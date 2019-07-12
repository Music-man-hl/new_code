<?php

namespace app\v6\handle\query;

use app\v6\model\Main\Channel;
use app\v6\model\Main\Shop;
use app\v6\model\Shop\Coupon;
use app\v6\model\Shop\CouponCode;
use app\v6\model\Shop\CouponProduct;

/**
 * 优惠券相关操作
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:29
 */
class CouponQuery
{

    const STATUS_OK = 1; //正常
    const STATUS_DELETE = 0; //删除 也是优惠券下线


    //获取券详情
    public function getCoupon($id, $channel)
    {
        return Coupon::where('channel', $channel)->where('id', $id)
            ->field('id,shop_id,channel,name,type,value,limit,start,end,day,max_geted,intro,count,geted,status')->find();
    }

    //获取总关联的券产品
    public function getProductTotal($id)
    {
        return CouponProduct::where('coupon_id', $id)->count();
    }

    //获取门店
    public function getChannel($channel)
    {
        return Channel::where('id', $channel)->field('name')->find();
    }

    //获取门店
    public function getChannelForCou($shopId)
    {
        return Shop::where('id', $shopId)->field('name')->find();
    }

    //通过code获取实际的券详情
    public function getCouponBycode($code, $channel, $user)
    {
        $couponCode = CouponCode::where('id', $code)->where('channel', $channel)->where('uid', $user)
            ->with('coupon')->find();
        return array_merge($couponCode->toArray(),$couponCode->coupon->toArray());
    }

    public function getCouponByProAndPrice($channel, $id, $users, $price, $type, $shop_id = '')
    {
        $sql = 'SELECT c.coupon_id as id,c.shop_id,c.id as `code`,co.name,c.type,c.value,c.limit,c.start,c.end,co.day,co.max_geted,co.intro,co.count,co.geted,c.status FROM coupon_product p 
                LEFT JOIN coupon co ON co.id=p.coupon_id
                LEFT JOIN coupon_code c ON co.id=c.coupon_id
                WHERE c.`channel`=:channel AND  p.`product_id`=:id AND c.uid=:uid AND c.status=0 AND c.start<=:start AND c.end>=:end AND c.`limit`<=:price AND p.product_type=:type AND co.shop_id=:shop_id';

        return CouponProduct::where('product_id', $id)
            ->where('product_type', $type)
            ->with(['coupon' => function ($query) use ($channel, $users, $price, $shop_id) {
                $query->with(['code' => function ($query) use ($channel, $users, $price, $shop_id) {
                    $query->where('channel', $channel)
                        ->where('shop_id', $shop_id)
                        ->where('uid', $users)
                        ->where('status', 0)
                        ->where('start', '<=', NOW)
                        ->where('end', '>=', NOW)
                        ->where('limit', '<=', $price);
                }]);
            }])->select();
    }

    public function getCouponByNoProAndPrice($channel, $id, $users, $price, $type, $shop_id = '')
    {
        $sql = 'select c.coupon_id as id,c.shop_id,c.id as `code`,co.name,c.type,c.value,c.limit,c.start,c.end,co.day,co.max_geted,co.intro,co.count,co.geted,c.status,co.id,count(p.id) as totalNum from  coupon_code c 
                Left JOIN  coupon co on co.id=c.coupon_id
                LEFT JOIN  coupon_product p ON p.coupon_id=c.coupon_id 
                WHERE c.`channel`=:channel  AND c.uid=:uid AND c.status=0 AND c.start<=:start AND c.end>=:end AND co.shop_id=:shop_id AND c.`limit`<=' . $price . '
                GROUP BY  c.id ';
        return CouponCode::query($sql, array('channel' => $channel, 'uid' => $users, 'start' => NOW, 'end' => NOW, 'shop_id' => $shop_id));
    }


    public function getCouponByUid($channel, $users)
    {
        $sql = 'SELECT count(p.id) as `num`,c.coupon_id as id,c.shop_id,c.id as `code`,co.name,c.type,c.value,c.limit,c.start,c.end,co.day,co.max_geted,co.intro,co.count,co.geted,c.status FROM  coupon_code c 
                LEFT JOIN coupon co ON co.id=c.coupon_id
                LEFT JOIN coupon_product p ON p.coupon_id=co.id
                WHERE c.`channel`=:channel  AND c.uid=:uid GROUP BY c.id';
        return CouponCode::query($sql, array('channel' => $channel, 'uid' => $users));
    }

