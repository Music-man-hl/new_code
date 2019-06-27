<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-14
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\Services;


use app\v4\model\Main\ThirdUser;

class WeixinPay extends BaseService
{

    public function setWeixinPay($channel)
    {

        $where = [
            'channel' => $channel,
            'status' => 1
        ];

        $channelPay = ThirdUser::field('appid,pay_mchid,pay_key,pay_cert_path,pay_key_path,release_status')->where($where)->find();

        if (empty($channelPay) || $channelPay['release_status'] == 0) error(50000, '商家未配置支付信息');

        if (!defined('WEIXIN_APPID')) define('WEIXIN_APPID', $channelPay['appid']);
        if (!defined('WEIXIN_MCHID')) define('WEIXIN_MCHID', $channelPay['pay_mchid']);
        if (!defined('WEIXIN_KEY')) define('WEIXIN_KEY', $channelPay['pay_key']);
        if (!defined('WEIXIN_APPSECRET')) define('WEIXIN_APPSECRET', '');//不需要设置
        if (!defined('WEIXIN_SSLCERT_PATH')) define('WEIXIN_SSLCERT_PATH', PROGARM_ROOT . $channelPay['pay_cert_path']);
        if (!defined('WEIXIN_SSLKEY_PATH')) define('WEIXIN_SSLKEY_PATH', PROGARM_ROOT . $channelPay['pay_key_path']);

        return $channelPay;

    }

    public function setWeixinPayCheck($data)
    {

        $channelPay = ThirdUser::where('channel', $data['channel_id'])->where('status', 1)
            ->field('appid,pay_cert_path,pay_key_path')->find();

        if (empty($channelPay)) error(50000, '商家未配置支付信息');
        $channelPay['pay_mchid'] = $data['pay_mchid'];
        $channelPay['pay_key'] = $data['pay_key'];

        if (!defined('WEIXIN_APPID')) define('WEIXIN_APPID', $channelPay['appid']);
        if (!defined('WEIXIN_MCHID')) define('WEIXIN_MCHID', $channelPay['pay_mchid']);
        if (!defined('WEIXIN_KEY')) define('WEIXIN_KEY', $channelPay['pay_key']);
        if (!defined('WEIXIN_APPSECRET')) define('WEIXIN_APPSECRET', '');//不需要设置
        if (!defined('WEIXIN_SSLCERT_PATH')) define('WEIXIN_SSLCERT_PATH', PROGARM_ROOT . $channelPay['pay_cert_path']);
        if (!defined('WEIXIN_SSLKEY_PATH')) define('WEIXIN_SSLKEY_PATH', PROGARM_ROOT . $channelPay['pay_key_path']);

        return $channelPay;
    }
}