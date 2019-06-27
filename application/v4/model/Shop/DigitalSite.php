<?php

namespace app\v4\model\Shop;

use app\v4\model\BaseModel;
use app\v4\model\Main\District;

class DigitalSite extends BaseModel
{
    protected $autoWriteTimestamp = 'datetime';

    public function province()
    {
        return $this->hasOne(District::class, 'id', 'province');
    }

    public function city()
    {
        return $this->hasOne(District::class, 'id', 'city');
    }

    public function county()
    {
        return $this->hasOne(District::class, 'id', 'county');
    }
}
