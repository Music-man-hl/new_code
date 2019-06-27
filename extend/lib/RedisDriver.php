<?php
namespace lib;
/**
 * Class RedisDriver
 * @package lib
 */
class RedisDriver
{
    //保存类实例的静态成员变量
    private static $_instance;

    private $redis;

    /**
     * @param string $host
     * @param int $post
     */
    private function __construct($host, $port , $auth) {
        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
        if(!empty($auth)) $this->redis->auth($auth);
        return $this->redis;
    }

    //创建__clone方法防止对象被复制克隆
    public function __clone(){
        trigger_error('Clone is not allow!',E_USER_ERROR);
    }

    //单例方法,用于访问实例的公共的静态方法
    public static function getInstance(array $config = []){

        if(!(self::$_instance instanceof self)){
            if(empty($config)) {
                $config = config('cache.redis');
            }
            $host =  $config['host'] ;
            $port =  $config['port'] ;
            $auth =  $config['password'];
            self::$_instance = new self($host, $port , $auth);
        }
        return self::$_instance;
    }


    /**
     * 返回redis对象
     * redis有非常多的操作方法，我们只封装了一部分
     * 拿着这个对象就可以直接调用redis自身方法
     * eg:$redis->redis()->keys('*a*')   keys方法没封
     */
    public function redis() {
        return $this->redis;
    }

}










