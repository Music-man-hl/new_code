<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-08
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;

class CouponProduct extends BaseModel
{

    public function coupon()
    {
        return $this->hasMany(Coupon::class, 'id', 'coupon_id');
    }

}