<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-16
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class CouponCode extends BaseModel
{

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }
}