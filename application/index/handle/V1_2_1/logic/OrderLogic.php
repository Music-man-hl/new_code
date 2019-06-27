<?php

namespace app\index\handle\V1_2_1\logic;

use app\common\model\Room;
use app\index\Services\PmsApi;
use lib\Status;
use third\S;

/**
 * 订单相关逻辑
 * X-Wolf
 * 2018-6-14
 */
class OrderLogic
{
    private $handle;
    private $api_version;


    public function __construct($api_version)
    {
        $this->api_version = $api_version;
        $model_path = $api_version . "model\OrderModel";
        $this->handle = new $model_path();
    }

    public function create($channels, $params, $users)
    {
        //共用参数
        if (
            !isset($params['contact_id']) ||
            !isset($params['total_price']) ||
            !isset($params['type']) ||
            !isset($params['id'])
        ) {
            error(40000, '参数不全');
        }

        $user_id = (int)$users;
        $channel = (int)$channels['channel'];
        $order = makeOrder($channel);
        $contact_id = (int)$params['contact_id'];
        $remark = isset($params['remark']) ? filterEmoji(trim($params['remark'])) : '';
        if (!$this->vaildPost(json_encode($remark), 200)) {
            error(40000, '参数长度超限');
        }
        if (empty($params['sub_shop_id'])) {
            $shop_id = Room::validSubId($channel);
            if ($shop_id === false) {
                error(40000, '门店错误！');
            }
        } else {
            $shop_id = encrypt($params['sub_shop_id'], 4, false);//门店id
        }

        //引入钩子
        $classes = $this->api_version . "hook\OrderInit";

        $data = $params;
        $data['user_id'] = $user_id;
        $data['contact_id'] = $contact_id;
        $data['order'] = $order;
        $data['channel'] = $channel;
        $data['shop_id'] = $shop_id;

        //使用了优惠券判断优惠券的状态
        if (isset($params['coupon_id']) && !empty($params['coupon_id'])) {
            $product_id = $classes::factory($params['type'])->apply('getProductId', $params['id']);
            $data['coupon_price'] = $this->coupon($params['coupon_id'], $user_id, $product_id, $params['total_price'], $channel, $params['type']);
            $data['coupon'] = $params['coupon_id'];
            unset($params['coupon_id']);
        }

        //判断不同的订单类型
        if ($params['type'] == '5') {
            $data['voucher_id'] = encrypt($params['id'], 2, false);
        } elseif ($params['type'] == '1') {
            $vaildTime = $this->vaildTime($data['price_map']);
            $data['vaildTime'] = $vaildTime;
            $data['room_id'] = $data['id'];
        } elseif ($params['type'] == '2') {
            $data['ticket_id'] = encrypt($params['id'], 1, false);
        } else {
            error(40000, '参数不正确!');
        }
        $classes::factory($params['type'])->apply('orderCreate', $data);
        success(['order_id' => $order]);
    }


    private function coupon($coupon, $user, $product, $price, $channel, $type)
    {
//        $channel        = 1001;
        $couponData = $this->handle->getCoupon($coupon, $user, $channel);
        if (empty($couponData) || $couponData['status'] == '1' || $couponData['lock_time'] > NOW) {
            error(40000, '券不可用!');
        }
        if ($couponData['start'] > NOW) {
            error(40000, '券未到可用时间!');
        }
        if ($couponData['end'] < NOW) {
            error(40000, '券已过期!');
        }
        if ($couponData['limit'] > $price) {
            error(40000, '优惠券价格未到达限制条件!');
        }

        //做一下校验，是否有选中的产品
        $haveProductLimit = $this->handle->getProductByCouponCount($couponData['coupon_id']);
        if ($haveProductLimit > 0) {
            //有产品限制
            $couponArr = $this->handle->getProductByCoupon($couponData['coupon_id'], $product, $channel, $type);
            $productArr = empty($couponArr) ? [] : array_column($couponArr, 'product_id');
            if (!isset($couponArr[0]) && !in_array($product, $productArr)) {
                $couponArr = $this->handle->getCouponByPro($couponData['coupon_id'], $product, $channel, $type);
                if ($couponArr[0]['totalNum'] != 0) {
                    error(40000, '此商品无法使用该券!');
                }
            }
        }

        if ($couponData['type'] == '2') {
            // $couponPrice = sprintf("%.2f", $price*(1 - $couponData['value']));
            // 改为向下取整
            $couponPrice = floor($price * (1 - $couponData['value']) * 100) / 100;
        } else {
            if ($price > $couponData['value']) {
                $couponPrice = $couponData['value'];
            } else {
                error(40000, '此商品无法使用该券!');
            }
        }

        return $couponPrice;
    }

