<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-25
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\Services;


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
}