<?php
namespace app\index\controller;

use app\common\controller\Common;

/**
 * 我的操作
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class My extends Common
{

    private $handel;



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
        $logic = $this->api_version."logic\MyLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->bind_mobile($this->channels,$this->all_param,$this->users);
    }

    //门店详情
    public function is_bind()
    {
        $logic = $this->api_version."logic\MyLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->is_bind($this->channels,$this->all_param,$this->users);
    }

    //手机短信验证码
    public function login_code()
    {
        $logic = $this->api_version."logic\MyLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->login_code($this->channels,$this->all_param,$this->users);
    }

    //获取图片验证码
    public function img_captcha()
    {
        $logic = $this->api_version."logic\MyLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->img_captcha($this->channels,$this->all_param,$this->users);
    }

    //获取个人中心配置
    public function info_config()
    {
        $logic = $this->api_version."logic\MyLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->info_config($this->channels,$this->all_param,$this->users);
    }

}