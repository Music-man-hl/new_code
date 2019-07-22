<?php


namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class OrderRetail extends BaseModel
{
    public $autoWriteTimestamp = 'timestamp';
    protected $json = [
        'receive_address',
        'take_address'
    ];
}