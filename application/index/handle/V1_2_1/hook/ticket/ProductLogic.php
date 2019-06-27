<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\index\handle\V1_2_1\hook\ticket;

use app\index\model\Product as ProductM;
use app\index\model\ProductTicketBooking;
use app\index\model\ProductTicketItem;
use app\index\model\ProductTicketTag;
use think\db\Query;

class ProductLogic
{
    public static function lists($data)
    {
        $type = $data['type'];
        $channel = [
            'channel_id' => $data['channel'],
            'shop_id' => $data['sub_shop_id']
        ];
        $page = [
            'start' => $data['start'],
            'limit' => $data['limit']
        ];

        $productList = ProductM::where('channel', $channel['channel_id'])
            ->where('shop_id', $channel['shop_id'])
            ->where('status', ProductM::STATUS_VALID)
            ->where('start', '<', NOW)
            ->where('end', '>', NOW)
            ->where('type', $type)
            ->order('update_time', 'desc')
            ->limit($page['start'], $page['limit'])
            ->with(['ticketItems' => function (Query $query) {
                $query->withMin(['booking' => function (Query $query) {
                    $query->where('status', 1)->where('date', '>=', TODAY)
                        ->whereRaw('allot > used')->where('date', '<', TODAY + (30 * 24 * 60 * 60));
                }], 'sale_price');
            }])->select();
        if (!$productList) {
            return [0 => [], 1 => []];
        }
        $tagPid = implode(',', array_column($productList->toArray(), 'id'));
        $tag = ProductTicketTag::where('channel', $channel['channel_id'])->whereIn('pid', $tagPid)->field('pid,name')->select();
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
        $type = $data['type'];
        $channel = [
            'channel_id' => $data['channel'],
            'shop_id' => $data['sub_shop_id']
        ];
        $productId = $data['product_id'];

        $product = ProductM::where('channel', $channel['channel_id'])->where('shop_id', $channel['shop_id'])
            ->where('id', $productId)->where('type', $type)->where('status', ProductM::STATUS_VALID)
            ->with('video,pictures,ticketTags,ticketItems.tags')
            ->with(['ticketItems' => function (Query $query) {
                $query->withMin(['booking' => function (Query $query) {
                    $query->where('status', 1)->where('date', '>=', TODAY)
                        ->whereRaw('allot > used')->where('date', '<', TODAY + (30 * 24 * 60 * 60));
                }], 'sale_price');
            }])->find();
        if (!$product) {
            return error(40002);
        }

        $data = [
            'product_id' => $product['en_id'],
            'name' => $product['name'],
            'bright_point' => $product['title'],
            'price_min' => empty(min($product->ticket_items->column('booking_min'))) ? 0 : floatval(min($product->ticket_items->column('booking_min'))),
            'original_price' => $product['market_price'],
            'tag' => $product->ticketTags,
            'content' => $product->info['content'],//产品详情
            'intro' => $product->info['intro'],//费用包含
            'rule' => $product->info['rule'],
            'product_thumb' => array_column($product->pictures->toArray(), 'pic'),
            'product_video' => ['cover' => $product->video['pic'], 'video' => $product->video['url']],
            'product_type' => $product['type'],
            'product_status' => $product['status'],
            'refund_rule' => $product->info['refund'],
            'is_card' => $product['is_card'],
            'is_refund' => $product['is_refund'],
            'is_invoice' => $product['is_invoice'],
            'is_coupons' => $product['is_coupons'],
            'start' => $product['start'], //上线时间
            'end' => $product['end'],  // 下线时间
            'current_time' => NOW,
        ];
        $item = [];
        foreach ($product->ticket_items as $ticketItem) {
            $item[] = [
                "id" => $ticketItem['en_id'],
                'name' => $ticketItem['name'],
                'status' => $ticketItem['status'],
                'is_docking' => $ticketItem['goods_code'] ? 1 : 0, //是否对接
                'booking_min' => empty($ticketItem['booking_min']) ? 0 : floatval($ticketItem['booking_min']), //预约日期中最低价
                "original_price" => floatval($ticketItem['sale_price']),
                'intro' => $ticketItem['intro'],
                'booking_info' => $ticketItem['booking_info'],
                'end_time' => $ticketItem['end_time'],
                'advance_day' => $ticketItem['advance_day'],
                'buy_min' => $ticketItem['min'],
                'buy_max' => $ticketItem['max'],
                'use_period' => $ticketItem['use_period'], //下单几天内有效
                'use_start' => $ticketItem['use_start'],  //使用开始时间
                'use_end' => $ticketItem['use_end'],    //使用结束时间
                'use_requirements' => $ticketItem['use_requirements'],  //使用要求
                'refund_type' => $ticketItem['refund_type'], //退票类型
                'refund_reason' => $ticketItem['refund_reason'], //退票说明
                'tags' => array_column($ticketItem->tags->toArray(), 'name')
            ];
        }
        $data['ticket_items'] = $item;

        return $data;
    }


    public static function booking_calendar($data)
    {
        $id = encrypt($data['id'], '1', false);
        $item = ProductTicketItem::get($id);

        $min = ProductTicketBooking::field('date')
            ->where('item_id', $id)->available($item)
            ->min('date', false);

        $max = ProductTicketBooking::field('date')
            ->where('item_id', $id)->available($item)
            ->max('date');

        $query = ProductTicketBooking::field('date,sale_price,allot,used,status')
            ->where('item_id', $id)
            ->available($item)
            ->order('date');

        if (isset($data['page'])) {
            $page = startLimit($data);
            $limit = $page['start'];
            $length = $page['limit'] - 1;
            $startTime = strtotime(date('Y-m-01', strtotime("+ $limit  month", $min)));
            $endTime = strtotime(date('Y-m-t', strtotime("+ $length month", $startTime)));
            $query->where('date', '>=', $startTime);
            $query->where('date', '<=', $endTime);
            $total_count = ((date('Y', $max) - date('Y', $min)) * 12 + date('m', $max) - date('m', $min) + 1);
        }

        $booking = $query->select()->toArray();
        //前2个可以预约的时间
        $first_time = $query->where('status', 1)->limit(0, 2)->select();

        foreach ($booking as &$item) {
            $item['allot'] = $item['allot'] - $item['used'];
        }
        $data = [
            'current_date' => date('Y-m-d'),
            'booking_start_date' => date('Y-m-d', $min),
            'booking_end_date' => date('Y-m-d', $max),
            'first_time' => $first_time,
            'total_count' => isset($total_count) ? ($total_count) ? ($total_count) : 1 : 0,
            'list' => $booking
        ];
        return $data;
    }
}
