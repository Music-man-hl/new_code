<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v3\handle\hook\ticket;

use app\v3\model\Main\Shop;
use app\v3\model\Shop\CouponCode;
use app\v3\model\Shop\MessageSend;
use app\v3\model\Shop\Order;
use app\v3\model\Shop\OrderExt;
use app\v3\model\Shop\OrderInfo;
use app\v3\model\Shop\OrderTicket;
use app\v3\model\Shop\Product;
use app\v3\model\Shop\ProductTicketItem;
use lib\Status;
use think\Db;
use third\S;

class OrderQuery
{
    const STAT_VALID = 1; //有效

    public static function getTicketByOrderId($order_id, $ticket_id, $user)
    {
        $sql = 'SELECT o.id,o.status,v.status as ticket_status,o.product,v.id as ticket_id,v.item_id,i.data,o.`count` FROM `order` o 
            LEFT JOIN order_ticket v on o.id=v.order_id 
            LEFT JOIN order_info i on o.id=i.order_id 
            WHERE o.order = :order  AND o.uid=:uid AND v.status <> 4';
        return Order::query($sql, ['order' => $order_id, 'uid' => $user]);
    }

    //订单创建
    public static function create($data, $snap)
    {
        //Db::startTrans();
        try {
            $orderData = [
                'channel' => $data['channel'],
                'shop_id' => $data['shop_id'],
                'order' => $data['order'],
                'pms_id' => $data['pms_id'],
                'goods_code' => $data['goods_code'],
                'count' => $data['count'],
                'total' => $data['total'],
                'rebate' => $data['coupon_price'],
                'coupon_id' => $data['coupon_id'],
                'product' => $data['product'],
                'product_name' => $data['product_name'],
                'type' => 2,
                'contact' => $data['contact'],
                'mobile' => $data['mobile'],
                'uid' => $data['uid'],
                'update' => NOW,
                'create' => NOW,
                'date' => strtotime(date('Y-m-d')),
                'status' => 2,
                'ip' => getIp(),
                'expire' => NOW + 1800,
                'pv_from' => '微信小程序',
                'terminal' => 1,
            ];
            $orderId = Order::insertGetId($orderData);
            if (!$orderId) {
                //Db::rollback();
                error(50000, 'order_id 创建失败');
            }

            $order_ext_data = [
                'order_id' => $orderId,
                'channel' => $data['channel'],
                'order' => $data['order'],
            ];

            $res = OrderExt::insert($order_ext_data);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'order_ext 创建失败');
            }

            $order_info_data = [
                'order_id' => $orderId,
                'channel' => $data['channel'],
                'order' => $data['order'],
                'data' => json_encode($snap, JSON_UNESCAPED_UNICODE),
            ];
            $res = OrderInfo::insert($order_info_data);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'order_info 创建失败');
            }

            for ($i = 0; $i < $data['count']; $i++) {
                $people = [];
                if ($data['booking_info'] == 2) {
                    $people = [$data['people'][0]];
                }
                if ($data['booking_info'] == 3) {
                    $people = [$data['people'][$i]];
                }

                $orderTicketData = [
                    'channel' => $data['channel'],
                    'order_id' => $orderId,
                    'item_id' => $snap['product_item_id'],
                    'item_order' => $data['order'] . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'order' => $data['order'],
                    'status' => 0,
                    'people' => json_encode($people),
                    'checkin' => strtotime($data['use_date']),
                    'checkout' => strtotime($data['use_date']),
                    'terminal' => OrderTicket::APPLET,
                    'update' => NOW,
                    'create' => NOW,
                ];
                $ticketId = OrderTicket::insertGetId($orderTicketData);
                if (!$ticketId) {
                    //Db::rollback();
                    error(50000, 'order_ticket 创建失败');
                }
            }

            if ($data['coupon_id']) {
                $res = CouponCode::where('id', $data['coupon_id'])->update([
                    'lock_order' => $data['order'],
                    'lock_time' => NOW + 1800,
                ]);
                if ($res === false) {
                    //Db::rollback();
                    error(50000, '优惠券更新失败');
                }
            }

            //Db::commit();
            return true;
        } catch (\Exception $e) {
            //Db::rollback();
            return error(50000, exceptionMessage($e));
        }
    }

    // 短信 - 申请退款
    public static function smsApplyRefund($order)
    {
        $shop = Shop::field('`id`,`name`')
            ->where('id', $order['shop_id'])
            ->with(['tels'=> function ($query) {
                $query->field("citycode,tel,objid")
                    ->where("type", 1);
            }])
            ->find();
        if (empty($shop)) {
            S::log('发送申请退款短信 - 获取门店名称失败 订单:' . $order['order']);
            return false;
        }
        $params = [
            'product_name' => $order['product_name'],
            'order' => $order['order'],
            'mobile' => ($shop['tels']['citycode'] ? $shop['tels']['citycode'] . '-' : $shop['tels']['citycode']) . $shop['tels']['tel']
        ];

        $msg = [
            'channel' => $order['channel'],
            'product_type' => $order['type'],
            'msg_type' => Status::SMS_APPLY_REFUND,
            'mobile' => $order['mobile'],
            'order' => $order['order'],
            'data' => json_encode($params),
            'create' => NOW
        ];
        S::log('发送申请退款短信 - 发送短信数据:' . json_encode($msg, JSON_UNESCAPED_UNICODE));
        return MessageSend::insert($msg);
    }

    public static function getProductId($id)
    {
        return ProductTicketItem::where(['id' => $id])->field('pid')->find();
    }

    public static function getProductById($id)
    {
        return Product::where(['id' => $id])->field('is_coupons')->find();
    }
}
