<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-15
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;

class OrderPayLog extends BaseModel
{

    public static function addLog($channel, $order, $data)
    {
        return self::insertGetId(['channel' => $channel, 'order' => $order, 'data' => $data, 'create' => NOW]);
    }

}