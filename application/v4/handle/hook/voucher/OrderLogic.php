<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v4\handle\hook\voucher;

use lib\Status;

class OrderLogic
{
    public static function pay($getOrder, $param)
    {
        return OrderQuery::pay($getOrder, $param);
    }

    public static function smsPaySuccess($order)
    {
        return OrderQuery::smsPaySuccess($order);
    }

    public static function sendWxTmp($order)
    {
        return OrderQuery::sendWxTmp($order);
    }

    public static function smsApplyRefund($order)
    {
        return OrderQuery::smsApplyRefund($order);
    }

    public static function informPayInfo($order)
    {
        return OrderQuery::informPayInfo($order);
    }

    public static function informPay($order, $tpl, $errcode, $errmsg)
    {
        return OrderQuery::informPay($order, $tpl, $errcode, $errmsg);
    }

    //订单创建操作
    public static function create($data)
    {
        if (empty($data['voucher_id']) || !isset($data['count'])) {
            error(40000, '参数不全');
        }
        $count = (int)$data['count'];
        $product_type = 5;//产品类型
        $channel = $data['channel'];
        $shop_id = $data['shop_id'];
        $total_price = $data['total_price'];
        $contact_id = $data['contact_id'];
        $order = $data['order'];
        $user_id = $data['user_id'];
        $voucher_id = $data['voucher_id'];
        $extensionUser = $data['extension_user'] ?? 0;
        //校验参数
        $product = OrderQuery::getProduct($channel, $shop_id, $voucher_id);
        $total_order = count(OrderQuery::getOrderByVoucher($voucher_id));
        if (isset($product[0])) {
            $product = $product[0];
        } else {
            error(40000, '未找到产品！');
        }
        if (($product['allot'] - $total_order - $product['sales'] - $count) < 0) {
            error(40000, '库存不足！');
        }
        if ($count < $product['min'] || $count > $product['max']) {
            error(40000, '购买数量有误！');
        }

        $level = OrderQuery::getStandard($product['level1'], $product['level2']);
        if (empty($level)) {
            error(40000, '未找到层级！');
        }
        $levelArr = [];
        foreach ($level as $v) {
            $levelArr[$v['level']] = $v;
        }
        if (isset($levelArr['2'])) {
            $name = $levelArr['2']['value'];
        } else {
            $name = $levelArr['1']['value'];
        }
        $shop_arr = OrderQuery::getShop($shop_id);
        if (empty($shop_arr)) {
            error(50000, '没有找到店铺');
        }
        $shop = $shop_arr[0];

        if (bcmul($product['sale_price'], $count, 2) != $total_price) {
            error(40000, '价格不正确');
        }
        //校验库存和价格是否一致

        $contact = OrderQuery::getContact($contact_id);
        if (empty($contact)) {
            error(40800, '联系人不存在!');
        }
        $pic = OrderQuery::getPic($shop_id, 1);
        if (isset($data['coupon']) && isset($data['coupon_price'])) {
            $coupon = $data['coupon'];
            $rebate = $data['coupon_price'];
        } else {
            $coupon = 0;
            $rebate = 0;
        }

        $data = [
            'order' => $order,
            'total' => $total_price,//订房价格
            'count' => $count,//订房数
            'channel' => $channel,
            'shop_id' => $shop_id,
            'rebate' => $rebate,
            'coupon_id' => $coupon,
            'product' => $product['id'],
            'product_name' => $product['name'],
            'type' => $product_type,
            'contact' => $contact['name'],
            'mobile' => $contact['mobile'],
            'uid' => $user_id,
            'status' => 2,
            'ip' => getIp(),
            'pv_from' => '微信小程序',
            'extension_user' => $extensionUser,
        ];

        $level2 = isset($levelArr['2']['value']) ? $levelArr['2']['value'] : '';
        $snap = [
            'product_name' => $product['name'],
            'product_id' => $product['id'],
            'voucher_id' => $voucher_id,
            'name' => $levelArr['1']['value'] . ' ' . $level2,
            'voucher_desc' => $product['voucher_intro'],
            'level1' => $levelArr['1']['value'],
            'level2' => $level2,
            'product_title' => $product['title'],
            'booking_info' => $product['booking_info'],
            'market_price' => $product['market_price'],
            'sale_price' => $product['sale_price'],
            'count' => $count,
            'bucket' => $product['bucket'],
            'cover' => $product['pic'],
            'price_total' => bcmul($product['sale_price'], $count, 2), //总价格
            'pay_total' => $total_price,//实付金额
            'shop_name' => $shop['shop_name'],
            'sub_shop_name' => $shop['sub_shop_name'],
            'shop_group' => $shop['group'],
            'pic' => $pic,        //详细描述图片
            'intro' => $product['intro'],
            'rule' => $product['rule'],
            'refund' => $product['refund'],
            'booking_start' => $product['booking_start'],
            'booking_end' => $product['booking_end']
        ];
        return OrderQuery::create($data, $snap);
    }

