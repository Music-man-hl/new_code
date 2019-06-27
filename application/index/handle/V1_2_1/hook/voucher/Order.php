<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:06
 */

namespace app\index\handle\V1_2_1\hook\voucher;


class Order
{
    //支付
    public function pay($getOrder,$param)
    {
        return OrderLogic::pay($getOrder,$param);
    }

    // 短信-支付成功
    public function smsPaySuccess($order)
    {
    	return OrderLogic::smsPaySuccess($order);
    }

    //在服务号中推送消息给相应得管理员
    public function sendWxTmp($order)
    {
        return OrderLogic::sendWxTmp($order);
//        return true;//暂时不推送
    }

    // 短信-申请退款
    public function smsApplyRefund($order)
    {
        return OrderLogic::smsApplyRefund($order);
    }

    // 模板消息-获取模板数据
    public function informPayInfo($order)
    {
        return OrderLogic::informPayInfo($order);
    }

    // 模板消息-支付
    public function informPay($order,$tpl,$errcode,$errmsg)
    {
        return OrderLogic::informPay($order,$tpl,$errcode,$errmsg);
    }

    //订单创建时的操作
    public function orderCreate($data)
    {
        return OrderLogic::create($data);
    }

    //订单详情时的操作
    public function orderDetail($getOrder,$data)
    {
        return OrderLogic::orderDetail($getOrder,$data);
    }

    //支付之前校验是否可以支付
    public function payCreateVild($orders){
        return OrderLogic::payCreateVild($orders);
    }

    //订单预约
    public function booking($data)
    {
        return OrderLogic::booking($data);
    }

    public function refund($order,$user)
    {
        return OrderLogic::refund($order,$user);
    }

    //获取产品ID
    public function getProductId($id)
    {
        return OrderLogic::getProductId($id);
    }

}