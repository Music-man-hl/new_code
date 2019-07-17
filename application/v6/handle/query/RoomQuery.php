<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\v6\handle\query;

use app\v6\model\Main\Shop;
use app\v6\model\Shop\HotelBooking;
use app\v6\model\Shop\HotelRoomType;
use app\v6\model\Shop\OrderHotelCalendar;
use app\v6\model\Shop\RoomPicture;

class RoomQuery
{

    const STATUS_OK = 1;//上线  房型状态
    const STATUS_NO = 0;//下线
    const STATUS_DEL = 3;//删除

    const STATUS_SALE_OK = 1;//房态开
    const STATUS_SALE_NO = 0;//房态关

    //获取门店
    function getShopIdAndName($channel, $ids)
    {
        return Shop::field('id,name')->where(['status' => 1, 'channel' => $channel, 'id' => $ids])->select();
    }

    //获取房型信息
    function getRoomInfoById($id)
    {
        return HotelRoomType::where('id',$id)
            ->where('status',self::STATUS_OK)
            ->with('shop')
            ->select();
    }

    //获取房型图片
    function getRoomPicture($room_type_id)
    {
        return RoomPicture::where('room_type_id', $room_type_id)->order('seq')->field('pic,bucket')->select();
    }

    //获取房型平均价格
    function getListsPrice($room, $sTime, $eTime)
    {
        $sql = 'SELECT `room`,AVG(`sale_price`) as price,SUM(`allot`) as num FROM hotel_booking WHERE `room` IN(' . $room . ') AND `date`>=:stime AND `date`<:etime GROUP BY `room`';
        $param = ['stime' => $sTime, 'etime' => $eTime];

        return HotelBooking::query($sql, $param);
    }

    //获取订单中未支付的房型
    function orderRoom($room, $sTime, $eTime)
    {
        $sql = 'SELECT `room_num`,`checkin`,`checkout`,`room_id` FROM order_hotel_calendar WHERE `room_id` IN(' . $room . ')  AND ((`checkin`>=:stime AND `checkout`<:etime) OR (`checkin`<=:stime1 AND `checkout`>:stime11 AND `checkout`<:etime111) OR (`checkin`<=:etime2 AND `checkout`>:etime22 AND `checkin`>=:stime222) OR (`checkin`<:stime3 AND `checkout`>:etime3)) AND `order_status`=2 AND `create`>=:time';
        $param = ['stime' => $sTime, 'etime' => $eTime, 'stime1' => $sTime, 'stime11' => $sTime, 'etime2' => $eTime, 'etime22' => $eTime, 'stime3' => $sTime, 'etime3' => $eTime, 'etime111' => $eTime, 'stime222' => $sTime, 'time' => NOW - 60 * 30];
        return OrderHotelCalendar::query($sql, $param);
    }


    //获取状态异常房型
    function getListsStat($room, $sTime, $eTime, $inLists)
    {
        $sql = 'SELECT `room`,`allot`,`status`,`used`,`date` FROM hotel_booking b
                WHERE `room` IN(' . $room . ') AND `date`>=:stime AND `date`<:etime AND (`status`=0 or (`allot`-`used`)=0 OR `allot`=0  ' . $inLists . ')';
        $param = ['stime' => $sTime, 'etime' => $eTime];
        return HotelBooking::query($sql, $param);
    }

    //获取列表
    function getLists($channel, $sub_shop, $checkin, $checkout, $tag, $page, $count)
    {

        $query = HotelRoomType::field('id,name,feature,bucket,cover,min_limit,max_limit,tag')
            ->where(['channel' => $channel, 'shop_id' => $sub_shop, 'status' => [self::STATUS_OK]])
            ->where('start', '<=', $checkin)
            ->where('end', '>=', $checkout)
            ->order('update_time desc')
            ->limit($page, $count);
        if ($tag) {
            $query->where('tag', $tag);
        }
        return $query->select();

    }


    //没有checkin的情况下，获取列表
    function getListsNoCheck($channel, $sub_shop, $page, $count)
    {
        return HotelRoomType::field('id,name,feature,bucket,cover,default_price as price,status,min_limit,max_limit,tag')
            ->where(['channel' => $channel, 'shop_id' => $sub_shop, 'status' => [self::STATUS_OK]])
            ->order('update_time desc')
            ->limit($page, $count)
            ->select();
    }


    function getListsCount($channel, $sub_shop, $checkin, $checkout)
    {
        return HotelRoomType::field('id,name,feature,bucket,cover')
            ->where(['channel' => $channel, 'shop_id' => $sub_shop, 'status' => [self::STATUS_OK]])
            ->where('start', '<=', $checkin)
            ->where('end', '>', $checkout)
            ->order('update_time desc')
            ->count();
    }

    //根据日前获取预约日历
    function getCalendar($channel, $sub_shop, $room_id, $start, $end)
    {

        $sql = 'SELECT `date`,`price`,`sale_price`,`allot`-`used` as allot,`status` FROM hotel_booking WHERE `channel`=:channel AND `room`=:room AND `date`>=:start AND `date`<:end';
        $param = [
            'channel' => $channel,
            'room' => $room_id,
            'start' => $start,
            'end' => $end,
        ];
        return HotelBooking::query($sql, $param);
    }

    //获取产品可售卖的月数
    function getCalendarTotal($channel, $sub_shop, $room_id, $time)
    {
        $sql = '  SELECT FROM_UNIXTIME(`date`,\'%Y%m\') months,COUNT(id) COUNT FROM hotel_booking  WHERE `channel`=:channel AND `room`=:room AND `date`>=:time  GROUP BY months ';
        $param = [
            'channel' => $channel,
            'room' => $room_id,
            'time' => $time,
        ];
        return HotelBooking::query($sql, $param);
    }
}