<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-16
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\model\Shop;


use app\v4\model\BaseModel;
use think\db\Query;

class HotelBooking extends BaseModel
{
    const STATUS_OPEN = 1; //打开
    const STATUS_CLOSE = 0; //关闭

    public function getDateAttr($value)
    {
        return date('Y-m-d', $value);
    }

    public function getPriceAttr($value,$data)
    {
        return $data['sale_price'];
    }

    public function getStockAttr($value,$data)
    {
        return $data['allot']-$data['used'];
    }

    /**
     * @param $query Query
     * @param $data ProductTicketItem
     */
    public function scopeAvailable($query, $data)
    {
        $query->whereRaw('(`allot`-`used`) >=' . $data->min);

        $query->where('status', '=', self::STATUS_OPEN);

        $query->where('date', '>=', TODAY + ($data->advance_day * 24 * 60 * 60));

        if ($data->end_time) {
            $query->where('date', '>', NOW - (strtotime($data->end_time) - TODAY));
        }

    }
}