<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-08
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\model\Shop;


use app\v4\model\BaseModel;

class ProductVideo extends BaseModel
{

    public function getPicAttr($value, $data)
    {
        return picture($data['video_bucket'], $data['pic']);
    }

    public function getUrlAttr($value, $data)
    {
        return picture($data['video_bucket'], $data['url']);
    }

}