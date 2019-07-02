<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class Shop extends Model
{
    public function getChannel()
    {
        return $this->belongsTo('Channel', 'channel', 'id');
    }

    public function pictures()
    {
        return $this->hasMany('ShopPicture', 'shop', 'id');
    }
}