<?php


namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class DistributionOrder extends BaseModel
{
    public function orderInfo()
    {
        return $this->belongsTo(Order::class, 'order', 'order');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'distribution_user_id', 'id');
    }
}