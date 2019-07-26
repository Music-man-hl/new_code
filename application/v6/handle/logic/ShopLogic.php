<?php

namespace app\v6\handle\logic;

use app\v6\handle\query\ShopQuery;
use app\v6\model\BaseModel;
use app\v6\model\Shop\Hotel;
use app\v6\Services\BaseService;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:28
 */
class ShopLogic extends BaseService
{
    private $query;

    private $successData = ['operation' => 1];

    public function __construct()
    {
        $this->query = new ShopQuery();
    }

    //门店列表
    public function lists($all_param)
    {
        $total = 0;//总数
        $channel = encrypt($all_param['channel'], 3, false);
        $getGroup = $this->query->getChannel($channel);
        if (empty($getGroup)) {
            error(40000, '获取店铺信息错误');
        }
        $getShop = $this->query->getShopLists($channel, $all_param, $total);
        if (empty($getShop)) {
            success(['list' => [], 'shop_type' => '', 'total_count' => '']);
        }
        $shopIds = array_column($getShop->toArray(), 'id');//获取所有的门店

        $getShopCovers = $this->query->getShopCovers($channel, $shopIds);
        $covers = [];  //封面

        if (!empty($getShopCovers)) {
            foreach ($getShopCovers as $getShopCover) {
                $covers[$getShopCover['shop']] = picture($getShopCover['bucket'], $getShopCover['cover']);
            }
        }

        $list = [];
        foreach ($getShop as $v) {
            $list[] = [
                'sub_shop_id' => encrypt($v['id'], 4),
                'sub_shop_name' => $v['name'],
                'cover' => isset($covers[$v['id']]) ? $covers[$v['id']] : '',
            ];
        }
        success([
            'list' => $list,
            'shop_type' => $getGroup['group'],
            'type' => $getGroup['type'],
            'total_count' => $total,
        ]);
    }

    // 获取店铺风格设置
    public function style($all_param)
    {
        $channel = encrypt($all_param['channel'], 3, false);
        $color = $this->query->getColorStyle($channel);
        if ($color) {
            $color = json_decode($color);
        } else {
            $color = ["global_color" => env('color_style_default.global_color')];
        }

        success([
            'color_style' => $color
        ]);
    }

    //子商铺首页内容
    public function index($all_param)
    {
        $shopId = encrypt($all_param['channel'], 3, false);
        if (empty($all_param['sub_shop_id'])) {
            $subShopId = BaseModel::validSubId($shopId);
            if ($subShopId === false) {
                error(40000, '首页门店错误！');
            }
        } else {
            $subShopId = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $getShop = $this->query->getShopDetail($subShopId, $shopId);
        if (empty($getShop)) {
            error(40305);
        }
        $getShopCovers = $this->query->getSilderCovers($subShopId, $shopId);
        $getTags = $this->query->getTags($subShopId, $shopId);
        $getFacilities = $this->query->getFacilities($subShopId, $shopId, 3);
        $covers = [];  //封面
        if (!empty($getShopCovers)) {
            foreach ($getShopCovers as $k => $getShopCover) {
                $covers[$k]['url'] = (string)picture($getShopCover['bucket'], $getShopCover['cover']);
            }
        }
        $tags = []; //标签
        if (!empty($getTags)) {
            foreach ($getTags as $getTag) {
                $tags[] = $getTag['name'];
            }
        }
        $facilities = []; //设施
        if (!empty($getFacilities)) {
            foreach ($getFacilities as $kk => $facilitie) {
                $facilities[$kk]['icon'] = $facilitie['id'];
                $facilities[$kk]['tag'] = $facilitie['name'];
            }
        }
        $getShop = $getShop[0];
        $getDistrict = $this->query->getDistrict($getShop['province'], $getShop['city'], $getShop['district']); //获取省市县
        $district = '';
        foreach ($getDistrict as $v) {
            if ($v['type'] == 2 && !empty($v['citycode'])) {
                continue;
            }
            $district .= $v['name'];
        }
        $location = array(
            'address' => $district . (string)$getShop['address'],
            'label' => $tags,
            'longitude' => $getShop['lng'],
            'latitude' => $getShop['lat']
        );
        $giant = array(
            'avatar' => (string)picture($getShop['shop_intro']['bucket'], $getShop['shop_intro']['icon']),
            'name' => $getShop['name'],
            'desc' => (string)$getShop['shop_intro']['intro'],
        );

        success(['slider' => $covers, 'location' => $location, 'tel' => $getShop->tels['citycode'] . $getShop->tels['tel'], 'facilities' => $facilities, 'giant' => $giant, 'shop_name' => $getShop['name']]);
    }


    public function desc($all_param)
    {
        $shopId = encrypt($all_param['channel'], 3, false);
        if (empty($all_param['sub_shop_id'])) {
            $subShopId = BaseModel::validSubId($shopId);
            if ($subShopId === false) {
                error(40000, '详情门店错误！');
            }
        } else {
            $subShopId = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $desc = $this->query->desc($subShopId, $shopId);
        $pic = $this->query->getAroundPic($subShopId, $shopId);
        $name = $this->query->getShopDetail($subShopId, $shopId);
        $pic = picture($pic[0]['bucket'], $pic[0]['cover']);
        success(['cover' => $pic, 'desc' => $desc[0]['intro'], 'detail' => $desc[0]['content'], 'name' => $name[0]['name']]);
    }

    public function facilities($all_param)
    {
        $shopId = encrypt($all_param['channel'], 3, false);
        if (empty($all_param['sub_shop_id'])) {
            $subShopId = BaseModel::validSubId($shopId);
            if ($subShopId === false) {
                error(40000, '设施门店错误！');
            }
        } else {
            $subShopId = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $getShop = $this->query->getShopByid($subShopId, $shopId);
        $getFacilities = $this->query->getFacilities($subShopId, $shopId);
        $pic = $this->query->getAroundPic($subShopId, $shopId);
        $pic = picture($pic[0]['bucket'], $pic[0]['cover']);
        $facilities = [];
        if (!empty($getFacilities)) {
            foreach ($getFacilities as $kk => $facilitie) {
                $facilities[$kk]['icon'] = $facilitie['id'];
                $facilities[$kk]['tag'] = $facilitie['name'];
            }
        }
        $data = ['cover' => $pic, 'name' => $getShop[0]['name'], 'location' => $getShop[0]['address'], 'list' => $facilities];

        $data += Hotel::getAllTagsByShop($subShopId);
        success($data);
    }

    public function detail($all_param)
    {
        $shopId = encrypt($all_param['channel'], 3, false);
        if (empty($all_param['sub_shop_id'])) {
            $subShopId = BaseModel::validSubId($shopId);
            if ($subShopId === false) {
                error(40000, '设施门店错误！');
            }
        } else {
            $subShopId = encrypt($all_param['sub_shop_id'], 4, false);//门店id
        }
        $getShop = $this->query->getShopByid($subShopId, $shopId);
        $getFacilities = $this->query->getFacilities($subShopId, $shopId);
        $pic = $this->query->getAroundPic($subShopId, $shopId);
        $pic = picture($pic[0]['bucket'], $pic[0]['cover']);
        $facilities = [];
        if (!empty($getFacilities)) {
            foreach ($getFacilities as $kk => $facilitie) {
                $facilities[$kk]['icon'] = $facilitie['id'];
                $facilities[$kk]['tag'] = $facilitie['name'];
            }
        }
        success(['cover' => $pic, 'name' => $getShop[0]['name'], 'location' => $getShop[0]['address'], 'list' => $facilities]);
    }
}
