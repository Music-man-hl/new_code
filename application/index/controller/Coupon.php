<?php
namespace app\index\controller;

use app\common\controller\Common;

/**
 * 优惠券相关
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class Coupon extends Common
{

    // 权限控制
    protected function access()
    {
        return [
            'detail'                =>  [ 'type'=>'GET' ,   'lived'=>false] ,
            'coupon_list'           =>  [ 'type'=>'GET' ,   'lived'=>true ] ,
            'lists'                  =>  [ 'type'=>'GET' ,   'lived'=>true ] ,
            'product_list'          =>  [ 'type'=>'GET' ,   'lived'=>false] ,
            'draw'                  =>  [ 'type'=>'POST',   'lived'=>true ] ,
        ];
    }


    //优惠券详情
    public function detail()
    {
        $logic = $this->api_version."logic\CouponLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->detail($this->channels,$this->all_param,$this->users);
    }

    //券列表
    public function coupon_list()
    {
        $logic = $this->api_version."logic\CouponLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->coupon_list($this->channels,$this->all_param,$this->users);
    }

    //我的优惠券
    public function lists()
    {
        $logic = $this->api_version."logic\CouponLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->lists($this->channels,$this->all_param,$this->users);
    }

    //券相关产品
    public function product_list()
    {
        $logic = $this->api_version."logic\CouponLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->product_list($this->channels,$this->all_param,$this->users);
    }

    //领取优惠券
    public function draw()
    {
        $logic = $this->api_version."logic\CouponLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->draw($this->channels,$this->all_param,$this->users);
    }


}