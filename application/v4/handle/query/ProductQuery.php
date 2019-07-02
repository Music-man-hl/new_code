<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\v4\handle\query;

use app\v4\model\Main\Shop;
use app\v4\model\Shop\HotelRoomType;

class ProductQuery
{

    const STATUS_OK = 1;//上线  房型状态
    const STATUS_NO = 0;//下线
    const STATUS_DEL = 3;//删除

    const STATUS_SALE_OK = 1;//房态开
    const STATUS_SALE_NO = 0;//房态关

    //获取房型
    function findHotelRoomType($id)
    {
        return  HotelRoomType::where('id', $id)->where('status', '<>', self::STATUS_DEL)->find();
    }

    //获取门店
    function getShopIdAndName($channel, $ids)
    {
        return Shop::field('id,name')->where(['status' => 1, 'channel' => $channel, 'id' => $ids])->select();
    }

}