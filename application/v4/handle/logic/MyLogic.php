<?php

namespace app\v4\handle\logic;

use app\v4\handle\query\MyQuery;
use app\v4\Services\BaseService;
use lib\SmsSend;
use lib\ValidPic;
use lib\ValidSMS;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:28
 */
class MyLogic extends BaseService
{

    private $query;


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
    public function info_config($channels)
    {
        $channel = $channels['channel'];
        $res = $this->query->getChannel($channel);
        $data = [];
        if (isset($res['user_cover'])) {
            $data['user_cover'] = $res['user_cover'];
        }
        //获取渠道配置是否开启推广中心
        if (isset($res['extension_status'])) {
            $data['extension_status'] = $res['extension_status'];
        }
        success($data);
    }

}