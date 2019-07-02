<?php
namespace app\index\handle\V1_2_1\model;

use think\Db;
use lib\Error;
use lib\Redis;

/**
 * 门店相关操作
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:29
 */
class ShopModel
{
    const STATUS_OK     = 1; //正常
    const STATUS_DELETE = 0; //删除

    const TEL_SHOP = 1; //门店类型

    const PICTURE_SCROLL = 1; //轮播图
    const PICTURE_BANNER = 2; //导航图
    const PICTURE_COVER  = 3; //封面图
    const PICTURE_AROUND = 4; //周边图片

    const AROUND_DIABLE = 0;  //无效
    const AROUND_ABLE   = 1;  //可用

    //获取风格颜色
    public function getColorStyle($channel)
    {
        $key = "style:" . $channel;
        if (!$color_style = Redis::get($key)) {
            $color_style =  Db::name('channel')->where('id', $channel)->value('color_style');
            Redis::set($key, $color_style, 300);
        }
        return $color_style;
    }

    //获取shop封面
    public function getShopCovers($channel, $shopIds)
    {
        return Db::name('shop_picture')->where('channel', $channel)->where('around_id', 0)->where('type', self::PICTURE_COVER)->whereIn('shop', $shopIds)->select();
    }

    //获取轮播图
    public function getSilderCovers($shopId, $channel)
    {
        return Db::name('shop_picture')->where('channel', $channel)->where('around_id', 0)->where('type', self::PICTURE_SCROLL)->where('shop', $shopId)->select();
    }

    //获取渠道信息
    public function getChannel($channel)
    {
        return Db::name('channel')->where('id', $channel)->find();
    }


    //获取shops
    public function getShopLists($channel, $all_param, &$total)
    {
        $startLimit = startLimit($all_param);

        if (is_numeric($total)) {
            //统计
            $total = Db::name('shop')->where('channel', $channel)->where('status', self::STATUS_OK)->count();
        }

        return Db::name('shop')->where('channel', $channel)->where('status', self::STATUS_OK)->limit($startLimit['start'], $startLimit['limit'])->select();
    }



    //通过sub_id获取店铺详细信息
    public function getShopDetail($id, $channel)
    {
        $sql = 'SELECT s.*,i.`name` as lord,i.`bucket` as lord_bucket,i.`icon` as lord_icon,i.`intro` as intro,t.`tel`,t.`citycode` FROM `shop` s
                LEFT JOIN `shop_intro` i  ON s.`id`=i.`shop_id`
                LEFT JOIN `tels` t ON s.`id` = t.objid
                WHERE s.`id`=:id AND s.`channel`=:channel AND s.`status`=:status';
        $param = ['id'=>$id,'channel'=>$channel,'status'=>self::STATUS_OK];

        return Db::query($sql, $param);
    }

    public function getDistrict($province, $city, $district)
    {
        $sql = 'SELECT * FROM district WHERE id=:province OR id=:city OR id=:district ORDER BY id ASC';
        return Db::query($sql, ['province'=>$province,'city'=>$city,'district'=>$district]);
    }

    //获取标签
    public function getTags($shopId, $channel)
    {
        return Db::name('shop_tag')->where('channel', $channel)->where('shop_id', $shopId)
            ->field('name')->select();
    }


    //获取酒店设施
    public function getFacilities($shopId, $channel, $limit=0)
    {
        if ($limit === 0) {
            $limit='';
        } else {
            $limit = ' LIMIT '.$limit;
        }
        $sql = 'SELECT f.`id`,f.`name` FROM `hotel_facility` hf
                LEFT JOIN `hotel` h  ON hf.`hotel_id`=h.`id`
                LEFT JOIN `facility` f  ON f.`id` = hf.`facility_id`
                WHERE h.`shop_id`=:shop_id AND h.`channel`=:channel '.$limit;
        $param = ['shop_id'=>$shopId,'channel'=>$channel];

        return Db::query($sql, $param);
    }

    public function desc($shopId, $channel)
    {
        return Db::name('shop_intro')->where('channel', $channel)->where('shop_id', $shopId)->select();
    }

    public function getAroundPic($shopId, $channel)
    {
        return Db::name('shop_picture')->where('channel', $channel)->where('around_id', 0)->where('type', self::PICTURE_COVER)->where('shop', $shopId)->select();
    }

    public function getShopByid($shopId, $channel)
    {
        return Db::name('shop')->where('channel', $channel)->where('status', self::STATUS_OK)->where('id', $shopId)
            ->field('name,address')->select();
    }
}
