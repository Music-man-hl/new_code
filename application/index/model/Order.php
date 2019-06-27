<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class Order extends Model
{

    const APPLET = 1;//微信小程序

    public function ext()
    {
        return $this->hasOne(OrderExt::class, 'order_id', 'id');
    }

    public function ticket()
    {
        return $this->hasMany(OrderTicket::class, 'order_id', 'id');
    }
}