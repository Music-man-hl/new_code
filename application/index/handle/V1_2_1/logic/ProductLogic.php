<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\index\handle\V1_2_1\logic;

use lib\Upload;
use app\common\model\Room;

class ProductLogic
{

    private $handle;
    private $api_version;//设置版本，随时可以更新



    function __construct( $api_version )
    {
        $this->api_version = $api_version;
        $model_path = $api_version . "model\ProductModel"; //用户模型
        $this->handle = new $model_path();
    }



    //产品详情
    public function detail($all_param)
    {

        $channelId = encrypt($all_param['channel'], 3, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $shopId = Room::validSubId($channelId);
            if ($shopId === false) error(40000, '门店错误！');
        } else {
            $shopId = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $sub_shop = $this->handle->getShopIdAndName($channelId, $shopId);
        if (empty($sub_shop)) error(40000, '没有找到门店');
        if (!in_array($all_param['type'], [2, 5])) {
            error(40000, '参数不正确!');
        }

        $data = $all_param;
        $data['sub_shop_id'] = $sub_shop[0]['id'];
        $data['product_id'] = encrypt($all_param['id'], 1, false);
        $data['type'] = $all_param['type'];
        $data['channel'] = $channelId;//门店id

        $classes = $this->api_version . "hook\ProductInit";
        $list = $classes::factory($all_param['type'])->apply('detail', $data);

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


    //产品列表
    public function lists($all_param)
    {
        $channelId = encrypt($all_param['channel'], 3, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $shopId = Room::validSubId($channelId);
            if ($shopId === false) error(40000, '门店错误！');
        } else {
            $shopId = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $page = startLimit($all_param);

        $data = $all_param;
        $data['sub_shop_id'] = $shopId;
        $data['start'] = $page['start'];
        $data['limit'] = $page['limit'];
        $data['type'] = $all_param['type'];
        $data['channel'] = $channelId;//门店id

        $classes = $this->api_version . "hook\ProductInit";
        $list = $classes::factory($all_param['type'])->apply('lists', $data);
        $productList = $list[0];
        $tagArr = $list[1];
        $list = [];
        foreach ($productList as $v) {
            $list[] = [
                'product_id' => encrypt($v['id'], 1),
                'name' => $v['name'],
                'cover' => picture($v['bucket'], $v['pic']),
                'desc' => $v['title'],
                'price' => ceil(floatval($v['price']) * 10) / 10,
                'ticket_items_min' => isset($v->ticket_items) ? floatval(min($v->ticket_items->column('booking_min'))) ?: 0 : 0,
                'tags' => isset($tagArr[$v['id']]) ? $tagArr[$v['id']] : [],
            ];
        }
        success(['list' => $list, 'total_count' => count($list)]);

    }

    //券日历列表
    public function booking_calendar($all_param)
    {
        $channel = encrypt($all_param['channel'], 3, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $sub_shop = Room::validSubId($channel);
            if ($sub_shop === false) error(40000, '门店错误！');
        } else {
            $sub_shop = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }

        if (isset($all_param['type'])) {
            $all_param['sub_shop'] = $sub_shop;
            $all_param['channel'] = $channel;
            $classes = $this->api_version . "hook\ProductInit";
            $list = $classes::factory($all_param['type'])->apply('booking_calendar', $all_param);
            success($list);
        } else {
            success();
        }

    }


}