    public static function orderDetail($getOrder, $data)
    {
        $order_voucher = OrderQuery::getOrderVoucherByOrder($getOrder['order']);
        $product = OrderQuery::getProductByIdForOrder($data['product_id']);
        if (!isset($order_voucher[0])) {
            error(40000, '券不存在！');
        }

        $item = [];
        if ($data['booking_end'] < (NOW - 86400) || $getOrder['status'] == Status::ORDER_UNPAY || ($getOrder['status'] == Status::ORDER_CLOSE && in_array($getOrder['refund_status'], [0, 3])) || ($getOrder['status'] != Status::ORDER_BOOKING_FAIL && in_array($getOrder['refund_status'], [1]))) {
            $status = 99;
        }
        if ($getOrder['status'] == Status::ORDER_BOOKING_FAIL && in_array($getOrder['refund_status'], array(1))) {
            $status = 98;
        }
        foreach ($order_voucher as $vv) {
            if (in_array($vv['status'], array(Status::TICKET_BOOKING, Status::TICKET_CONFIRM, Status::TICKET_COMPLETE))) {
                $refund_status = false;
            }
            if ($getOrder['status'] == Status::ORDER_CLOSE && $vv['status'] == Status::TICKET_BOOKING_FAIL) {
                $status = 98;
            }
            if (isset($status) && $getOrder['status'] != Status::ORDER_CLOSE && $status == 98 && $vv['status'] == 0) {
                $statusEx = $status;
                $status = 99;
            } else {
                if (isset($status)) {
                    $statusEx = $status;
                }
            }
            if ($getOrder['refund_status'] == 3 && $vv['status'] == 0) {
                $status = 99;
            } else {
                if (isset($status)) {
                    $statusEx = $status;
                }
            }
            $item[] = [
                'order_voucher_id' => $vv['id'],
                'status' => isset($status) ? $status : $vv['status'],
                'remark' => $vv['remark'],
                'check_in_time' => $vv['checkin'],
                'contact_list' => json_decode($vv['people'], true),
            ];
            if (isset($statusEx)) {
                $status = $statusEx;
            }
        }
        $voucherArr[] = [
            'desc' => isset($data['voucher_desc']) ? $data['voucher_desc'] : '',
            'standard' => [
                $data['level1'],
                $data['level2'],
            ],
            'price' => floatval($data['price_total'] / $data['count']),
            'user_type' => $product['booking_info'],
            'items' => $item,
        ];
        if ((($getOrder['status'] == 3 || $getOrder['status'] == 6) && ($getOrder['refund_status'] == 0 || $getOrder['refund_status'] == 2)) && $data['booking_end'] > (NOW - 86400)) {
            $refund = true;
        } else {
            $refund = false;
        }

        if (isset($refund_status)) {
            $refund = $refund_status;
        }
        $list = [
            "product_name" => $data['product_name'],
            "product_id" => encrypt($getOrder['product'], 1),
            "product_cover" => picture($data['bucket'], $data['cover']),
            "order_count" => $data['count'], // 订单件数
            "order_price" => ceil(floatval($data['price_total'] / $data['count']) * 100) / 100, // 该订单预定期间的均价
            "order_total" => floatval($data['price_total']),
            "pay_total" => floatval(add($getOrder['total'], -$getOrder['rebate'], -$getOrder['sales_rebate'])),
            "order_remark" => $getOrder['remark'],
            "refund" => [
                'status' => $getOrder['refund_status'],
            ],
            'shop_id' => encrypt($getOrder['shop_id'], 4),
            "refund_rule" => $data['refund'],
            "contain" => $data['intro'],
            "usage" => $data['rule'],
            "type" => 5, //产品类型
            "voucher" => $voucherArr //券信息
        ];
        $list['refund']['is_refundable'] = $refund;

        return $list;
    }

