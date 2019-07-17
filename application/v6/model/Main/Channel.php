<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-01
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Main;


use app\v6\model\BaseModel;

class Channel extends BaseModel
{
    protected $connection = 'dms_main';


    public function database()
    {
        return $this->belongsTo(ChannelDatabase::class,'db_id','id')->bind('hostname,dbname,username,password,hostport');
    }

    public function shops()
    {
        return $this->hasMany(Shop::class,'channel','id');
    }
}