    //订单创建校验数据

    public function vaildPost($data, $length)
    {
        if (strlen($data) <= $length) {
            return true;
        } else {
            return false;
        }
    }

    //校验时间
    public function vaildTime($price_map)
    {
        $price_map_count = count($price_map);


        sort_array($price_map, 'date', 'asc', 'string');
        $price_maps = [];
        $total = 0;
        foreach ($price_map as $k=> $item) {
            if ($k>=1) {
                if ((strtotime($item['date'])-$date_time) != '86400') {
                    error(40000, '时间不正确');
                }
            }
            $date_time = strtotime($item['date']);
            if (empty($date_time)) {
                error(40000, '时间不正确');
            }
            $price_maps[$date_time] = $item['price'];
            $total = add($total, $item['price']);
        }

        if ($price_map_count != count($price_maps)) {
            error(40000, '时间价格不正确');
        }
        return ['price_map' => $price_maps, 'total' => $total];
    }


    public function lists($channels, $params, $users)
    {
        //相同的参数判断
        $shop_arr = $this->handle->getShopName($channels['channel']);
        if (!$shop_arr) {
            error(40000, 'shop_id错误！');
        }
        $shopName = array_column($shop_arr, 'sub_shop_name', 'id');

        $startLimit = startLimit($params);
        $status = isset($params['status']) ? (int)$params['status'] : 0;

        $orders = $this->handle->getOrders($channels, $users, $startLimit, $status, 'desc');
        $count = $this->handle->getOrdersCount($channels, $users, $status);
        $list = [];
        // 加密使用的key标志 从app.php读取
        $type = [5 => 1, 1 => 6, 2 => 1];
        if ($orders) {
            foreach ($orders as $order) {
                $data = json_decode($order['data'], true);
                // if ($order['status'] == 3 && ($order['refund_status'] == 0 || $order['refund_status'] == 2)) {
                //     $refund = true;
                // } else {
                //     $refund = false;
                // }
                // if (\in_array($order['status'], [Status::ORDER_PAY,Status::ORDER_CONFIRM])
                //     && \in_array($order['refund_status'], [])) {
                // }
                $refund = $this->checkRefundable($order);
                $expire = NOW - 1800;
                if ($order['status'] == 2 && $order['create'] < $expire) {
                    $order['status'] = 9;
                }
                if ($status == '2' && $order['status'] == 9) {
                    continue;
                }
                $list[] = array(
                    "order_id" => $order['order'],
                    "order_time" => date('Y-m-d', $order['create']), // 下单时间,精确到天
                    "order_status" => $order['status'],
                    "pay_total" => floatval(add($order['total'], -$order['rebate'], -$order['sales_rebate'])),
                    "order_count" => $order['count'],
                    "cover" => picture($data['bucket'], $data['cover']),
                    "shop_name" => $shopName[$order['shop_id']],
                    "product_name" => $order['product_name'],
                    "name" => isset($data['name']) ? $data['name'] : '',
                    "expire" => isset($data['checkin']) ? date('Y-m-d', $data['checkin']) . "至" . date('Y-m-d', $data['checkout']) : '', // 入住有效期
                    'product_id' => encrypt($order['product'], $type[$order['type']]),
                    "is_refundable" => $refund, // 是否可退款
                    'shop_id' => encrypt($order['shop_id'], 4),
                    'type' => $order['type'],
                    'sub_status' => $order['sub_status'],
                    'use_date' => isset($data['use_start']) ? date('Y-m-d', $data['use_start']) : '',
                    'is_docking' => $order['goods_code'] ? 1 : 0, //是否对接
                    // 'refund_status' => $order['refund_status'],
                );
            }
        }
        success(['list' => $list, 'total_count' => $count[0]['count']]);
    }

    protected function checkRefundable($order)
    {
        $is_refundable = false;
        switch ($order['type']) {
            case Status::CALENDAR_PRODUCT:
            case Status::SUIT_PRODUCT:
            case Status::MARKET_PRODUCT:
            case Status::VOUCHER_PRODUCT:
                if ($order['status'] == 3 && ($order['refund_status'] == 0 || $order['refund_status'] == 2)) {
                    $is_refundable = true;
                }
                break;
            case Status::TICKET_PRODUCT:
                if (\in_array($order['status'], [Status::ORDER_PAY,Status::ORDER_CONFIRM])
                    && \in_array($order['refund_status'], [Status::REFUND_DEFAULT,Status::REFUND_REFUSE])) {
                    $is_refundable = true;
                }
                break;
        }
        return $is_refundable;
    }

