<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v3\handle\hook\voucher;

use lib\MyLog;

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
        MyLog::info(json_encode($data));
        $product = ProductQuery::getProductById($shop_id, $channel, $product_id, $type);
        if (empty($product)) {
            error(40002);
        }
        $product = $product[0];
        $tag = ProductQuery::getTagByshopDetail($channel, $product['id']);
        $standard = ProductQuery::getStandardByshop($channel, $product['id']);
        $video = ProductQuery::getVideoByshop($channel, $product['id']);
        $pic = ProductQuery::getPicByshop($channel, $product['id']);
        $voucher = ProductQuery::getVoucherByshop($channel, $product['id']);

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

        $voucherArr = [];
        $idArr = '';
        foreach ($voucher as $id) {
            $idArr .= ',' . $id['id'];
        }
        $idArr = substr($idArr, 1);
        $allot = ProductQuery::getOrderAllotByVoucher($idArr);
        $allotArr = [];
        foreach ($allot as $v) {
            $allotArr[$v['item_id']] = $v['num'];
        }
        foreach ($voucher as $tttt) {
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
            $voucherArr[] = [
                'level' => $level,
                'voucher_price' => $tttt['sale_price'],
                'voucher_stock' => $total > 0 ? $total : 0,
                'id' => encrypt($tttt['id'], 2),
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
            'product_video' => ['cover' =>$video['pic'], 'video' => $video['url']],
            'standard' => $standardArr,
            'product_type' => 5,
            'voucher' => $voucherArr,
            'product_status' => $product['status'],
            'refund_rule' => $product['refund'],
            'min' => $product['min'],
            'max' => $product['max'],
            'is_card' => $product['is_card'],
            'is_refund' => $product['is_refund'],
            'is_invoice' => $product['is_invoice'],
            'is_coupons' => $product['is_coupons'],
        ];
        return $list;
    }


    public static function booking_calendar($data)
    {
        $voucher = ProductQuery::getVoucherByOrderId($data['id']);
        $voucher_id = $voucher['item_id'];
        $sub_shop = $data['sub_shop'];
        $channel = $data['channel'];
        $total = ProductQuery::getCalendarTotal($channel, $sub_shop, $voucher_id, strtotime(date('Y-m-d', NOW)));

        if (empty($total)) {
            $total = 1;
        } else {
            $cou = count($total);
            $start_year = substr($total[0]['months'], 0, 4);
            $start_date = substr($total[0]['months'], 4, 2);
            $end_year = substr($total[$cou - 1]['months'], 0, 4);
            $end_date = substr($total[$cou - 1]['months'], 4, 2);
            $total = ($end_year - $start_year) * 12 + $end_date - $start_date + 1;
        }
        $page = isset($data['page']) ? $data['page'] : 1;
        $count = isset($data['count']) ? $data['count'] : 2;
        $now = strtotime(date('Y-m-d', NOW));
        $start = strtotime(date('Y-m-01 00:00:00', strtotime('+' . ($page * $count - $count) . ' month')));
        $end = strtotime('+' . $count . ' month', $start);
        $data = ProductQuery::getCalendar($channel, $sub_shop, $voucher_id, $start, $end);
        $dataEx = [];
        if (!isset($data[0])) {
            $list = [];
            for ($i = $start; $i < $end; $i = $i + 86400) {
                $list[] = [
                    'stock' => 0,
                    'date' => $i,
                    'status' => 0,
                ];
            }
        } else {
            $lastTime = $start;
            $n = 0;
            foreach ($data as $n => $d) {
                if (($d['date'] - $lastTime) > 0) {
                    $nn = ($d['date'] - $lastTime) / 86400;
                    if ($lastTime === $start && $n == 0 && $d['date'] != $lastTime) {
                        $dataEx[] = [
                            'stock' => 0,
                            'date' => $start,
                            'status' => 0,
                        ];
                    }
                    for ($i = $nn; $i > 1; $i--) {
                        $dataEx[] = [
                            'stock' => 0,
                            'date' => $lastTime + ($i - 1) * 86400,
                            'status' => 0,
                        ];
                    }
                }
                if ($d['date'] < $now) {
                    $data[$n]['status'] = 0;
                }
                $lastTime = $d['date'];
                $n++;
            }
            if (($end - $d['date']) > 0) {
                $n = ($end - $d['date']) / 86400;
                for ($i = $n; $i > 1; $i--) {
                    $dataEx[] = [
                        'stock' => 0,
                        'date' => $lastTime + ($i - 1) * 86400,
                        'status' => 0,
                    ];
                }
            }
            $list = array_merge($dataEx, $data);
            foreach ($list as $key => $row) {
                $volume[$key] = $row['date'];
            }


            array_multisort($volume, SORT_ASC, $list);
        }

        $item_data = ProductQuery::getVoucherById($voucher_id);
        $list = [
            'total' => $total / $count >= 1 ? ceil($total / $count) : 1,
            'current_time' => NOW,
            'end_time' => $item_data['booking_end'],
            'validTime' => $list,
        ];
        return $list;
    }
}
