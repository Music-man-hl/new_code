<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/7/4
 * Time: 16:44
 */

namespace app\index\handle\V1_2_1\logic;

use app\common\model\Channel;
use app\common\model\ChannelPay;
use app\index\model\Order;
use lib\MyLog;
use lib\Status;
use pay\JsApiPay;
use pay\WxPayApi;
use pay\WxPayConfig;
use pay\WxPayDownloadBill;
use pay\WxPayUnifiedOrder;
use third\S;

class PayLogic
{

    private $handle;
    private $api_version;

    const AUTH_NO = 0; //取消授权
    const AUTH_OK = 1; //授权成功

    const WECHAT = 1; //微信号
    const APPLET = 2; //小程序

    function __construct($api_version)
    {
        $this->api_version = $api_version;
        $model_path = $api_version . "model\OrderModel";
        $this->handle = new $model_path();
    }

    //支付信息校验
    public function checkPayKey($params)
    {
        $params = filterData($params, ['channel_id', 'pay_mchid', 'pay_key']);
        ChannelPay::setWeixinPayCheck($params);  //设置支付的变量

        $input = new WxPayDownloadBill();
        $input->SetBill_date(date('Ymd', strtotime('-2 day')));
        $input->SetBill_type("ALL");
        $res = WxPayApi::checkSign($input);
        if (!$res) {
            return error('50000', 'pay_key错误');
        }
        return success();
    }

