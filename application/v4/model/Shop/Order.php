<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\model\Shop;


use app\v4\model\BaseModel;

class Order extends BaseModel
{

    const APPLET = 1;//微信小程序

    public function userInfo()
    {
        return $this->belongsTo(UserInfo::class,'uid','user');
    }

    public function ext()
    {
        return $this->hasOne(OrderExt::class, 'order_id', 'id');
    }

    public function ticket()
    {
        return $this->hasMany(OrderTicket::class, 'order_id', 'id');
    }

    public function info()
    {
        return $this->hasOne(OrderInfo::class, 'order_id', 'id');
    }

    public function extension()
    {
        return $this->hasOne(DistributionOrder::class, 'order', 'order');
    }
}