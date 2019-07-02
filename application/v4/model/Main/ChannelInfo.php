<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-08
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\model\Main;


use app\v4\model\BaseModel;

class ChannelInfo extends BaseModel
{
    protected $connection = 'dms_main';

    const STAT_OK = 1;  //可用
    const STAT_NO = 0;  //禁用

    public function channel()
    {
        return $this->belongsTo(Channel::class,'channel','id');
    }

    public function thirdUser()
    {
        return $this->hasOne(ThirdUser::class, 'channel', 'channel');
    }

    public static function getChannelId($id)
    {
        return self::where('id',$id)->where('status',self::STAT_OK)->with(['channel'=>function($query){
            $query->where('status',self::STAT_OK);
        }])->value('channel');
    }

}