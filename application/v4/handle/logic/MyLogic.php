<?php

namespace app\v4\handle\logic;

use app\v4\handle\query\MyQuery;
use app\v4\Services\BaseService;
use lib\SmsSend;
use lib\ValidPic;
use lib\ValidSMS;
use third\WXBizDataCrypt;
use third\WxBizMsgCrypt;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:28
 */
class MyLogic extends BaseService
{

    private $query;
    const AUTH_OK = 1; //refresh_token过期时间
    const APPLET = 2; //授权成功


    function __construct()
    {
        $this->query = new MyQuery();
    }

    //手机号绑定
    public function is_bind($channels, $params, $users)
    {
        $channel = $channels['channel'];
        $tel = $this->query->getTel($users, $channel);
        if (empty($tel['mobile'])) $is_bind = 0;
        else            $is_bind = 1;
        success(['is_bind' => $is_bind]);
    }


    public function bind_mobile($channels, $params, $users)
    {
        if (!isset($params['vcode']) || !isset($params['username'])) error(40000, '参数不全！');
        $channel = $channels['channel'];
        $code = $params['vcode'];
        $tel = $params['username'];
        $create = NOW - 600;
        $res = $this->query->getTel($users, $channel);
        if (!empty($res['mobile'])) error(40000, '用户已绑定手机号！');
        $resM = $this->query->getTelByTel($tel, $channel);
        if (!empty($resM['mobile'])) error(40000, '该手机号已被绑定！');
        $result = $this->query->getCode($tel, $channel, $create);
        if (empty($result[0]['code'])) error(40000, '验证码已无效！');
        if ($result[0]['code'] != $code) error(40000, '验证码错误！');
        $this->query->bindUser($tel, $channel, $users);
        success(array('operation' => 1));
    }

    public function bind_wx_mobile($channels, $params, $users)
    {
        //解密微信数据
        if (empty($shopId = encrypt($params['shop_id'], 3, false))) error(40000, '店铺ID错误');
        // 店铺授权
        $res = $this->query->getChannelInfoAndThirdUser($shopId, self::APPLET);
        if (empty($res)) error(48001, '该店铺小程序未授权');
        if ($res['status'] != self::AUTH_OK) error(48001, '该小程序未授权');

        if (empty($appid = $res->thirdUser['appid'])) error(48001, '小程序appid错误');
        $server = new WxServer;
        // 通过code获取session_key
        $sessionData = $server->getSessionKey($appid, $params['code']);

        $wxData = '';
        //获取解密信息
        $pc = new WXBizDataCrypt($appid, $sessionData['session_key']);
        $errCode = $pc->decryptData($params['encryptedData'], $params['iv'], $wxData);
        if ($errCode) error(50000, '数据解密错误');
        $wxData = filterEmoji($wxData);
        $wxData = json_decode($wxData, true);
        $tel = $wxData['phoneNumber'] ?? error("获取用户手机号失败!");

        $channel = $channels['channel'];
        $res = $this->query->getTel($users, $channel);
        if (!empty($res['mobile'])) error(40000, '用户已绑定手机号！');
        $resM = $this->query->getTelByTel($tel, $channel);
        if (!empty($resM['mobile'])) error(40000, '该手机号已被绑定！');
        $this->query->bindUser($tel, $channel, $users);
        success(array('operation' => 1));
    }

