<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/21 0021
 * Time: 下午 14:43
 */
namespace app\common\model;

use think\Db;

class Channel {

    const STAT_OK = 1;  //可用
    const STAT_NO = 0;  //禁用

    //获取channl与id映射
    static function ids(){
        $data = Db::name('channel_info')->field('id,channel')->where(['stat'=>self::STAT_OK])->select();
        return array_column($data,'channel','id');
    }

    function getChannel($id)
    {
        $data = Db::name('channel_info')->field('id,channel')->where(['status'=>self::STAT_OK])->where(['id'=>$id])->select();
        return array_column($data,'channel','id');
    }

    //获取chennel的id
    static function getChannelId($id){
        return Db::name('channel c')->field('c.id')->join('channel_info i','i.channel=c.id')
            ->where(['c.status'=>self::STAT_OK,'i.id'=>$id,'i.status'=>self::STAT_OK])->find();
    }
}