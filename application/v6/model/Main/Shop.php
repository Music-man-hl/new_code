<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Main;


use app\v6\model\BaseModel;
use app\v6\model\Shop\ShopIntro;
use app\v6\model\Shop\ShopPicture;
use app\v6\model\Shop\Tels;

class Shop extends BaseModel
{
    protected $connection = 'dms_main';

    public function getChannel()
    {
        return $this->belongsTo(Channel::class, 'channel', 'id');
    }

    public function pictures()
    {
        return $this->hasMany(ShopPicture::class, 'shop', 'id');
    }

    public function shopIntro()
    {
        return $this->hasOne(ShopIntro::class, 'shop_id', 'id');
    }

    public function tels()
    {
        return $this->hasOne(Tels::class,'objid','id');
    }
}