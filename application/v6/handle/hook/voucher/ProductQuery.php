<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v6\handle\hook\voucher;


use app\v6\model\Shop\OrderVoucher;
use app\v6\model\Shop\Product;
use app\v6\model\Shop\ProductPicture;
use app\v6\model\Shop\ProductVideo;
use app\v6\model\Shop\ProductVoucherBooking;
use app\v6\model\Shop\ProductVoucherItem;
use app\v6\model\Shop\ProductVoucherStandard;
use app\v6\model\Shop\ProductVoucherTag;

class ProductQuery
{
    const STAT_VALID = 1; //有效

    static function getProductByshop($shop_id, $channel, $page, $count, $type)
    {
        return Product::query('select `name`,`title`,`price`,`bucket`,`pic`,`id` from product where `channel`=:channel AND `shop_id`=:shop_id AND `status`=:status AND `start`<=:date AND `end`>:end AND `type`=:type ORDER BY update_time DESC limit ' . $page . ',' . $count, ['channel' => $channel, 'shop_id' => $shop_id, 'date' => NOW, 'end' => NOW, 'status' => self::STAT_VALID, 'type' => $type]
        );
    }


    static function getTagByshop($channel, $tagPid)
    {
        $sql = 'select `pid`,`name` from product_voucher_tag where `channel`=:channel AND `pid` in(';
        foreach ($tagPid as $v) {
            $sql .= $v . ',';
        }
        $sql = substr($sql, 0, -1) . ')';
        return ProductVoucherTag::query($sql, ['channel' => $channel]);
    }

    static function getTagByshopDetail($channel, $pid)
    {
        return ProductVoucherTag::query('select `pid`,`name` from product_voucher_tag where `channel`=:channel AND `pid` in(:pid)', ['channel' => $channel, 'pid' => $pid]);
    }


    static function getProductById($shop_id, $channel, $product_id, $type)
    {
        return Product::query('select p.`name`, p.`allot`, p.`end`, p.`start`,p.`title`,p.`market_price`,p.`price`,p.`bucket`,p.`pic`,p.`id`,p.`min`,p.`max`,p.`is_card`,p.`is_refund`,p.`is_invoice`,p.`is_coupons`,p.`status`,i.`intro`,i.`rule`,i.`refund`,i.`content` from product p  
LEFT JOIN product_info i on p.id=i.id
where p.`id`=:id AND p.`channel`=:channel AND p.`shop_id`=:shop_id AND p.`status`=:status AND  p.`type`=:type', ['id' => $product_id, 'channel' => $channel, 'shop_id' => $shop_id, 'status' => self::STAT_VALID, 'type' => $type]
        );
    }

    static function getStandardByshop($channel, $pid)
    {
        return ProductVoucherStandard::where(['pid' => $pid, 'channel' => $channel])->field('level,title,value,id')->select();
    }

    static function getVideoByshop($channel, $pid)
    {
        return ProductVideo::where(['pid' => $pid, 'channel' => $channel])->field('bucket,pic,video_bucket,url')->find();
    }

    static function getPicByshop($channel, $pid)
    {
        return ProductPicture::where(['pid' => $pid, 'channel' => $channel])->field('bucket,pic')->order('seq ASC')->select();
    }


    static function getVoucherByshop($channel, $pid)
    {
        return ProductVoucherItem::where(['pid' => $pid, 'channel' => $channel])->field('level1,level2,sale_price,allot,id,intro,sales')->select();
    }


    static function getOrderAllotByVoucher($idArr)
    {
        $idArr = explode(',', $idArr);
        $sql = 'select count(v.item_id) as num,v.item_id from order_voucher v 
        LEFT JOIN  `order` o on o.`order`= v.`order`
        where v.item_id in(';
        $count = count($idArr);
        foreach ($idArr as $k => $v) {
            if ($k == ($count - 1)) {
                $sql .= $v;
            } else {
                $sql .= $v . ',';
            }
        }
        $sql .= ') AND o.expire>:expire AND o.status=2 group by v.item_id';
        return OrderVoucher::query($sql, ['expire' => NOW]);
    }

    //获取产品可售卖的月数
    static function getCalendarTotal($channel, $sub_shop, $voucher_id, $time)
    {
        $sql = '  SELECT FROM_UNIXTIME(`date`,\'%Y%m\') months,COUNT(id) COUNT FROM product_voucher_booking  WHERE `channel`=:channel AND `item_id`=:item_id AND `date`>=:time  GROUP BY months ';
        $param = [
            'channel' => $channel,
            'item_id' => $voucher_id,
            'time' => $time,
        ];
        return ProductVoucherBooking::query($sql, $param);
    }


    //根据日前获取预约日历
    static function getCalendar($channel, $sub_shop, $voucher_id, $start, $end)
    {

        $sql = 'SELECT `date`,`allot`-`used` as stock,`status` FROM product_voucher_booking WHERE `channel`=:channel AND `item_id`=:item_id AND `date`>=:start AND `date`<:end ORDER BY `date` ASC ';
        $param = [
            'channel' => $channel,
            'item_id' => $voucher_id,
            'start' => $start,
            'end' => $end,
        ];
        return ProductVoucherBooking::query($sql, $param);
    }

    static function getVoucherById($id)
    {
        $product_id = ProductVoucherItem::where(['id' => $id])->field('pid')->find();
        return Product::where(['id' => $product_id['pid']])->field('booking_end')->find();
    }

    static function getVoucherByOrderId($id)
    {
        return OrderVoucher::where(['id' => $id])->field('item_id')->find();
    }

    // 获取券名称通过ID
    static function getItemNameById($id)
    {
        return ProductVoucherStandard::where('id', $id)->value('value');
    }

}