<?php
namespace app\index\controller;

/**
 * 授权相关
 * X-Wolf
 * 2018-6-25
 */
use app\common\controller\Common;
use lib\Redis;

class Auth extends Common
{

    protected function access(){
        return [
            'wx_login'            =>  [ 'type'=>'POST' ],
            'refresh_token'  =>  [ 'type'=>'POST' ],
        ];
    }

    //登陆(小程序)
    public function wx_login(){

        $logic = $this->api_version."logic\AuthLogic"; 
        $handel = new $logic($this->api_version);
        $handel->wxLogin( $this->all_param);

    }

    public function refresh_token()
    {
        $logic = $this->api_version."logic\AuthLogic";
        $handel = new $logic($this->api_version);
        $handel->auth_refresh_token( $this->all_param);
    }

}