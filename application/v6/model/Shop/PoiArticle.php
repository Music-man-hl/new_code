<?php

namespace app\v6\model\Shop;

use app\v6\model\BaseModel;
use app\v6\model\Main\District;

class PoiArticle extends BaseModel
{
    protected $json = ['images'];

    public function getImagesAttribute($value)
    {
        $images = [];
        if ($value) {
            foreach ($value as $item) {
                $images[] = $item;
            }
        }
        return $images;
    }

    //关联
    public function tags()
    {
        return $this->belongsToMany(Tags::class, 'poi_article_tag', 'tag_id', 'article_id');
    }

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
