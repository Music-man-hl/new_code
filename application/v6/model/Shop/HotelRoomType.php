<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-07
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Shop;


use app\v6\model\BaseModel;
use app\v6\model\Main\Shop;

class HotelRoomType extends BaseModel
{
    const STATUS_OK  = 1;//上线  房型状态
    const STATUS_NO  = 0;//下线
    const STATUS_DEL = 3;//删除

    const STATUS_SALE_OK = 1;//房态开
    const STATUS_SALE_NO = 0;//房态关

    public function hotelRoomType()
    {
        return $this->belongsToMany(Coupon::class,'coupon_product','coupon_id','id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class,'shop_id','id');
    }

    public function coupon()
    {
        return $this->belongsToMany(Coupon::class,'coupon_product','product_id','id');
    }

    public function booking()
    {
        return $this->hasMany(HotelBooking::class,'room','id');
    }

}