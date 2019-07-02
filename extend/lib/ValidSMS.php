<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/6/12
 * Time: 18:19
 */

namespace lib;

use lib\Redis;
class ValidSMS
{
    public $redis;
    public $ipKey;
    public $expire = 86400;//按照天计算
    public $ipCount = 100;//一天一个IP只能发100条

    function __construct($channel)
    {
        $this->ipKey = 'vm_' . APP_EVN .$channel.md5(getIp());
    }

    //这个验证如果多过请求次数就不能再请求了
    function valid() {

        $ipTtl = Redis::ttl($this->ipKey);

        if($ipTtl < 0){ // 没有就生成
            Redis::set($this->ipKey,1,$this->expire);
        }else{

            $ipVal = Redis::get($this->ipKey);

            if( (int)$ipVal > $this->ipCount ) {
                error(50000,'请求太多了！');
            } else {
                Redis::incr($this->ipKey);
            }

        }

    }

    //减一的目的是如果发给用户图片验证码，不算数
    function decr(){
        Redis::decr($this->ipKey);
    }


}