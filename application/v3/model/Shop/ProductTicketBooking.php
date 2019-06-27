<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-08
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;
use think\db\Query;

class ProductTicketBooking extends BaseModel
{

    const STATUS_OPEN = 1; //打开
    const STATUS_CLOSE = 0; //关闭

    public function getDateAttr($value)
    {
        return date('Y-m-d', $value);
    }

    public function getStatusAttr($value, $data)
    {
        if ($data['allot'] <= $data['used']) {
            return self::STATUS_CLOSE;
        } else {
            return $value;
        }
    }

    public function getStockAttr($value, $data)
    {
        return $data['allot'] - $data['used'];
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
//                $query->where('date', '>', NOW + strtotime(date('Y-m-d', strtotime('+1 day'))) - strtotime($data->end_time));
            $query->where('date', '>', NOW - (strtotime($data->end_time) - TODAY));
        }

    }
}
