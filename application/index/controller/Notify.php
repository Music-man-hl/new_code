<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/7/4
 * Time: 18:29
 */

namespace app\index\controller;

use pay\PayNotifyCallBack;
use think\Controller;
use app\common\model\ChannelPay;
use app\common\model\Channel;
use third\S;

class Notify extends Controller
{

    //微信异步回调
    function weixin(){

        $postStr = file_get_contents('php://input');//获取post数据

        if ( empty($postStr) ) {
            throw new \Exception('Params Not Allow Empty');
        }

        S::log($postStr);//写入本地支付日志 调试用 上线时去掉

        // 禁止加载外部扩展
        libxml_disable_entity_loader(true);
        $XML2Array = json_decode(json_encode(simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        if(empty($XML2Array)) {
            throw new \Exception('Params Error');
        }

        $attach = isset($XML2Array['attach']) ? $XML2Array['attach'] : '';//自定义的参数，格式:channel_order_version
        if(empty($attach) || substr_count($attach,'_') != 2) error(40000,'attach错误');
        $attach_array = explode('_', $attach);

        $api_version = $this->getVersion($attach_array[2]); //获取版本

        $channel_info_id = $attach_array[0]; //解密之后的channel_info_id

        $getChannelId = Channel::getChannelId($channel_info_id);
        if(empty($getChannelId)) error(50000,'此店铺已经关闭');

        ChannelPay::setWeixinPay($getChannelId['id']);  //设置支付的变量

        $notify   = new PayNotifyCallBack();
        $notify->Handle(false);//这里返回给微信数据

        //交易成功 处理订单逻辑
        $returnValues   = $notify->GetValues();
        if(!empty($returnValues['return_code']) && $returnValues['return_code'] == 'SUCCESS'){
            //商户逻辑处理，如订单状态更新为已支付  走到这里说明已经支付成功
            $data   =   $notify->xmlData ;//如果校验成功才能使用这些数据  如果失败就不应该返回这些数据
            $data['order'] = $attach_array[1]; //商家订单
            $data['shop_info_id'] = $channel_info_id;//渠道信息
            $data['channel'] = $getChannelId['id'];//真实渠道信息
            $data['version'] = $attach_array[2];//版本

            $logic = $api_version."logic\PayLogic"; //获取调用的空间名
            $handel = new $logic($api_version);
            $handel->notify($data); //回调

        }

        exit();//输出后退出
    }

    private function getVersion($api_version){
        if(!empty($api_version) && preg_match("/^[\.\d]*$/i",$api_version)){
            $api_version = 'V'.str_replace('.','_',$api_version);
            $version_dir = PROGARM_ROOT.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'index'.
                DIRECTORY_SEPARATOR.'handle'.DIRECTORY_SEPARATOR.$api_version;
            if(is_dir($version_dir)) {
                return "\app\index\handle\\{$api_version}\\";
            }
        }

        error(40000,'api_version错误哦');
    }

}