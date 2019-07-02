<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-08
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class ProductVideo extends Model
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