    public function login_code($channels, $params, $users)
    {
        if (!isset($params['username'])) error(40000, '参数不全！');
        $tel = $params['username'];
        $channel = $channels['channel'];
        $count = $this->query->CountCode($tel, strtotime(date('Y-m-d')), $channel);

        $vcode = isset($params['img_vcode']) ? $params['img_vcode'] : '';
        $code = isset($params['img_code']) ? $params['img_code'] : '';

        if (isMobile($tel)) {
            //验证IP
            $valid_model = new ValidSMS($channel);
            $valid_model->valid();//添加ip限制 同一个ip只能100个

            //若发送次数在3次到10次之间 发送验证码
            if ($count >= 3 && $count < 10) {
                if (!empty($vcode) && !empty($code)) {
                    $valid_pic = new ValidPic();
                    $res = $valid_pic->check($channel, $code, $vcode);
                    if ($res === false) {
                        $valid_model->decr();//减去ip限制
                        error(40000, '验证码错误');
                        //验证不通过
                    }
                } else {
                    $valid_model->decr();//减去ip限制
                    error(40001);
                }
            }
            if ($count >= 10) error(50000, '您今日发送验证码次数已用完！');
            $sms = new SmsSend;
            $code = rand(1000, 9999);
            $msg = '您的验证码是' . $code;
            $result = $sms->sendSms('', '', $channel, '', $tel, $msg);
            if ($result->errmsg == "OK") {
                $this->query->saveCode($code, $channel, $tel, strtotime(date('Y-m-d')));
                success(['operation' => 1]);
            }
            error(50000, '请重新发送验证码');
        }
        error(40000, '不正确的手机号！');

    }


    //获取动态图片验证码
    public function img_captcha($channels, $params, $users)
    {
        $channel = $channels['channel']; //渠道id

        if (!isset($params['img_code'])) error(40000, 'code必传!');
        $code = $params['img_code'];

        if (!is_string($code) || strlen($code) != 32) error(40000, 'code错误');

        $pic = new ValidPic();
        $pic->valid($channel);//拉取ip验证

        $checkCode = ''; //获取code
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPRSTUVWXYZ23456789';

        for ($i = 0; $i < 4; $i++) {
            $checkCode .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        $checkCode = strtoupper($checkCode);// 记录session

        $res = $pic->setCode($channel, $code, $checkCode);//获取

        if ($res) {
            $pic->ImageCode($checkCode, 60); //显示GIF动画
        } else {
            error(500, '图片获取失败');
        }

        die();
    }

    //获取个人中心配置
    public function info_config($channels, $users)
    {
        $channel = $channels['channel'];
        $res = $this->query->getChannel($channel);
        $data = [];
        if (isset($res['user_cover'])) {
            $data['user_cover'] = $res['user_cover'] ? getBucket('channel', 'user_cover', $res['user_cover']) : '';
        }
        //获取渠道配置是否开启推广中心
        if (isset($res['extension_status'])) {
            $data['extension_status'] = $res['extension_status'];
        }
        //判断用户是否有分销记录
        $res = $this->query->getUserDistribution($channel, $users);
        if (count($res) == 0) {//无分销记录
            $data['extension_record'] = 2;
        } else {//有记录
            $data['extension_record'] = 1;
        }
        //判断用户是否是当前店铺推广员及启用状态
        $res = $this->query->getUser($channel, $users);
        if ($res) {
            $data['is_extension_user'] = 1;//是推广员
            $data['extension_user_status'] = $res['status'] ?? 2;
        } else {
            $data['is_extension_user'] = 2;//不是推广员
        }
        //获取店铺推广员报名设置
        $res = $this->query->getExtensionConfig($channel);
        $data['is_apply'] = $res['is_apply'] ?? 1;//是否允许申请 1-允许 2-不允许
        $data['is_condition'] = $res['is_condition'] ?? 1;//1-无条件加入 2-需购买任意商品
        $data['is_review'] = $res['is_review'] ?? 1;//是否需要审核
        switch ($data['is_condition']) {
            case 1:
                $data['is_join'] = 1;//满足加入条件
                break;
            case 2:
                //查找用户购买记录有记录则满足否则不满足
                $res = $this->query->getUserOrder($channel, $users);
                if (count($res) > 0) {
                    $data['is_join'] = 1;//满足加入条件
                } else {
                    $data['is_join'] = 2;//不满足加入条件
                }
        }

        success($data);
    }

}