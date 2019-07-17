<?php

namespace app\v6\model\Shop;

use app\v6\model\BaseModel;
use lib\Status;

class DigitalLine extends BaseModel
{
    protected $autoWriteTimestamp = 'datetime';

    public function getEidAttr($value, $data)
    {
        return encrypt($data['id'], Status::ENCRYPT_DIGITAL);
    }

}