    public function detail($channels, $params, $users)
    {
        if (!isset($params['order_id'])) {
            error(40000, '参数不全！');
        }
        $getOrder = $this->handle->getOrderById($channels, $users, $params['order_id']);
        $list = [];
        $refundReason = $this->handle->getRefundReason(1);
        if (!empty($getOrder)) {
            $getOrder = $getOrder[0];
            $data = json_decode($getOrder['data'], true);
            $expire = NOW - 1800;
            if ($getOrder['status'] == 2 && $getOrder['create'] < $expire) {
                $getOrder['status'] = 9;
            }//超时订单状态为关闭.

            $classes = $this->api_version . "hook\OrderInit";
            $list = $classes::factory($getOrder['type'])->apply('orderDetail', $getOrder, $data);
            $list['refund']['reason_map'] = $refundReason;
            $list['order_id'] = $getOrder['order'];
            $list['order_time'] = date('Y-m-d H:i:s', $getOrder['create']);
            $list['order_status'] = $getOrder['status'];
            $list['rebate'] = floatval($getOrder['rebate']);
            $list['shop_id'] = encrypt($getOrder['shop_id'], 4);
            $list['shop_name'] = $data['sub_shop_name'];
            $list['contact'] = ['name' => $getOrder['contact'], 'tel' => $getOrder['mobile']];
//            $list['coupon']                        =    0;
        }
        success($list);
    }

    //订单预约
    public function booking($channels, $params, $users)
    {
        if (!isset($params['order_id']) || !isset($params['checkin']) || !isset($params['type'])) {
            error(40000, '参数不全！');
        }
        $params['user'] = $users;
        $params['channel'] = $channels['channel'];
        $classes = $this->api_version . "hook\OrderInit";
        $list = $classes::factory($params['type'])->apply('booking', $params);
        if ($list) {
            success(['operation' => 1]);
        }
    }

    //订单申请退款
    public function refund($channels, $params, $users)
    {
        if (!isset($params['order_id']) && !isset($params['refund_reason'])) {
            error(40000, '参数不全！');
        }
        $order = $params['order_id'];
        $getOrder = $this->handle->getOrderById($channels, $users, $order);
        if (empty($getOrder)) {
            error(40000, '不存在该订单！');
        }
        $data = json_decode($getOrder[0]['data'], true);
        $getOrder = $getOrder[0];
        $classes = $this->api_version . "hook\OrderInit";
        $return = $classes::factory($getOrder['type'])->apply('refund', $getOrder, $users);
        if (!$this->checkRefundable($getOrder)) {
            error(40000, '此订单状态不允许退款');
        }
        $refund = [
            'channel' => $getOrder['channel'],
            'order_id' => $getOrder['id'],
            'order' => $params['order_id'],
            'num' => createRefundNum(),
            'status' => 1,
            'apply_total' => $data['pay_total'],
            'refund_reason' => isset($params['remark']) ? $params['remark'] : '',
            'refund_type' => $params['refund_reason'],
            'sponsor' => 1,
            'source' => 1,
            'create' => NOW,
            'update' => NOW,
        ];
        $user = $this->handle->getContactByUid($users);
        if (empty($user)) {
            error(40000, '不存在联系人！');
        }
        $get_refund_type = $this->handle->getRefundType('1');
        $type = [];
        foreach ($get_refund_type as $v) {
            $type[$v['id']] = $v['name'];
        }
        $typeName = $type[$params['refund_reason']];
        $remark = isset($params['remark']) ? $params['remark'] : '';
        $refund_log = [
            'type' => 1,
            'reason' => $typeName . '，' . $remark,
            'userid' => $users,
            'username' => $user['name'],
            'identity' => 1,
            'create' => NOW,
        ];
        $reOrder = $this->handle->RefundOrder($getOrder['id']);
        if (empty($reOrder['order_id'])) {
            $this->handle->refund($refund, $refund_log);
        } else {
            $this->handle->refundAgain($refund, $refund_log, $reOrder['id']);
        }

        //pms 产品退款
        if ($getOrder['goods_code']) {
            $this->refundPms($getOrder) || error(50000, '退款失败');
        }

        $this->refundApplySms($getOrder);

        success(['operation' => 1]);
    }

    private function refundPms($getOrder)
    {
        $data = [
            'channel' => $getOrder['channel'],
            'pms_id' => $getOrder['pms_id'],
            'orderCode' => $getOrder['order'],
        ];
        return PmsApi::service()->cancelOrder($data);
    }

    //退款申请短信
    private function refundApplySms($order)
    {
        $classes = $this->api_version . "hook\OrderInit";
        $ret = $classes::factory($order['type'])->apply('smsApplyRefund', $order);
        if ($ret) {
            $res = S::exec($order['order']);
            S::log('退款申请 - 及时发送短信结果:' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }
}
