<?php


namespace app\v4\Services;


use app\v4\model\Main\ThirdUser;
use app\v4\model\Shop\ChannelPayCert;
use EasyWeChat\Factory;
use lib\Error;

class EasyWeChat extends BaseService
{
    protected $app;
    protected $channel;
    protected $config;

    public function __construct()
    {
        $this->channel = $this->getChannel();
        $this->config = $this->getConfig();
        $this->app = Factory::payment($this->config);
    }

    private function getChannel()
    {
        if (!request()->channel) {
            return error(50000, 'channelID不存在');
        }
        return $this->channel = request()->channel;
    }

    private function getConfig()
    {
        $thirdUser = ThirdUser::where('channel', $this->channel['channelId'])->find();
        $payCert = ChannelPayCert::where('channel', $this->channel['channelId'])->find();
        $this->checkCert($thirdUser, $payCert);

        return [ // 必要配置
            'app_id'             => $thirdUser->appid,
            'mch_id'             => $thirdUser->pay_mchid,
            'key'                => $thirdUser->pay_key,   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path'          =>  PROGARM_ROOT .$thirdUser->pay_cert_path, // XXX: 绝对路径！！！！
            'key_path'           =>  PROGARM_ROOT .$thirdUser->pay_key_path,      // XXX: 绝对路径！！！！

            // 将上面得到的公钥存放路径填写在这里
//            'rsa_public_key_path' => '/path/to/your/rsa/publick/key/public-14339221228.pem',

//            'notify_url'         => '默认的订单回调地址',     // 你也可以在下单时单独设置来想覆盖它
        ];

    }

    public function transferToBalance($check_name, $amount, $desc, $openId)
    {
        $partner_trade_no = makeWithdrawOrder($this->channel['channelId']);
        $result = $this->app->transfer->toBalance([
            'partner_trade_no' => $partner_trade_no, // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
            'openid' => $openId,
            'check_name' => 'FORCE_CHECK', // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
            're_user_name' => $check_name, // 如果 check_name 设置为FORCE_CHECK，则必填用户真实姓名
            'amount' => $amount, // 企业付款金额，单位为分
            'desc' => $desc, // 企业付款操作说明信息。必填
        ]);
        return $result;

    }

    //检查证书是否存在
    private function checkCert($thirdUser, $payCert)
    {
        if (empty($payCert)) error(40310, '没有支付证书');
        if (!$thirdUser->pay_cert_path || !$thirdUser->pay_key_path){
            return error(40310, '没有配置支付证书');
        }
        return $this->writeCert($thirdUser, $payCert);
    }

    //写入证书
    private function writeCert($channelPay, $cert)
    {

        $pay_cert_path = PROGARM_ROOT . $channelPay['pay_cert_path'];
        $pay_key_path = PROGARM_ROOT . $channelPay['pay_key_path'];

        $dirname = dirname($pay_key_path); //获取证书的目录

        if (!is_dir($dirname)) {
            mkdir($dirname,0777,true);
        }

        if (!file_exists($pay_cert_path)) {
            //不存在就写入
            if (!$this->writeLocalCert($pay_cert_path, $cert['pay_cert'])) {
                Error::set(1, 'cert写入失败');
                return false;
            }
        }

        if (!file_exists($pay_key_path)) {
            //不存在就写入
            if (!$this->writeLocalCert($pay_key_path, $cert['pay_key'])) {
                Error::set(1, 'key写入失败');
                return false;
            }
        }

        return true;
    }

    private function writeLocalCert($filename,$txt){
        $file = fopen($filename, "w") or error(40310,'配置证书失败');
        fwrite($file, $txt);
        fclose($file);
        return true;
    }

}