    //小程序支付
    public function wx_app($channels, $params, $users)
    {

        $data = filterData($params, ['order_id', 'code', 'api_version', 'shop_id']);
        $data = array_map('trim', $data);

        if (empty($data['order_id']) || empty($data['code']) || strlen($data['order_id']) > 32 || strlen($data['code']) > 255)
            error(40000, 'code和order_id不正确');

        $channel_info_id = encrypt($params['shop_id'], 3, false);

        $model_path = $this->api_version . "model\AuthModel";
        $handle = new $model_path(); //创建 AuthModel

        $getChannelId = Channel::getChannelId($channel_info_id);
        if (empty($getChannelId)) error(50000, '此店铺已经关闭');

        // 店铺授权
        $res = $handle->getChannelInfoAndThirdUser($channel_info_id, self::APPLET);
        if (empty($res)) error(48001, '该店铺小程序未授权');
        if ($res['status'] != self::AUTH_OK) error(48001, '该小程序未授权');

        if (empty($appid = $res['appid'])) error(48001, '小程序appid错误');
        $channel = $res['channel'];

        $server = new WxServer;
        // 通过code获取session_key
        $sessionData = $server->getSessionKey($appid, $data['code']);
        $openId = $sessionData['openid'];//获取到openid

        ChannelPay::setWeixinPay($channel);  //设置支付的变量

        //1 订单配置[优惠券]

        $tools = new JsApiPay();

        $order = $this->payCreate($channel, $data['order_id'], $users); //获取订单信息
        $order_store = $order;
        try {
            //2.统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody(str_replace(' ', '', $order['product_name']));   //设置商品或支付单简要描述
            $input->SetAttach($channel_info_id . '_' . $order['order'] . '_' . $data['api_version']);  //设置附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
            $input->SetOut_trade_no($order['out_trade_no']); //设置商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
            $input->SetTotal_fee($order['fee']);
            $input->SetTime_start(date("YmdHis"));      //交易起始时间
            $input->SetTime_expire(date("YmdHis", $order['expire'] - NOW < 600 ? (NOW + 600) : $order['expire']));  //交易结束时间  最短失效时间间隔必须大于5分钟(10分钟)
            $input->SetGoods_tag("");       //设置商品标记，代金券或立减优惠功能的参数，说明详见代金券或立减优惠
            $input->SetNotify_url(DOMAIN . '/notify/weixin');  //设置接收微信支付异步通知回调地址
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openId);
            $order = WxPayApi::unifiedOrder($input);

            $jsApiParameters = $tools->GetJsApiParameters($order);
            $parameters = json_decode($jsApiParameters, true);

            $list = [
                'appId' => WxPayConfig::APPID,//小程序支付必须有appid
                'timeStamp' => $parameters['timeStamp'],
                'nonceStr' => $parameters['nonceStr'],
                'package' => $parameters['package'],
                'signType' => $parameters['signType'],
                'paySign' => $parameters['paySign'],
            ];
            // 模板消息处理 
            $this->preSendMessage($list, $order_store, $appid, $openId);

            success($list);

        } catch (\Exception $e) {
            // @TODO 异常处理
            error(40202, $e->getMessage());
        }

    }

    //微信支付成功后的回调
    public function notify($param)
    {
        S::log($param);//写入本地支付日志 调试用 上线时去掉

        $this->handle->orderPayLog($param['channel'], $param['order'], json_encode($param, JSON_UNESCAPED_UNICODE), NOW);//写入支付回调日志
        $order = Order::where('order', $param['order'])->find();
        $order->pay_count = $order->ext->pay_count;
        if ($order['status'] != 2) error(50000, '已经支付过了，无需支付');
        $order['cart'] ? $this->updateCartOrder($order, $param) : $this->updateOrder($order, $param);

    }

    //更新购物车的订单
    private function updateCartOrder($getOrder, $param)
    {
        return [];//敬请期待
    }

    //更新正常的订单
    private function updateOrder($getOrder, $param)
    {

        $classes = $this->api_version . "hook\OrderInit";

        $list = $classes::factory($getOrder['type'])->apply('pay', $getOrder, $param);  //这个执行
        if ($list) {
            // 短信
            $ret = $classes::factory($getOrder['type'])->apply('smsPaySuccess', $getOrder);
//            $classes::factory($getOrder['type'])->apply('sendWxTmp', $getOrder);
            $postData = [
                'order' => $getOrder['order'],
                'token' => md5(config('web.pms.secret') . NOW),
                'timestamp' => NOW,

            ];
            if (empty($getOrder['good_code'])) {
                $result = curl_file_get_contents(DOMAIN_MP . '/sms/neworder', $postData);
                $result = json_decode($result, true);
                if (!isset($result["errcode"]) || $result["errcode"] != 0) {
                    MyLog::error("send tpl err: order-{$getOrder['order']} msg:" . $result);
                }
            }
            S::log('执行短信操作 - 订单号:' . $getOrder['order'] . ' 结果:' . var_export($ret, true));
            if ($ret) {
                $res = S::exec($getOrder['order']);
                S::log('支付成功 - 及时发送短信结果:' . json_encode($res, JSON_UNESCAPED_UNICODE));
            }
            // 模版消息
            $this->informSend($classes, $getOrder);
        }
        return $list;

    }

    //获取支付信息
    private function payCreate($channel, $order, $users)
    {

        $getOrder = $this->handle->getOrder($channel, $order);

        if (empty($getOrder)) error(40000, '没有找到此订单');
        if ($getOrder['status'] != 2) error(50000, '已经支付过了，无需支付');
        if ($getOrder['uid'] != $users) error(40200);//不是自己的
        if ($getOrder['expire'] < NOW) error(40201);//过期

        $this->payCreateVild($getOrder);//创建之前的校验

        $pay_count = (int)$getOrder['pay_count'] + 1;
        $res = $this->handle->updatePayCount($getOrder['id']);
        if (!$res) error(50000, 'pay_count设置失败');

        $getOrder['out_trade_no'] = $getOrder['order'] . '_' . $pay_count;
        $fee = bcmul(add($getOrder['total'], -$getOrder['rebate'], -$getOrder['sales_rebate']), 100);

        $getOrder['fee'] = intval($fee);
        if (empty($getOrder['fee'])) error(50000, '订单金额不正确');

        return $getOrder;

    }

    //模板消息
    private function preSendMessage($payData, $order, $appid, $openId)
    {
        $prepayId = substr($payData['package'], 10);
        $params = [$order['order'], $prepayId, $appid, $openId];
        // 记录prepay_id
        $ret = $this->handle->handlerecordInformMsg(...$params);

        S::log('发送模板消息 - 记录prepay_id' . ($ret ? '成功' : '失败') . ' 数据:' . json_encode($params));
    }

    // 执行模版发送
    private function informSend($classes, $order)
    {
        $tpl = $classes::factory($order['type'])->apply('informPayInfo', $order);
        if ($tpl) {
            S::log('发送模板消息 - 要发送的数据:' . json_encode($tpl, JSON_UNESCAPED_UNICODE));
            $server = new WxServer;
            $res = $server->sender($tpl);
            S::log('发送模板消息 - 发送结果:' . json_encode($res, JSON_UNESCAPED_UNICODE));
            if ($res && is_array($res)) {
                $ret = $res['errcode'] == 0 ? 1 : 2;
                $order['appid'] = $tpl['appid'];
                $order['openid'] = $tpl['touser'];
                $result = $classes::factory($order['type'])->apply('informPay', $order, $tpl, $ret, $res['errcode'] . $res['errmsg']);
                S::log('发送模板消息 - 记录发送数据inform_send ' . ($result ? '成功' : '失败'));
            }
        }
    }

    //支付创建之前的校验
    private function payCreateVild($orders)
    {
        if (in_array($orders['type'], [Status::CALENDAR_PRODUCT])) return true;

        $classes = $this->api_version . "hook\OrderInit";
        $classes::factory($orders['type'])->apply('payCreateVild', $orders);

    }

}