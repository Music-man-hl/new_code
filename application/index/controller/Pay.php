<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/7/4
 * Time: 15:44
 */

namespace app\index\controller;


use app\common\controller\Common;

class Pay extends Common
{
    private $handel;
    protected $beforeActionList = [
        'channelHandle' => ['except'=>'access'],
    ];

    // 权限限制
    protected function access()
    {
        return [
            'wx_app' => [ 'type'=>'POST'  , 'lived' => true ],
            'checkPayKey' => ['type' => 'get', 'lived' => false],
        ];
    }


    protected function channelHandle()
    {
        $logic = $this->api_version.'logic\PayLogic';
        $this->handel = new $logic($this->api_version);
    }

    //小程序调起微信支付接口
    function wx_app(){
        $this->handel->wx_app($this->channels,$this->all_param,$this->users);
    }

    //
    public function checkPayKey()
    {
        $this->handel->checkPayKey($this->all_param);
    }

}