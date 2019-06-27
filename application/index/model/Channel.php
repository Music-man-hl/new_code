<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class Channel extends Model
{
    public function shops()
    {
        return $this->hasMany('Shop','channel','id');
    }
}