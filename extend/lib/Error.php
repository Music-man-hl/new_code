<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/4/25
 * Time: 11:59
 */

namespace lib;


class Error
{
    static $code = 0;
    static $message = '';
    //设置错误码和错误信息
    static function set($code,$message=''){
        self::$code = $code;
        self::$message = $message;
    }
    //获取错误码和错误信息
    static function get(){
        if(!empty(self::$code)){
            return self::$code . ' ' . self::$message ;
        }
        return '';
    }
    //设置错误码
    static function setCode($code){
        self::$code = $code;
    }
    //设置错误信息
    static function setMessage($message=''){
        self::$message = $message;
    }
    //获取错误码
    static function getCode(){
        return  self::$code;
    }
    //获取错误信息
    static function getMessage(){
        return self::$message;
    }

}