    public static function payCreateVild($orders)
    {
        $res = OrderQuery::productVoucherExist($orders['id']);
        if (empty($res)) {
            error(50000, '此产品已经发生变化，请重新下单');
        }
        if ((int)$orders['count'] > ((int)$res['allot'] - (int)$res['sales'])) {
            error(50000, '此产品库存不足，请重新下单');
        }
        return true;
    }

    //订单预约
    public static function booking($data)
    {
        if (!isset($data['adult'])) {
            error(40000, '预约人信息不存在！');
        }
        $voucherArr = OrderQuery::getVoucherByOrderId($data['order_id'], $data['id'], $data['user']);
        if (!isset($voucherArr[0])) {
            error(40000, '预约券信息不存在！');
        }
        $voucher = $voucherArr[0];
        if (count($voucherArr) > 1) {
            $sub_status = 1;
        } else {
            $sub_status = 0;
        }
        $jsonData = json_decode($voucher['data'], true);
        $product = OrderQuery::getProductById($voucher['product']);
        $voucherAll = [];
        foreach ($voucherArr as $v) {
            $voucherAll[$v['voucher_id']] = $v;
        }
        $voucher = $voucherAll[$data['id']];
        unset($voucherAll[$data['id']]);
        $status = 0;
        foreach ($voucherAll as $v) {
            if ($v['voucher_status'] != Status::TICKET_BOOKING) {
                $status = 1;
            }
        }
        if (empty($product)) {
            error(40000, '产品不存在！');
        }
        $vaild_status = [Status::ORDER_PAY, Status::ORDER_BOOKING, Status::ORDER_CONFIRM, Status::ORDER_BOOKING_FAIL];
        if (!in_array($voucher['status'], $vaild_status)) {
            error(40000, '订单状态不允许预约！');
        }
        if ($voucher['voucher_status'] != Status::TICKET_DEFAUT && $voucher['voucher_status'] != Status::TICKET_BOOKING_FAIL) {
            error(40000, '券状态不允许预约！');
        }
        if ($jsonData['booking_end'] < (NOW - 86400)) {
            error(40000, '券预约已过期！');
        }

        $itemDate = OrderQuery::getVoucherDate($voucherArr[0]['item_id'], $data['checkin']);
        if (empty($itemDate)) {
            error(40000, '当天不可预约！');
        }
        if (($itemDate['allot'] - $itemDate['used']) <= 0) {
            error(40000, '库存不足无法预约！');
        }
        $people = [];
        $user_info = [];
        if (isset($data['adult'])) {
            foreach ($data['adult'] as $v) {
                $people[] = $v;
                $user_info[] = [
                    'uid' => $data['user'],
                    'channel' => $data['channel'],
                    'type' => 5,
                    'name' => isset($v['name']) ? $v['name'] : '',
                    'id_info' => isset($v['id_card']) ? $v['id_card'] : '',
                    'size' => 1
                ];
            }
        }
        if (isset($data['child'])) {
            foreach ($data['child'] as $vv) {
                $people[] = $vv;
                $user_info[] = [
                    'uid' => $data['user'],
                    'channel' => $data['channel'],
                    'type' => 5,
                    'name' => isset($v['name']) ? $v['name'] : '',
                    'id_info' => isset($v['id_card']) ? $v['id_card'] : '',
                    'size' => 2
                ];
            }
        }


        $updateData = [
            'voucher_id' => $data['id'],
            'order_id' => $data['order_id'],
            'checkin' => $data['checkin'],
            'adultCount' => count($data['adult']),
            'people' => $people,
            'childCount' => count($data['child']),
            'remark' => isset($data['remark']) ? $data['remark'] : '',
            'status' => $status,
            'item_id' => $voucherArr[0]['item_id'],
            'sub_status' => $sub_status,
            'user' => $data['user'],
            'user_info' => $user_info,
        ];
        $return = OrderQuery::setVoucherBooking($updateData);
        return $return;
    }


    public static function refund($order, $user)
    {
        $data = OrderQuery::getVoucherByOrderId($order['order'], '', $user);
        $status_vaild = [Status::TICKET_DEFAUT, Status::TICKET_BOOKING_FAIL];
        foreach ($data as $v) {
            if (!in_array($v['voucher_status'], $status_vaild)) {
                error(40000, '券状态不允许退款');
            }
        }
    }

    public static function getProductId($id)
    {
        $id = encrypt($id, 2, false);
        $data = OrderQuery::getProductId($id);
        $product = OrderQuery::getProductById($data['pid']);
        if ($product['is_coupons'] == '0') {
            error(40000, '该产品不支持优惠券！');
        }
        return $data['pid'];
    }
}
