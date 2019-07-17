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
}
