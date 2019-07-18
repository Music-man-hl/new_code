<?php


namespace app\v6\handle\hook\shop;

use app\v6\model\Main\Shop;
use app\v6\model\Shop\MessageSend;
use app\v6\model\Shop\Product;
use app\v6\model\Shop\ProductTicketItem;
use lib\Status;
use third\S;

class OrderQuery
{
    const STAT_VALID = 1; //有效


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
