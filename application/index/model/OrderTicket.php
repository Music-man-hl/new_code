<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class OrderTicket extends Model
{
    const APPLET = 1; //终端类型小程序

    protected $json = ['people'];

    //30分钟内未支付订单数
    public static function lockCount($ticketId)
    {
        return self::alias('t')
            ->field('t.id')
            ->rightJoin('order o', 't.order=o.order')
            ->where('item_id', $ticketId)
            ->where('o.expire', '>', NOW)
            ->where('o.status', 2)
            ->count();
    }

}