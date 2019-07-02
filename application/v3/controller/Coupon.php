<?php
namespace app\v3\controller;

use app\v3\handle\logic\CouponLogic;

/**
 * 优惠券相关
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class Coupon extends Base
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
        CouponLogic::service()->detail($this->channels,$this->all_param,$this->users);
    }

    //券列表
    public function coupon_list()
    {
        CouponLogic::service()->coupon_list($this->channels,$this->all_param,$this->users);
    }

    //我的优惠券
    public function lists()
    {
        CouponLogic::service()->lists($this->channels,$this->all_param,$this->users);
    }

    //券相关产品
    public function product_list()
    {
        CouponLogic::service()->product_list($this->channels,$this->all_param,$this->users);
    }

    //领取优惠券
    public function draw()
    {
        CouponLogic::service()->draw($this->channels,$this->all_param,$this->users);
    }


}