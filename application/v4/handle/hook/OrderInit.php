<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 17:40
 */

namespace app\v4\handle\hook;


class OrderInit
{
    static $namespace = []; //实例化类
    static $initialise; //实例化自己
    static $class;//要执行的类

    /**
     * 工厂模式实例化类
     * @param $type int 产品类型
     * @return static object 实例化
     */
    static public function factory($type)
    {
        $hook = config('web.product_types');//酒店、门票、套餐、商超
        if (empty($type) || !in_array($type, array_keys($hook))) error(40000, '产品类型错误');

        self::$class = $item = $hook[$type];
        $class = __NAMESPACE__ . '\\' . $item . '\\OrderLogic';

        if (!isset(self::$namespace[$item])) self::$namespace[$item] = new $class();

        if (!self::$initialise) self::$initialise = new static();

        return self::$initialise;
    }

    /**
     * $args 第一个必须传递方法 其他值依次传递
     * @return bool|mixed
     */
    public function apply()
    {
        $args = func_get_args();
        $method = $args[0]; //方法

        if (!isset(self::$namespace[self::$class])) return false;
        $item = self::$namespace[self::$class];

        if (method_exists($item, $method)) {
            return call_user_func_array([$item, $method], array_slice($args, 1));
        } else {
            error(50000, $method . '不存在');
        }

    }

}