<?php
namespace app\common\model;

use think\Db;
/**
 * 房型相关处理
 * X-Wolf
 * 2018-4-28
 */
class Room
{
    public static function validSubId($channel)
    {
        $data = Db::table('shop')->field('id')->where('channel',$channel)->select();
        if(count($data)>1||count($data) == '0') return false;
        return $data[0]['id'];
    }

    public static function getRealChannel($channel)
    {
        $sql   = 'SELECT `channel` FROM channel_info WHERE id=:channel AND status=1';
        $param = array('channel'=>$channel);
        $data  = Db::query($sql,$param);
        if(count($data)>1||count($data) == 0) return false;
        return $data[0]['channel'];
    }
}


