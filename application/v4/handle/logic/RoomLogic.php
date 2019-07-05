<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\v4\handle\logic;

use app\v4\handle\query\RoomQuery;
use app\v4\model\BaseModel;
use app\v4\model\Shop\HotelBedType;
use app\v4\model\Shop\HotelBooking;
use app\v4\model\Shop\HotelRoomType;
use app\v4\Services\BaseService;

class RoomLogic extends BaseService
{

    private $query;

    function __construct()
    {
        $this->query = new RoomQuery();
    }


    //房型详情
    public function detail($all_param)
    {

        $channel = encrypt($all_param['channel'], 3, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $sub_shop = BaseModel::validSubId($channel);
            if ($sub_shop === false) error(40000, '门店ID错误！');
        } else {
            $sub_shop = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $room_id = encrypt($all_param['room_id'], 6, false);
        $sub_shop = $this->query->getShopIdAndName($channel, $sub_shop);
        if (empty($sub_shop)) error(40000, '没有找到门店');
        $getBedType = HotelBedType::all(); //房型

        if (empty($room_id)) error(40000, '房型ID不能为空');
        $room = $this->query->getRoomInfoById($room_id); //房型信息
        if ($room->isEmpty()) error(40000, '没有找到此房型信息');
        $room = $room[0];
        $getRoomPicture = $this->query->getRoomPicture($room_id); //房型图片
        foreach ($getRoomPicture as $p) {
            $pic[] = picture($p['bucket'], $p['pic']);
        }
        $bed_type = $this->formate_bed_type($getBedType, $room['bed_type']);
        $list = [
            'cover' => $pic[0],//房型封面图URL
            'room_name' => $room['name'],//房型名称
            'shop_name' => $room->shop['name'],//酒店/店铺名称
            'price' => floatval($room['default_price']),//价格
            'area' => $room['room_area'],//房间面积
            'market_price' => $room['market_price'],//划线价
            'floors' => $room['uppermost_floor'],//楼层数
            'limit' => [                          //最多入住
                'adult' => $room['adult_total'],
                'children' => $room['child_total'],
            ],
            'bed_type' => [
                'id' => $room['bed_type'],//房型id
                'name' => $bed_type//大床房等
            ],
            'extra' => [
                'add_bed' => $room['is_add_bed'],//是否可加床
                'smoking' => $room['is_smoke'],//是否可抽烟
                'pet' => $room['is_take_pet'],//是否可以带宠物
            ],
            'detail' => [
                'desc' => $room['feature'],//介绍文案
                'thumb' => $pic,//缩略图URL
            ],
            'buy_min' => $room['min_limit'],
            'buy_max' => $room['max_limit'],

        ];
        success($list);

    }

    //房型
    private function formate_bed_type($bed_type, $id = 0)
    {
        foreach ($bed_type as $item) {
            if ($item['id'] == $id) $list = $item['name'];

        }
        return $list;
    }


    //房型列表
    public function lists($all_param)
    {

        $channel = encrypt($all_param['channel'], 3, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $sub_shop = BaseModel::validSubId($channel);
            if ($sub_shop === false) error(40000, '门店错误！');
        } else {
            $sub_shop = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $page = startLimit($all_param);
        if (!isset($all_param['checkin'])) {
            $listTure = [];
            $data = $this->query->getListsNoCheck($channel, $sub_shop, $page['start'], $page['limit']);
            if (empty($data)) success(['list' => [], 'total_count' => 0]);
            $count = count($data);
            foreach ($data as $k => $value) {
                $listTure[$k]['room_id'] = encrypt($value['id'], 6);
                $listTure[$k]['cover'] = picture($value['bucket'], $value['cover']);
                $listTure[$k]['name'] = $value['name'];
                $listTure[$k]['desc'] = $value['feature'];
                $listTure[$k]['price'] = ceil(floatval($value['price']) * 10) / 10;
                $listTure[$k]['status'] = $value['status'];
                $listTure[$k]['buy_min'] = $value['min_limit'];
                $listTure[$k]['buy_max'] = $value['max_limit'];
                $listTure[$k]['tag'] = $value['tag'] ?: '';
            }
            success(['list' => $listTure, 'total_count' => $count]);
        }


        if (!isset($all_param['checkout'])) $all_param['checkout'] = date('Y-m-d', strtotime('+3 day'));
        $checkin = strtotime($all_param['checkin']);//入住时间
        $checkout = strtotime($all_param['checkout']);//离店时间
        $page = startLimit($all_param);
        $checkinL = strtotime(date('Y-m-d 23:59:59', strtotime($all_param['checkin'])));//入住时间
        $checkoutL = strtotime('-1day', $checkout);
        $tag = isset($all_param['tag']) ? $all_param['tag'] : '';
        $data = $this->query->getLists($channel, $sub_shop, $checkinL, $checkoutL, $tag, $page['start'], $page['limit']);
        if (empty($data)) success(['list' => [], 'total_count' => 0]);
        $count = $this->query->getListsCount($channel, $sub_shop, $checkinL, $checkout);
        $list = [];
        $id = [];
        foreach ($data as $k => $v) {
            $id[] = (int)$v['id'];
            $list[$v['id']] = $v;
        }
        if (!$id) {
            return success(['list' => [], 'total_count' => 0]);
        }

        $id = implode(',', $id);
        $price = $this->query->getListsPrice($id, $checkin, $checkout);
        $orderRoom = $this->query->orderRoom($id, $checkin, $checkout);
        $orderData = array();
        foreach ($orderRoom as $orderTime) {
            if ($orderTime['checkin'] >= $checkin && $orderTime['checkout'] < $checkout) {
                $num = ($orderTime['checkout'] - $orderTime['checkin']) / (86400);
                for ($a = 0; $a < $num; $a++) {
                    $time = $orderTime['checkin'] + $a * 86400;
                    if (!isset($orderData[$orderTime['room_id']][$time])) $orderData[$orderTime['room_id']][$time] = 0;
                    $orderData[$orderTime['room_id']][$time] = $orderData[$orderTime['room_id']][$time] + $orderTime['room_num'];
                }
            }
            if ($orderTime['checkin'] < $checkin && $orderTime['checkout'] > $checkin && $orderTime['checkout'] < $checkout) {
                $num = ($orderTime['checkout'] - $checkin) / (86400);
                for ($a = 0; $a < $num; $a++) {
                    $time = $orderTime['checkin'] + $a * 86400;
                    if (!isset($orderData[$orderTime['room_id']][$time])) $orderData[$orderTime['room_id']][$time] = 0;
                    $orderData[$orderTime['room_id']][$time] = $orderData[$orderTime['room_id']][$time] + $orderTime['room_num'];
                }
            }
            if ($orderTime['checkin'] < $checkout && $orderTime['checkout'] > $checkout && $orderTime['checkin'] > $checkin) {
                $num = ($checkout - $orderTime['checkin']) / (86400);
                for ($a = 0; $a < $num; $a++) {
                    $time = $orderTime['checkin'] + $a * 86400;
                    if (!isset($orderData[$orderTime['room_id']][$time])) $orderData[$orderTime['room_id']][$time] = 0;
                    $orderData[$orderTime['room_id']][$time] = $orderData[$orderTime['room_id']][$time] + $orderTime['room_num'];
                }
            }
            if ($orderTime['checkin'] <= $checkin && $orderTime['checkout'] > $checkout) {
                $num = ($checkout - $orderTime['checkin']) / (86400);
                for ($a = 0; $a < $num; $a++) {
                    $time = $orderTime['checkin'] + $a * 86400;
                    if (!isset($orderData[$orderTime['room_id']][$time])) $orderData[$orderTime['room_id']][$time] = 0;
                    $orderData[$orderTime['room_id']][$time] = $orderData[$orderTime['room_id']][$time] + $orderTime['room_num'];
                }
            }
        }
        $inLists = '';
        foreach ($orderData as $key => $rid) {
            $inLists .= $key . ',';
        }
        $inLists = substr($inLists, 0, -1);
        if (!empty($inLists)) $inLists = ' OR `room` IN(' . $inLists . ') ';
        $status = $this->query->getListsStat($id, $checkin, $checkout, $inLists);
//        var_dump($status);var_dump($orderData);
        foreach ($status as $stat) {
            if (!isset($orderData[$stat['room']][$stat['date']])) $orderData[$stat['room']][$stat['date']] = 0;
            if (($stat['allot'] - $stat['used']) != '0' && $stat['status'] != '0') $stat['allot'] = $stat['allot'] - $orderData[$stat['room']][$stat['date']];
            if (($stat['allot'] - $stat['used']) == '0' && $stat['status'] != '0') $list[$stat['room']]['status'] = 2;
            if ($stat['status'] == '0') $list[$stat['room']]['status'] = 0;

        }
        foreach ($price as $vv) {
            $list[$vv['room']]['price'] = $vv['price'];
        }
        $listTure = [];
        $num = 0;
        foreach ($list as $value) {
            $listTure[$num]['room_id'] = encrypt($value['id'], 6);
            $listTure[$num]['cover'] = picture($value['bucket'], $value['cover']);
            $listTure[$num]['name'] = $value['name'];
            $listTure[$num]['desc'] = $value['feature'];
            $listTure[$num]['price'] = floatval(sprintf("%.1f", $value['price']));
            $listTure[$num]['status'] = isset($value['status']) ? $value['status'] : 1;
            $listTure[$num]['buy_min'] = $value['min_limit'];
            $listTure[$num]['buy_max'] = $value['max_limit'];
            $listTure[$num]['tag'] = $value['tag'] ?: '';
            $num++;
        }
        success(['list' => $listTure, 'total_count' => $count]);

    }

    //日历房列表
    public function calendar($allParam)
    {
        $channel = encrypt($allParam['channel'], 3, false);//渠道id
        if (empty($allParam['sub_shop_id'])) {
            $sub_shop = BaseModel::validSubId($channel);
            if ($sub_shop === false) error(40000, '门店错误！');
        } else {
            $sub_shop = encrypt($allParam['sub_shop_id'], 4, false);//门店id
        }
        if (!isset($allParam['room_id'])) error(40000, '请填写room_id！');
        $room_id = encrypt($allParam['room_id'], 6, false);


        $room = HotelRoomType::where('id', $room_id)->find();

        $min = HotelBooking::field('date')
            ->where('room', $room_id)
            ->min('date', false);

        $max = HotelBooking::field('date')
            ->where('room', $room_id)
            ->max('date');

        $query = HotelBooking::field('date,sale_price,allot,used,status')
            ->where('room', $room_id)
            ->where('status', HotelBooking::STATUS_OPEN)
            ->order('date');

        $page = startLimit($allParam);
        $limit = $page['start'];
        $length = $page['limit'] - 1;

        if (isset($allParam['checkin']) && isset($allParam['checkout'])) {
            $startTime = strtotime($allParam['checkin']);
            $endTime = strtotime($allParam['checkout']) - 1;
        } else {
            $startTime = strtotime(date('Y-m-01', strtotime("+ $limit  month", $min)));
            $endTime = strtotime(date('Y-m-t', strtotime("+ $length month", $startTime)));
        }
        $query->where('date', '>=', $startTime);
        $query->where('date', '<=', $endTime);
        $total_count = ((date('Y', $max) - date('Y', $min)) * 12 + date('m', $max) - date('m', $min) + 1);

        $booking = $query->select()->hidden(['sale_price', 'allot', 'used'])->append(['price', 'stock'])->toArray();
        $roomName = $room['name'];


        success(['room_name' => $roomName, 'list' => $booking, 'total_count' => $total_count ?? 1]);
    }


    public
    function tags($all_param)
    {
        $request = request();
        $channel = encrypt($all_param['channel'], 3, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $sub_shop = BaseModel::validSubId($channel);
            if ($sub_shop === false) error(40000, '门店错误！');
        } else {
            $sub_shop = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }

        $request->checkout = $request->checkout ?: date('Y-m-d', strtotime('+3 day'));
        $checkin = strtotime(date('Y-m-d 23:59:59', strtotime($request->checkin)));//入住时间
        $checkout = strtotime('-1day', strtotime($request->checkout));//离店时间
        $tags = HotelRoomType::where('shop_id', $sub_shop)
            ->where('status', HotelRoomType::STATUS_OK)
            ->where('start', '<=', $checkin)
            ->where('end', '>=', $checkout)
            ->group('tag')
            ->column('tag');
        return success($tags);
    }


}