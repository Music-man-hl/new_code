<?php
namespace lib;
/**
 * Class Redis
 * @package lib
 */
class Redis
{

    public static $serialize = ['serialize', 'unserialize', 'think_serialize:', 16];
    /**
     * 序列化数据
     * @access protected
     * @param  mixed $data
     * @return string
     */
    public static function serialize($data)
    {
        if ( is_scalar($data) ) {
            return $data;
        }

        $serialize = self::$serialize[0];

        return self::$serialize[2] . $serialize($data);
    }

    /**
     * 反序列化数据
     * @access protected
     * @param  string $data
     * @return mixed
     */
    public static function unserialize($data)
    {
        if (  0 === strpos($data, self::$serialize[2]) ) {
            $unserialize = self::$serialize[1];

            return $unserialize(substr($data, self::$serialize[3]));
        } else {
            return $data;
        }
    }

    /**
     * 实例化redis
     */
    public static function instance(){
        return RedisDriver::getInstance()->redis();
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $value  设置值
     * @param int $timeOut 时间  0表示无过期时间
     */
    public static function set($key, $value, $timeOut=0) {
        $value  = self::serialize($value);
        $retRes = self::instance()->set($key, $value);
        if ($timeOut > 0)
            self::expire($key, $timeOut);
        return $retRes;
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $value  设置值
     * @param int $timeOut 时间  0表示无过期时间
     */
    public static function get($key) {
        $data =  self::instance()->get($key);
        return self::unserialize($data);
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称 del string array
     * @param string $value  设置值
     * @param int $timeOut 时间  0表示无过期时间
     */
    public static function del($key) {
        return self::instance()->del($key);
    }


    /**
     * 构建一个集合(无序集合)
     * @param string $key 集合Y名称
     * @param string|array $value  值
     */
    public static function sAdd($key,$value){
        if(is_array($value)){
            array_unshift($value,$key);
            return call_user_func_array(array(self::instance(), 'sAdd'), $value);
        }else{
            return self::instance()->sAdd($key,$value);
        }
    }

    /**
     * 构建一个集合(有序集合)
     * @param string $key 集合名称
     * @param string|array $value  值
     */
    public static function zAdd($key,$score,$value){
        return self::instance()->zAdd($key,$score,$value);
    }

    /**
     * 取集合对应元素
     * @param string $setName 集合名字
     */
    public static function sMembers($setName){
        return self::instance()->sMembers($setName);
    }

    /**
     * 构建一个列表(先进后去，类似栈)
     * @param sting $key KEY名称
     * @param string $value 值
     */
    public static function lPush($key,$value){
        return self::instance()->lPush($key,$value);
    }

    /**
     * 构建一个列表(先进先去，类似队列)
     * @param sting $key KEY名称
     * @param string $value 值
     */
    public static function rPush($key,$value){
        return self::instance()->rPush($key,$value);
    }

    /**
     * HASH类型
     * @param string $tableName  表名字key
     * @param string $key            字段名字
     * @param sting $value          值
     */
    public static function hSet($tableName,$field,$value){
        return self::instance()->hSet($tableName,$field,$value);
    }

    public function hGet($tableName,$field){
        return self::instance()->hGet($tableName,$field);
    }

    /**
     * HASH类型
     * @param string $tableName  表名字key
     * @param string $key            字段名字
     * @param sting $value          值
     */
    public static function hMset($tableName,$value){
        return self::instance()->hMset($tableName,$value);
    }

    public static function hMGet($tableName,$field){
        return self::instance()->hMget($tableName,$field);
    }

    /**
     * 设置多个值
     * @param array $keyArray KEY名称
     * @param string|array $value 获取得到的数据
     * @param int $timeOut 时间
     */
    public static function mset($keyArray, $timeout) {
        if (is_array($keyArray)) {
            $retRes = self::instance()->mset($keyArray);
            if ($timeout > 0) {
                foreach ($keyArray as $key => $value) {
                    self::expire($key, $timeout);
                }
            }
            return $retRes;
        } else {
            return "Call  " . __FUNCTION__ . " method  parameter  Error !";
        }
    }


    /**
     * 同时获取多个值
     * @param ayyay $keyArray 获key数值
     */
    public static function mget($keyArray) {
        if (is_array($keyArray)) {
            return self::instance()->mget($keyArray);
        } else {
            return "Call  " . __FUNCTION__ . " method  parameter  Error !";
        }
    }

    /**
     * 数据自增
     * @param string $key KEY名称
     */
    public static function incr($key) {
        return self::instance()->incr($key);
    }

    /**
     * 数据自减
     * @param string $key KEY名称
     */
    public static function decr($key) {
        return self::instance()->decr($key);
    }

    /**
     * 判断key是否存在
     * @param string $key KEY名称
     */
    public static function exists($key){
        return self::instance()->exists($key);
    }

    /**
     * 获取KEY存储的值类型
     * none(key不存在) int(0)  string(字符串) int(1)   list(列表) int(3)  set(集合) int(2)   zset(有序集) int(4)    hash(哈希表) int(5)
     * @param string $key KEY名称
     */
    public static function type($key){
        return self::instance()->type($key);
    }

    //查询过期时间用
    public static function ttl($key){
        return self::instance()->ttl($key);
    }
    /**
     * 设置过期时间
     * @param string $key KEY名称
     * @param int $timeOut 时间  0表示无过期时间
     */
    public static function expire($key , $timeOut=0) {
        return self::instance()->expire($key, $timeOut);
    }

    /**
     * 删除有序集合的key
     * @param $key
     * @param $value
     * @return int
     */
    public static function zRem($key,$value){
        return self::instance()->zRem($key,$value);
    }

    /**
     * 删除无序集合的值
     * @param $key
     * @param $value
     * @return int
     */
    public static function sRem($key,$value){
        return self::instance()->sRem($key,$value);
    }

    /**
     * 判断是否出现在集合中
     * @param $key
     * @param $value
     * @return bool
     */
    public static function sIsMember( $key, $value ) {
        return self::instance()->sIsMember($key,$value);
    }

}










