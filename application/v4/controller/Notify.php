<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/7/4
 * Time: 18:29
 */

namespace app\v4\controller;

use app\v4\handle\logic\ExtensionLogic;
use app\v4\handle\logic\PayLogic;
use app\v4\model\Main\ChannelInfo;
use app\v4\Services\WeixinPay;
use Exception;
use lib\MyLog;
use pay\PayNotifyCallBack;
use think\Controller;
use think\facade\Cache;
use third\S;

class Notify extends Controller
{

    //微信异步回调
    public function weixin()
    {

        $postStr = file_get_contents('php://input');//获取post数据

        if (empty($postStr)) {
            throw new Exception('Params Not Allow Empty');
        }

        S::log($postStr);//写入本地支付日志 调试用 上线时去掉

        // 禁止加载外部扩展
        libxml_disable_entity_loader(true);
        $XML2Array = json_decode(json_encode(simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        if (empty($XML2Array)) {
            throw new Exception('Params Error');
        }

        $attach = isset($XML2Array['attach']) ? $XML2Array['attach'] : '';//自定义的参数，格式:channel_order_version
        if (empty($attach) || substr_count($attach, '_') != 2) error(40000, 'attach错误');
        $attach_array = explode('_', $attach);

        $channel_info_id = $attach_array[0]; //解密之后的channel_info_id

        $getChannelId = ChannelInfo::getChannelId($channel_info_id);
        if (empty($getChannelId)) error(50000, '此店铺已经关闭');

        $this->setConf($getChannelId); //设置数据库配置
        WeixinPay::service()->setWeixinPay($getChannelId);  //设置支付的变量

        $notify = new PayNotifyCallBack();
        $notify->Handle(false);//这里返回给微信数据

        //交易成功 处理订单逻辑
        $returnValues = $notify->GetValues();
        if (!empty($returnValues['return_code']) && $returnValues['return_code'] == 'SUCCESS') {
            //商户逻辑处理，如订单状态更新为已支付  走到这里说明已经支付成功
            $data = $notify->xmlData;//如果校验成功才能使用这些数据  如果失败就不应该返回这些数据
            $data['order'] = $attach_array[1]; //商家订单
            $data['shop_info_id'] = $channel_info_id;//渠道信息
            $data['channel'] = $getChannelId;//真实渠道信息
            $data['version'] = $attach_array[2];//版本

            MyLog::info('微信回调通知开始---');
//            PayLogic::service()->notify($data); //微信回调通知
            MyLog::info('微信回调通知结束---');
            ExtensionLogic::service()->sendMq($attach_array[1]);
        }

        exit();//输出后退出
    }

    public function setConf($channelId)
    {
        $dbConfigs = Cache::store('redis')->remember(redis_prefix() . 'dbConfigs', function () {
            return (new Base())->getDbConfigs();
        });
        request()->dbConfig = $dbConfigs[$channelId];
    }

}