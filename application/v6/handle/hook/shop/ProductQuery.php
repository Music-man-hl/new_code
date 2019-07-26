<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v6\handle\hook\shop;


use app\v6\model\Main\District;
use app\v6\model\Shop\OrderRetail;
use app\v6\model\Shop\Product;
use app\v6\model\Shop\ProductPicture;
use app\v6\model\Shop\ProductRetailAddress;
use app\v6\model\Shop\ProductRetailExt;
use app\v6\model\Shop\ProductRetailItem;
use app\v6\model\Shop\ProductRetailStandard;
use app\v6\model\Shop\ProductRetailTag;
use app\v6\model\Shop\ProductVideo;


class ProductQuery
{
    const STAT_VALID = 1; //有效

    static function getProductByshop($shop_id, $channel, $page, $count, $type)
    {
        return Product::query('select `name`,`title`,`price`,`bucket`,`pic`,`id` from product where `channel`=:channel AND `shop_id`=:shop_id AND `status`=:status AND `start`<=:date AND `end`>:end AND `type`=:type ORDER BY update_time DESC limit ' . $page . ',' . $count, ['channel' => $channel, 'shop_id' => $shop_id, 'date' => NOW, 'end' => NOW, 'status' => self::STAT_VALID, 'type' => $type]);
    }

    static function getTagByshop($channel, $tagPid)
    {
        $sql = 'select `pid`,`name` from product_retail_tag where `channel`=:channel AND `pid` in(';
        foreach ($tagPid as $v) {
            $sql .= $v . ',';
        }
        $sql = substr($sql, 0, -1) . ')';
        return ProductRetailTag::query($sql, ['channel' => $channel]);
    }

    static function getTagByshopDetail($channel, $pid)
    {
        return ProductRetailTag::query('select `pid`,`name` from product_retail_tag where `channel`=:channel AND `pid` in(:pid)', ['channel' => $channel, 'pid' => $pid]);
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
        return ProductRetailStandard::where(['pid' => $pid, 'channel' => $channel])->field('level,title,value,id')->select();
    }

    static function getVideoByshop($channel, $pid)
    {
        return ProductVideo::where(['pid' => $pid, 'channel' => $channel])->field('bucket,pic,video_bucket,url')->find();
    }

    static function getPicByshop($channel, $pid)
    {
        return ProductPicture::where(['pid' => $pid, 'channel' => $channel])->field('bucket,pic')->order('seq ASC')->select();
    }


    static function getRetailByshop($channel, $pid)
    {
        return ProductRetailItem::where(['pid' => $pid, 'channel' => $channel])->field('level1,level2,sale_price,allot,id,intro,sales')->select();
    }

    static function getOrderAllotByRetail($idArr)
    {
        $idArr = explode(',', $idArr);
        $sql = 'select o.count as num,r.product_item_id as item_id 
        from order_retail r 
        LEFT JOIN  `order` o on o.`order`= r.`order`
        where r.product_item_id in(';
        $count = count($idArr);
        foreach ($idArr as $k => $v) {
            if ($k == ($count - 1)) {
                $sql .= $v;
            } else {
                $sql .= $v . ',';
            }
        }
        $sql .= ') AND o.expire>:expire AND o.status=2 group by r.product_item_id';
        return OrderRetail::query($sql, ['expire' => NOW]);
    }

    static public function getRetailExt($pid)
    {
        return ProductRetailExt::field('is_self_mention,address_id,is_transport,transport_fee_type,transport_fee,explain')
            ->where(["pid" => $pid])->find();
    }

    static public function getAddress($id)
    {
        return ProductRetailAddress::field("id,lng,lat,province,city,county,address,contacts,mobile")->where(["id" => $id])->find();
    }

    static public function getAdName($id)
    {
        return District::where(["id" => $id])->value("name");
    }

    static public function getAdCode($id)
    {
        return District::where(["id" => $id])->value("adcode");
    }
}