<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-08
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class ChannelInfo extends Model
{
    public function thirdUser()
    {
        return $this->hasOne(ThirdUser::class, 'channel', 'channel');
    }
}