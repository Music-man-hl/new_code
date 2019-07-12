<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v6\handle\hook\hotel;

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
        if (!isset($data['id']) || !isset($data['count']) || !isset($data['price_map']) || !isset($data['guest_info'])) {
            error(40000, '参数不全');
        }
        $room_id = (int)encrypt($data['id'], 6, false);
        $count = (int)$data['count'];
        $price_map = $data['price_map'];
        $guest_info = $data['guest_info'];
        $product_type = 1;//产品类型
        $channel = $data['channel'];
        $shop_id = $data['shop_id'];
        $total_price = $data['total_price'];
        $contact_id = $data['contact_id'];
        $order = $data['order'];
        $user_id = $data['user_id'];
        $remark = $data['remark'];
        $vaildTime = $data['vaildTime'];
        $extensionUser = $data['extension_user'] ?? 0;
        //校验参数
        foreach ($guest_info as $k => $v) {
            $guest_info[$k]['name'] = filterEmoji($v['name']);
        }
        $room = OrderQuery::getRoomType($channel, $shop_id, $room_id);
        if ($count < $room['min_limit'] || $count > $room['max_limit']) {
            error(40000, '购买数量有误！');
        }
        $shop_arr = OrderQuery::getShop($room['shop_id']);
        if (empty($shop_arr)) {
            error(50000, '没有找到店铺');
        }
        $shop = $shop_arr[0];

        $price_maps = $vaildTime['price_map'];
        $total = $vaildTime['total'];
        if (bcmul($total, $count, 2) != $total_price) {
            error(40000, '价格不正确');
        }
        $data_map = array_keys($price_maps);
        $where = ['channel' => $channel, 'room' => $room_id, 'status' => 1];
        $where['date'] = $data_map;
        $hotel_booking = OrderQuery::getBooking($where);
        if (empty($room) || count($hotel_booking) != count($data_map)) {
            error(50000, '预约房不存在');
        }
        //校验库存和价格是否一致

        foreach ($hotel_booking as $item) {
            $getOrderCount = OrderQuery::getOrderCount($channel, $item['room'], $item['date'], 2, NOW - 1800);
            if (!empty($getOrderCount)) {
                $usedCount = $getOrderCount[0]['cou'];
            } else {
                $usedCount = 0;
            }
            if ($item['allot'] - $item['used'] - $count - $usedCount < 0) {
                error(40800, '没有库存了');
            }
            if (!isset($price_maps[$item->getData('date')]) || $item['price'] != $price_maps[$item->getData('date')]) {
                error(40800, '价格不正确');
            }
        }
        $contact = OrderQuery::getContact($contact_id);
        if (empty($contact)) {
            error(40800, '联系人不存在!');
        }
        $pic = OrderQuery::getPic($shop_id, 1);

        //单独的逻辑用于处理优惠券
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
            'rebate' => $rebate,
            'coupon_id' => $coupon,
            'shop_id' => $shop_id,
            'product' => $room_id,
            'product_name' => $room['name'],
            'type' => $product_type,
            'contact' => $contact['name'],
            'mobile' => $contact['mobile'],
            'uid' => $user_id,
            'status' => 2,
            'ip' => getIp(),
            'pv_from' => '微信小程序',
            'remark' => $remark,
            'adult' => count($guest_info),
            'people' => json_encode($guest_info, JSON_UNESCAPED_UNICODE),
            'bed' => $room['bed_type'],
            'room_id' => $room['id'],
            'room_num' => $count,
            'extension_user' => $extensionUser,
        ];

        $night = count($data_map);
        $avg_price = ceil((float)$total_price / ($count * $night) * 100) / 100;

        $checkin = $data_map[0];
        $checkout = end($data_map) + 86400;
        $snap = [
            'name' => $room['name'],
            'feature' => $room['feature'],
            'room_area' => $room['room_area'],
            'uppermost_floor' => $room['uppermost_floor'],
            'adult_total' => $room['adult_total'],
            'child_total' => $room['child_total'],
            'bed_type' => $room['bed_type'],
            'is_smoke' => $room['is_smoke'],
            'is_take_pet' => $room['is_take_pet'],
            'is_add_bed' => $room['is_add_bed'],
            'user_data' => $room['user_data'],
            'checkin' => $checkin,
            'checkout' => $checkout,
            'night' => $night,
            'avg_price' => $avg_price,
            'count' => $count,
            'bucket' => $room['bucket'],
            'cover' => $room['cover'],
            'price_map' => $price_map,
            'price_total' => bcmul($total, $count, 2), //总价格
            'pay_total' => $total_price,//实付金额
            'guest_info' => $guest_info,//入住人信息
            'shop_name' => $shop['shop_name'],
            'sub_shop_name' => $shop['sub_shop_name'],
            'shop_group' => $shop['group'],
            'pic' => $pic        //详细描述图片
        ];
        return OrderQuery::create($data, $snap);
    }


    public static function orderDetail($getOrder, $data)
    {
        if (($getOrder['status'] == 3 || $getOrder['status'] == 6) && ($getOrder['refund_status'] == 0 || $getOrder['refund_status'] == 2)) {
            $refund = true;
        } else {
            $refund = false;
        }
        $list = [
            "product_name" => $data['name'],
            "product_id" => encrypt($getOrder['product'], 6),
            "product_cover" => picture($data['bucket'], $data['cover']),
            "order_count" => $data['count'], // 订单件数
            "order_price" => floatval($data['avg_price']), // 该订单预定期间的均价
            "price_map" => $data['price_map'],
            "guest_info" => $data['guest_info'],
            "order_total" => floatval($data['price_total']),
            "pay_total" => floatval(add($getOrder['total'], -$getOrder['rebate'], -$getOrder['sales_rebate'])),
            "order_remark" => $getOrder['remark'],
            "refund" => [
                'status' => $getOrder['refund_status'],
            ],
            'shop_id' => encrypt($getOrder['shop_id'], 4),
            "type" => 1 //产品类型


        ];
        $list['refund']['is_refundable'] = $refund;

        return $list;
    }

    public static function refund($order)
    {
        if ($order['status'] != '3' || $order['refund_status'] == 1 || $order['refund_status'] == 3) {
            error(40000, '该订单无法进行退款操作！');
        }
        return true;
    }

    public static function getProductId($id)
    {
        $id = (int)encrypt($id, 6, false);
        $data = OrderQuery::getProductId($id);
        return $data['hid'];
    }
}
