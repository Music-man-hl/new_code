<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/7/4
 * Time: 15:44
 */

namespace app\v6\controller;


use app\v6\handle\logic\PayLogic;

class Pay extends Base
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
        $this->handel = new PayLogic();
    }

    //小程序调起微信支付接口
    function wx_app(){
        PayLogic::service()->wx_app($this->users);
    }

    //
    public function checkPayKey()
    {
        PayLogic::service()->checkPayKey($this->all_param);
    }

}