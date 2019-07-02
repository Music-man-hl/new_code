<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\index\handle\V1_2_1\hook\voucher;


use think\Db;
use lib\Error;
use third\S;
use lib\Status;
use lib\Redis;

class ProductModel
{
    const STAT_VALID     = 1; //有效

    static function getProductByshop($shop_id,$channel,$page,$count,$type)
    {
        return Db::query('select `name`,`title`,`price`,`bucket`,`pic`,`id` from product where `channel`=:channel AND `shop_id`=:shop_id AND `status`=:status AND `start`<=:date AND `end`>:end AND `type`=:type ORDER BY update_time DESC limit '.$page.','.$count,['channel'=>$channel,'shop_id'=>$shop_id,'date'=>NOW,'end'=>NOW,'status'=>self::STAT_VALID,'type'=>$type]
        );
    }


    static function getTagByshop($channel,$tagPid)
    {
        $sql = 'select `pid`,`name` from product_voucher_tag where `channel`=:channel AND `pid` in(';
        foreach ($tagPid as $v)
        {
            $sql .= $v.',';
        }
        $sql = substr($sql,0,-1).')';
        return Db::query($sql,['channel'=>$channel]);
    }

    static function getTagByshopDetail($channel,$pid)
    {
        return Db::query('select `pid`,`name` from product_voucher_tag where `channel`=:channel AND `pid` in(:pid)',['channel'=>$channel,'pid'=>$pid]);
    }


    static function getProductById($shop_id,$channel,$product_id,$type)
    {
        return Db::query('select p.`name`, p.`allot`, p.`end`, p.`start`,p.`title`,p.`market_price`,p.`price`,p.`bucket`,p.`pic`,p.`id`,p.`min`,p.`max`,p.`is_card`,p.`is_refund`,p.`is_invoice`,p.`is_coupons`,p.`status`,i.`intro`,i.`rule`,i.`refund`,i.`content` from product p  
LEFT JOIN product_info i on p.id=i.id
where p.`id`=:id AND p.`channel`=:channel AND p.`shop_id`=:shop_id AND p.`status`=:status AND  p.`type`=:type',['id'=>$product_id,'channel'=>$channel,'shop_id'=>$shop_id,'status'=>self::STAT_VALID,'type'=>$type]
        );
    }

    static function getStandardByshop($channel,$pid)
    {
        return Db::table('product_voucher_standard')->where(['pid'=>$pid,'channel'=>$channel])->field('level,title,value,id')->select();
    }

    static function getVideoByshop($channel,$pid)
    {
        return Db::table('product_video')->where(['pid'=>$pid,'channel'=>$channel])->field('bucket,pic,video_bucket,url')->find();
    }

    static function getPicByshop($channel,$pid)
    {
        return Db::table('product_picture')->where(['pid'=>$pid,'channel'=>$channel])->field('bucket,pic')->order('seq ASC')->select();
    }


    static function getVoucherByshop($channel,$pid)
    {
        return Db::table('product_voucher_item')->where(['pid'=>$pid,'channel'=>$channel])->field('level1,level2,sale_price,allot,id,intro,sales')->select();
    }



    static function getOrderAllotByVoucher($idArr)
    {
        $idArr = explode(',',$idArr);
        $sql = 'select count(v.item_id) as num,v.item_id from order_voucher v 
        LEFT JOIN  `order` o on o.`order`= v.`order`
        where v.item_id in(';
        $count  =   count($idArr);
        foreach ($idArr as $k=>$v)
        {
            if($k==($count-1))
            {
                $sql .= $v;
            }
            else
            {
                $sql .= $v.',';
            }
        }
        $sql .= ') AND o.expire>:expire AND o.status=2 group by v.item_id' ;
        return Db::query($sql,['expire'=>NOW]);
    }

    //获取产品可售卖的月数
    static function getCalendarTotal($channel,$sub_shop,$voucher_id,$time)
    {
        $sql = '  SELECT FROM_UNIXTIME(`date`,\'%Y%m\') months,COUNT(id) COUNT FROM product_voucher_booking  WHERE `channel`=:channel AND `item_id`=:item_id AND `date`>=:time  GROUP BY months ';
        $param  = [
            'channel'       =>  $channel,
            'item_id'       =>  $voucher_id,
            'time'          =>  $time,
        ];
        return Db::query($sql,$param);
    }


    //根据日前获取预约日历
    static  function getCalendar($channel,$sub_shop,$voucher_id,$start,$end){

        $sql    = 'SELECT `date`,`allot`-`used` as stock,`status` FROM product_voucher_booking WHERE `channel`=:channel AND `item_id`=:item_id AND `date`>=:start AND `date`<:end ORDER BY `date` ASC ';
        $param  = [
            'channel'       =>$channel,
            'item_id'       =>$voucher_id,
            'start'         =>$start,
            'end'           =>$end,
        ];
        return Db::query($sql,$param);
    }

    static function getVoucherById($id)
    {
        $product_id     =  Db::table('product_voucher_item')->where(['id'=>$id])->field('pid')->find();
        return   Db::table('product')->where(['id'=>$product_id['pid']])->field('booking_end')->find();
    }

    static function getVoucherByOrderId($id)
    {
        return   Db::table('order_voucher')->where(['id'=>$id])->field('item_id')->find();
    }

    // 获取券名称通过ID
    static function getItemNameById($id)
    {
        return Db::table('product_voucher_standard')->where('id',$id)->value('value');
    }

}