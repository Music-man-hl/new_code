<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-15
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class OrderPayLog extends Model
{
    public static function addLog($channel, $order, $data)
    {
        return self::insertGetId(['channel' => $channel, 'order' => $order, 'data' => $data, 'create' => NOW]);
    }

}