    public function getCouponByUidForUser($channel, $users, $status)
    {
        if (!$status) { //可用
            $where = ' AND c.status = ' . $status . ' AND c.`end` > ' . NOW . ' ';
        } else {//不可用
            $where = ' AND ( c.`end` < ' . NOW . ' OR c.status = ' . $status . ' ) ';
        }
        $sql = 'SELECT count(p.id) as `num`,c.coupon_id as id,c.shop_id,c.id as `code`,co.name,c.type,c.value,c.limit,c.start,c.end,co.day,co.max_geted,co.intro,co.count,co.geted,c.status FROM  coupon_code c 
                LEFT JOIN coupon co ON co.id=c.coupon_id
                LEFT JOIN coupon_product p ON p.coupon_id=co.id
                WHERE c.`channel`=:channel ' . $where . ' AND c.uid=:uid GROUP BY c.id ORDER BY c.id DESC';
        return CouponCode::query($sql, array('channel' => $channel, 'uid' => $users));
    }


    public function getProductByCoupon($channel, $id)
    {
        $sql = 'SELECT p.id,p.type,p.shop_id,p.name,p.title,p.price,p.pic,p.bucket 
                FROM product p 
                LEFT JOIN coupon_product cp ON p.id=cp.product_id 
                WHERE cp.coupon_id=:id AND p.channel=:channel AND p.status=1 AND cp.product_type<>1
                ORDER BY cp.`product_id` DESC';
        return Coupon::where('id', $id)->field('id')
            ->where('coupon.channel', $channel)
            ->with(['product' => function ($query) use ($channel) {
                $query->where('product.status', 1)
                    ->where('product.channel', $channel);
            }])
            ->find();
    }

    public function getProductByCouponForRoom($channel, $id)
    {
        $sql = 'SELECT p.id,p.shop_id,p.name,p.feature as title,
                    p.default_price as price,p.cover as pic,p.bucket 
                FROM hotel_room_type p 
                LEFT JOIN coupon_product cp ON p.id=cp.product_id 
                WHERE cp.coupon_id=:id AND p.channel=:channel AND p.status=1 AND cp.product_type=1
                ORDER BY cp.`product_id` DESC';
        return Coupon::where('id', $id)->field('id')
            ->where('coupon.channel', $channel)
            ->with(['hotel_room_type' => function ($query) use ($channel) {
                $query->where('hotel_room_type.status', 1)
                    ->where('hotel_room_type.channel', $channel);
            }])
            ->find()->hotel_room_type;
    }


    public function getCouponByUidAndId($channel, $users, $coupon)
    {
        return CouponCode::where('channel', $channel)
            ->where('uid', $users)
            ->where('coupon_id', $coupon)
            ->with('coupon')
            ->order('id', 'desc')
            ->select();
    }

    public function setCoupon($user, $coupon)
    {
        //用于个人的优惠券生成
        //Db::startTrans();
        try {

            $data = [
                'coupon_id' => $coupon['id'],
                'shop_id' => $coupon['shop_id'],
                'channel' => $coupon['channel'],
                'type' => $coupon['type'],
                'limit' => $coupon['limit'],
                'start' => $coupon['start'],
                'value' => $coupon['value'],
                'end' => $coupon['end'],
                'create_time' => NOW,
                'uid' => $user,
                'exchange_time' => NOW,
            ];


            $order_id = CouponCode::insertGetId($data);
            if (empty($order_id)) {
                throw new \Exception('优惠券 创建失败');
            }

            $res = Coupon::where('id', $coupon['id'])->inc('geted')->update();
            if ($res === false) {
                throw new \Exception('优惠券 更新失败');
            }

            $getCoupon = Coupon::field('count,geted')->where('id', $coupon['id'])->find();
            if ($getCoupon['count'] == $getCoupon['geted']) {
                $res = Coupon::where('id', $coupon['id'])->update(['status' => self::STATUS_DELETE]);
                if ($res === false) {
                    throw new \Exception('优惠券 更新失败!');
                }
            }

            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
    }

    //获取channel所属的单店铺还是多店铺
    public function getChannlGroup($channel)
    {
        return Channel::field('group')->where('id', $channel)->find();
    }

}