<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-25
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\Services;


use app\v4\model\BaseModel;
use lib\Status;

class BaseService
{

    private static $instances;

    /**
     * @return static
     */
    public static function service()
    {
        $name = get_called_class();

        if (!isset(self::$instances[$name]) || !is_object(self::$instances[$name])) {

            self::$instances[$name] = new static();

            return self::$instances[$name];
        }

        return self::$instances[$name];
    }

    /**
     * 返回channel和shop_id
     * @param $all_param
     * @return array
     */
    protected static function shop($all_param){
        $channelId = encrypt($all_param['channel'], Status::ENCRYPT_SHOP, false);//渠道id
        if (empty($all_param['sub_shop_id'])) {
            $shopId = BaseModel::validSubId($channelId);
            if ($shopId === false) error(40000, '门店错误！');
        } else {
            $shopId = encrypt($all_param['sub_shop_id'], Status::ENCRYPT_SUB_SHOP, false);//门店id
        }
        return ['channel'=>$channelId,'shop_id'=>$shopId];
    }

}