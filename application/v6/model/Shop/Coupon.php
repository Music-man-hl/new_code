<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-16
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class Coupon extends BaseModel
{

    public function code()
    {
        return $this->hasMany(CouponCode::class, 'coupon_id', 'id');
    }

    public function hotelRoomType()
    {
        return $this->belongsToMany(HotelRoomType::class,'coupon_product','product_id','coupon_id');
    }

    public function product()
    {
        return $this->belongsToMany(Product::class,'coupon_product','product_id','coupon_id');
    }

}