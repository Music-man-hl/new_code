<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-04
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class ProductPicture extends Model
{
    public function getPicAttr($value, $data)
    {
        return picture($data['bucket'], $data['pic']);
    }

}