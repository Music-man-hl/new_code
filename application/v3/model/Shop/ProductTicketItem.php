<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-04
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;

class ProductTicketItem extends BaseModel
{

    public function getEnidAttr($value, $data)
    {
        return encrypt($data['id'], 1);
    }

    public function Product()
    {
        return $this->belongsTo(Product::class, 'pid', 'id');
    }

    public function Tags()
    {
        return $this->hasMany(ProductTicketItemTag::class, 'item_id', 'id');
    }

    public function booking()
    {
        return $this->hasMany(ProductTicketBooking::class, 'item_id', 'id');
    }

}