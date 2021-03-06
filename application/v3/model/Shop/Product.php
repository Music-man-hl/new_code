<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;

class Product extends BaseModel
{

    const STATUS_VALID = 1; //有效

    public function getEnidAttr($value, $data)
    {
        return encrypt($data['id'], 1);
    }

    public function info()
    {
        return $this->hasOne('ProductInfo', 'id', 'id');
    }

    public function video()
    {
        return $this->hasOne('ProductVideo', 'pid', 'id');
    }

    public function pictures()
    {
        return $this->hasMany('ProductPicture', 'pid', 'id');
    }

    public function ticketTags()
    {
        return $this->hasMany('ProductTicketTag', 'pid', 'id');
    }

    public function ticketItems()
    {
        return $this->hasMany(ProductTicketItem::class, 'pid', 'id');
    }

    public function coupon()
    {
        return $this->belongsToMany(Coupon::class,'coupon_product','coupon_id','product_id');
    }

}