<?php
namespace app\v4\controller;

use app\v4\handle\logic\MyLogic;

/**
 * 我的操作
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class My extends Base
{

    // 权限控制
    protected function access()
    {
        return [
            'bind_mobile'           =>  [ 'type'=>'POST' ,   'lived'=>true ] ,
            'is_bind'               =>  [ 'type'=>'GET'  ,   'lived'=>true ] ,
            'login_code'            =>  [ 'type'=>'POST' ,   'lived'=>true ] ,
            'img_captcha'           =>  [ 'type'=>'GET'  ,   'lived'=>false ] ,
            'info_config'           =>  [ 'type'=>'GET'  ,   'lived'=>false ] ,
        ];
    }


    //绑定手机号
    public function bind_mobile()
    {
        MyLogic::service()->bind_mobile($this->channels,$this->all_param,$this->users);
    }

    //门店详情
    public function is_bind()
    {
        MyLogic::service()->is_bind($this->channels,$this->all_param,$this->users);
    }

    //手机短信验证码
    public function login_code()
    {
        MyLogic::service()->login_code($this->channels,$this->all_param,$this->users);
    }

    //获取图片验证码
    public function img_captcha()
    {
        MyLogic::service()->img_captcha($this->channels,$this->all_param,$this->users);
    }

    //获取个人中心配置
    public function info_config()
    {
        MyLogic::service()->info_config($this->channels);
    }

}