<?php

namespace app\v6\handle\logic;

use app\v6\handle\hook\OrderInit;
use app\v6\handle\query\OrderQuery;
use app\v6\model\BaseModel;
use app\v6\model\Main\Channel;
use app\v6\model\Main\Shop;
use app\v6\model\Shop\OrderRetailExpress;
use app\v6\model\Shop\User;
use app\v6\Services\BaseService;
use app\v6\Services\PmsApi;
use lib\Express;
use lib\Status;
use third\S;

/**
 * 订单相关逻辑
 * X-Wolf
 * 2018-6-14
 */
class OrderLogic extends BaseService
{
    private $query;


    public function __construct()
    {
        $this->query = new OrderQuery();
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
            $shop_id = BaseModel::validSubId($channel);
            if ($shop_id === false) {
                error(40000, '门店错误！');
            }
        } else {
            $shop_id = encrypt($params['sub_shop_id'], 4, false);//门店id
        }

        $data = $params;
        $data['user_id'] = $user_id;
        $data['contact_id'] = $contact_id;
        $data['order'] = $order;
        $data['channel'] = $channel;
        $data['shop_id'] = $shop_id;
        $data['coupon_price'] = 0;

        //使用了优惠券判断优惠券的状态
        if (isset($params['coupon_id']) && !empty($params['coupon_id'])) {
            $product_id = OrderInit::factory($params['type'])->apply('getProductId', $params['id']);
            $price = $params['total_price'] ?? 0;
            if (isset($params['type']) && $params['type'] == 4) {
                //商超产品优惠卷价格判断用原始总价
                $price = $params['total_fee'] ?? 0;
            }
            $data['coupon_price'] = $this->coupon($params['coupon_id'], $user_id, $product_id, $price, $channel, $params['type']);
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
        } elseif ($params['type'] == 4) {
            $data['id'] = encrypt($params['id'], 1, false);
        } else {
            error(40000, '参数不正确!');
        }
        OrderInit::factory($params['type'])->apply('create', $data);
        success(['order_id' => $order]);
    }

    public function vaildPost($data, $length)
    {
        if (strlen($data) <= $length) {
            return true;
        } else {
            return false;
        }
    }

    //订单创建校验数据

    private function coupon($coupon, $user, $product, $price, $channel, $type)
    {
        $couponData = $this->query->getCoupon($coupon, $user, $channel);
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
        $haveProductLimit = $this->query->getProductByCouponCount($couponData['coupon_id']);
        if ($haveProductLimit > 0) {
            //有产品限制
            $couponArr = $this->query->getProductByCoupon($couponData['coupon_id'], $product, $channel, $type);
            $productArr = empty($couponArr) ? [] : $couponArr->column("product_id");
            if (!isset($couponArr[0]) && !in_array($product, $productArr)) {
                $couponArr = $this->query->getCouponByPro($couponData['coupon_id'], $product, $channel, $type);
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

    //校验时间

    public function vaildTime($price_map)
    {
        $price_map_count = count($price_map);


        sort_array($price_map, 'date', 'asc', 'string');
        $price_maps = [];
        $total = 0;
        foreach ($price_map as $k => $item) {
            if ($k >= 1) {
                if ((strtotime($item['date']) - $date_time) != '86400') {
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
        $shop_arr = Shop::where('channel', $channels['channel'])->select()->toArray();
        if (!$shop_arr) {
            error(40000, 'shop_id错误！');
        }
        $shopName = array_column($shop_arr, 'name', 'id');

        $limit = startLimit($params);
        $status = isset($params['status']) ? (int)$params['status'] : 0;

        $orders = $this->query->getOrders($channels, $users, $limit, $status);
        $count = $this->query->getOrdersCount($channels, $users, $status);
        $list = [];
        // 加密使用的key标志 从app.php读取
        $type = [1 => 6, 2 => 1, 4 => 1, 5 => 1];
        if ($orders) {
            foreach ($orders as $order) {
                $data = json_decode($order->info['data'], true);
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
                    "name" => $data['name'] ?? $data['product_item_name'] ?? '',
                    "expire" => isset($data['checkin']) ? date('Y-m-d', $data['checkin']) . "至" . date('Y-m-d', $data['checkout']) : '', // 入住有效期
                    'product_id' => encrypt($order['product'], $type[$order['type']]),
                    "is_refundable" => $refund, // 是否可退款
                    'shop_id' => encrypt($order['shop_id'], 4),
                    'type' => $order['type'],
                    'sub_status' => $order['sub_status'],
                    'use_date' => isset($data['use_start']) ? date('Y-m-d', $data['use_start']) : '',
                    'is_docking' => $order['goods_code'] ? 1 : 0, //是否对接
                    'transport_type' => $order->retail->transport_type ?? '' //发货类型
                );
            }
        }
        success(['list' => $list, 'total_count' => $count]);
    }

    protected function checkRefundable($order)
    {
        $is_refundable = false;
        switch ($order['type']) {
            case Status::CALENDAR_PRODUCT:
            case Status::SUIT_PRODUCT:
            case Status::VOUCHER_PRODUCT:
                if (($order['status'] == 3 || $order['status'] == 6) && ($order['refund_status'] == 0 || $order['refund_status'] == 2)) {
                    $is_refundable = true;
                }
                break;
            case Status::MARKET_PRODUCT:
            case Status::TICKET_PRODUCT:
                if (in_array($order['status'], [Status::ORDER_PAY, Status::ORDER_CONFIRM])
                    && in_array($order['refund_status'], [Status::REFUND_DEFAULT, Status::REFUND_REFUSE])) {
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
        $getOrder = $this->query->getOrderById($channels, $users, $params['order_id']);
        $list = [];
        if (!empty($getOrder)) {
            $getOrder = $getOrder[0];
            $data = json_decode($getOrder['data'], true);
            $expire = NOW - 1800;
            if ($getOrder['status'] == 2 && $getOrder['create'] < $expire) {
                $getOrder['status'] = 9;
            }//超时订单状态为关闭.

            if (in_array($getOrder['type'], [1, 2, 3, 5])) {
                $refundReasonType = 1;
            } else {
                $refundReasonType = $getOrder['type'];
            }

            $list = OrderInit::factory($getOrder['type'])->apply('orderDetail', $getOrder, $data);
            $list['refund']['reason_map'] = $this->query->getRefundReason($refundReasonType);
            $list['order_id'] = $getOrder['order'];
            $list['order_time'] = date('Y-m-d H:i:s', $getOrder['create']);
            $list['order_status'] = $getOrder['status'];
            $list['rebate'] = floatval($getOrder['rebate']);
            $list['shop_id'] = encrypt($getOrder['shop_id'], 4);
            $list['shop_name'] = $data['sub_shop_name'];
            $list['contact'] = ['name' => $getOrder['contact'], 'tel' => $getOrder['mobile']];
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
        $list = OrderInit::factory($params['type'])->apply('booking', $params);
        if ($list) {
            success(['operation' => 1]);
        }
    }

    public function complete($channels, $params)
    {
        if (!isset($params['order_id']) || !isset($params['type'])) {
            error(40000, '参数不全！');
        }
        OrderInit::factory($params['type'])->apply('complete', $params);
    }

    //订单申请退款
    public function refund($channels, $params, $users)
    {
        if (!isset($params['order_id']) && !isset($params['refund_reason'])) {
            error(40000, '参数不全！');
        }
        $order = $params['order_id'];
        $getOrder = $this->query->getOrderById($channels, $users, $order);
        if (empty($getOrder)) {
            error(40000, '不存在该订单！');
        }
        $data = json_decode($getOrder[0]['data'], true);
        $getOrder = $getOrder[0];
        OrderInit::factory($getOrder['type'])->apply('refund', $getOrder, $users);
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
        $userName = User::where('id', $users)->value('nickname');
        if (empty($userName)) {
            error(40000, '用户不存在!');
        }
        if (in_array($getOrder['type'], [1, 2, 3, 5])) {
            $refundReasonType = 1;
        } else {
            $refundReasonType = $getOrder['type'];
        }
        $get_refund_type = $this->query->getRefundType($refundReasonType);
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
            'username' => $userName,
            'identity' => 1,
            'create' => NOW,
        ];
        $reOrder = $this->query->RefundOrder($getOrder['id']);
        if (empty($reOrder['order_id'])) {
            $this->query->refund($refund, $refund_log);
        } else {
            $this->query->refundAgain($refund, $refund_log, $reOrder['id']);
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
        $ret = OrderInit::factory($order['type'])->apply('smsApplyRefund', $order);
        if ($ret) {
            $res = S::exec($order, 2);
            S::log('退款申请 - 及时发送短信结果:' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }

    function express($channels, $params, $users)
    {
        if (!isset($params['transport_order'])) {
            error(40000, '参数不全！');
        }
        $transport_order = $params['transport_order'];
        $getOrder = $this->query->getExpress($channels, $users, $transport_order);
        $list = [];
        if (!empty($getOrder)) {
            $getOrder = $getOrder[0];
            $data = json_decode($getOrder['data'], true);
            $list = [
                'cover' => picture($data['bucket'], $data['cover']),
                'order_status' => $getOrder['status'],
                'transport_company' => $getOrder['transport_company'],
                'transport_code' => $getOrder['transport_code'],
                'state' => 0,
                'traces' => []
            ];

            $retail = OrderRetailExpress::field('express')->where('transport_order', $transport_order)->where('channel', $channels['channel'])->find();
            if (empty($retail)) {
                $CustomerName = '';
                $transport_code = $retail['transport_code'];
                if ($transport_code == 'JD') {
                    $customer_name_data = Channel::field('customer_name')->where('id', $channels['channel'])->find();
                    $CustomerName = $customer_name_data['customer_name'];
                }
                if ($transport_code == 'SF') {
                    $receive_address = json_decode($getOrder['receive_address'], true);
                    $CustomerName = substr($receive_address['mobile'], -4);
                }
                $getExpress = Express::getTrace($getOrder['transport_code'], $transport_order, $CustomerName);
                if (is_array($getExpress)) {
                    //有数据的情况
                    $state = (int)$getExpress['State'];
                    $list['state'] = $state;
                    if ($getExpress['Success']) {

                        $traces = $getExpress['Traces'];
                        $setTraces = [];
                        if (!empty($traces)) {
                            $length = count($traces);
                            for ($i = $length - 1; $i >= 0; $i--) {
                                $setTraces[] = [
                                    'time' => $traces[$i]['AcceptTime'],
                                    'station' => $traces[$i]['AcceptStation'],
                                ];
                            }
                            $list['traces'] = $setTraces;
                            if ($state == 3) {
                                //已经签收的要存库
                                $post = [
                                    'state' => $state,
                                    'traces' => $setTraces
                                ];

                                OrderRetailExpress::create([
                                    'channel' => $channels['channel'],
                                    'transport_order' => $transport_order,
                                    'express' => json_encode($post, JSON_UNESCAPED_UNICODE)
                                ]);

                            }
                        }

                    } else {
                        error(50000, $getExpress['Reason']);
                    }

                }

            } else {
                $express = json_decode($retail['express'], true);
                $list['state'] = (int)$express['state'];
                $list['traces'] = $express['traces'];
            }

            success($list);

        } else {
            error(50000, '没有找到此单号');
        }
        success($list);
    }
}
