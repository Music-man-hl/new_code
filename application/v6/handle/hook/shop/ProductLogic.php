<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v6\handle\hook\shop;

use app\v6\model\Shop\Product;
use app\v6\model\Shop\ProductTicketBooking;
use app\v6\model\Shop\ProductTicketItem;
use app\v6\model\Shop\ProductTicketTag;
use think\db\Query;

class ProductLogic
{
    public static function lists($data)
    {
        $shop_id = $data['sub_shop_id'];
        $channel = $data['channel'];
        $page = $data['start'];
        $count = $data['limit'];
        $type = $data['type'];
        $productList = ProductQuery::getProductByshop($shop_id, $channel, $page, $count, $type);
        if (empty($productList)) {
            return [0 => [], 1 => []];
        }

        $tagPid = [];
        foreach ($productList as $v) {
            $tagPid[] = $v['id'];
        }
        $tag = ProductQuery::getTagByshop($channel, $tagPid);
        $tagArr = [];
        foreach ($tag as $t) {
            $tagArr[$t['pid']][] = $t['name'];
        }
        $list[0] = $productList;
        $list[1] = $tagArr;
        return $list;

    }

    public static function detail($data)
    {
        $shop_id = $data['sub_shop_id'];
        $channel = $data['channel'];
        $product_id = $data['product_id'];
        $type = $data['type'];
        $product = ProductQuery::getProductById($shop_id, $channel, $product_id, $type);
        if (empty($product)) {
            error(40002);
        }
        $product = $product[0];
        $tag = ProductQuery::getTagByshopDetail($channel, $product['id']);
        $standard = ProductQuery::getStandardByshop($channel, $product['id']);
        $video = ProductQuery::getVideoByshop($channel, $product['id']);
        $pic = ProductQuery::getPicByshop($channel, $product['id']);
        $retail = ProductQuery::getRetailByshop($channel, $product['id']);
        $transport = ProductQuery::getRetailExt($product['id']);
        $address = [];
        if (!empty($transport['address_id'])) {
            $address = ProductQuery::getAddress($transport['address_id']);
            if ($address) {
                $address['province_name'] = ProductQuery::getAdName($address['province']);
                $address['city_name'] = ProductQuery::getAdName($address['city']);
                $address['district_name'] = ProductQuery::getAdName($address['county']);
                $address['province_adcode'] = ProductQuery::getAdCode($address['province']);
                $address['city_adcode'] = ProductQuery::getAdCode($address['city']);
                $address['county_adcode'] = ProductQuery::getAdCode($address['county']);
            }
        }

        $tagArr = [];
        foreach ($tag as $t) {
            $tagArr[] = $t['name'];
        }

        $standardArr = [];
        foreach ($standard as $ttt) {
            if ($ttt['level'] == '1') {
                $title1 = $ttt['title'];
            }
            if ($ttt['level'] == '2') {
                $title2 = $ttt['title'];
            }
            if ($ttt['level'] == '1') {
                $arr1[] = [
                    'id' => $ttt['id'],
                    'name' => $ttt['value']
                ];
            }
            if ($ttt['level'] == '2') {
                $arr2[] = [
                    'id' => $ttt['id'],
                    'name' => $ttt['value']
                ];
            }
        }
        if (!isset($title1)) {
            error(40000, '规格不存在！');
        }
        $standardArr[] = [
            'title' => $title1,
            'value' => $arr1,
        ];
        if (isset($arr2)) {
            $standardArr[] = [
                'title' => $title2,
                'value' => $arr2,
            ];
        }

        $retailArr = [];
        $idArr = '';
        foreach ($retail as $id) {
            $idArr .= ',' . $id['id'];
        }
        $idArr = substr($idArr, 1);
        $allot = ProductQuery::getOrderAllotByRetail($idArr);
        $allotArr = [];
        foreach ($allot as $v) {
            $allotArr[$v['item_id']] = $v['num'];
        }
        foreach ($retail as $tttt) {
            if (empty($tttt['level2'])) {
                $level = [$tttt['level1']];
            } else {
                $level = [$tttt['level1'], $tttt['level2']];
            }
            if (isset($allotArr[$tttt['id']])) {
                $num = $allotArr[$tttt['id']];
            } else {
                $num = 0;
            }
            $total = $tttt['allot'] - $num - $tttt['sales'];
            $retailArr[] = [
                'level' => $level,
                'retail_price' => $tttt['sale_price'],
                'retail_stock' => $total > 0 ? $total : 0,
                'id' => encrypt($tttt['id'], 1),
                'desc' => $tttt['intro'],
            ];
        }

        if ($product['end'] < NOW) {
            $product['status'] = -1;
        }//已过期
        elseif ($product['start'] > NOW) {
            $product['status'] = -2;
        }//即将出售
        elseif ($product['allot'] <= 0) {
            $product['status'] = -3;
        }//已售罄

        $list = [
            'product_id' => encrypt($product['id'], 1),
            'name' => $product['name'],
            'bright_point' => $product['title'],
            'price' => $product['price'],
            'original_price' => $product['market_price'],
            'tag' => $tagArr,
            'desc' => $product['content'],
            'contain' => $product['intro'],
            'usage' => $product['rule'],
            'product_thumb' => $pic->column('pic'),
            'product_video' => ['cover' => $video['pic'], 'video' => $video['url']],
            'standard' => $standardArr,
            'product_type' => 4,
            'retail' => $retailArr,
            'product_status' => $product['status'],
            'refund_rule' => $product['refund'],
            'min' => $product['min'],
            'max' => $product['max'],
            'is_card' => $product['is_card'],
            'is_refund' => $product['is_refund'],
            'is_invoice' => $product['is_invoice'],
            'is_coupons' => $product['is_coupons'],
            'is_self_mention' => $transport['is_self_mention'],
            'is_transport' => $transport['is_transport'],
            'transport_fee_type' => $transport['transport_fee_type'],
            'transport_fee' => $transport['transport_fee'],
            'explain' => $transport['explain'],
            'address' => $address
        ];
        return $list;
    }
}
