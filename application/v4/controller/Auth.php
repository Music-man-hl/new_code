<?php
namespace app\v4\controller;

use app\v4\handle\logic\AuthLogic;

/**
 * 授权相关
 * X-Wolf
 * 2018-6-25
 */

class Auth extends Base
{

    protected function access(){
        return [
            'wx_login'            =>  [ 'type'=>'POST' ],
            'refresh_token'  =>  [ 'type'=>'POST' ],
        ];
    }

    //登陆(小程序)
    public function wx_login(){
        AuthLogic::service()->wxLogin( $this->all_param);
    }

    public function refresh_token()
    {
        AuthLogic::service()->auth_refresh_token( $this->all_param);
    }

}