<?php

namespace app\v6\handle\logic;

use app\v6\handle\query\AuthQuery;
use app\v6\handle\validate\auth\WxLogin;
use app\v6\Services\BaseService;
use lib\Redis;
use lib\Upload;
use third\S;
use third\WxBizDataCrypt;

/**
 * 授权Logic
 * X-Wolf
 * 2018-6-25
 */
class AuthLogic extends BaseService
{

    const TOKEN_EXPIRE = 3600;
    const REFRESH_EXPIRE = 864000;
    const AUTH_NO = 0;      //token过期时间
    const AUTH_OK = 1; //refresh_token过期时间
    const WECHAT = 1; //取消授权
    const APPLET = 2; //授权成功
    private $query;

    function __construct()
    {
        $this->query = new AuthQuery();
    }

    // 登录(小程序)
    public function wxLogin($params)
    {
        $data = filterData($params, ['shop_id', 'code', 'rawData', 'signature', 'encryptedData', 'iv', 'device']);
        checkValidate($data, WxLogin::class);

        if (empty($shopId = encrypt($data['shop_id'], 3, false))) error(40000, '店铺ID错误');
        // 店铺授权
        $res = $this->query->getChannelInfoAndThirdUser($shopId, self::APPLET);
        if (empty($res)) error(48001, '该店铺小程序未授权');
        if ($res['status'] != self::AUTH_OK) error(48001, '该小程序未授权');

        if (empty($appid = $res->thirdUser['appid'])) error(48001, '小程序appid错误');
        $channel = $res['channel'];
        $channelInfoId = $res['id'];
        $data = array_map('trim', $data);
        $server = new WxServer;
        // 通过code获取session_key
        $sessionData = $server->getSessionKey($appid, $data['code']);

        $wxData = '';
        //获取解密信息
        $pc = new WxBizDataCrypt($appid, $sessionData['session_key']);
        $errCode = $pc->decryptData($data['encryptedData'], $data['iv'], $wxData);
        if ($errCode) error(50000, '数据解密错误');
        S::recordLog('Appid:' . $appid . ' Channel:' . $channel . ' Data:' . $wxData);
        $wxData = filterEmoji($wxData);
        $wxData = json_decode($wxData, true);
        if (!isset($wxData['unionId'])) error(48001, '该小程序未绑定开放平台');
        if (empty($unionId = $wxData['unionId'])) error(48001, '无法获取小程序的unionid');
        if ($wxData['watermark']['appid'] != $appid) error(48001, '小程序appid不对应');
        if ($wxData['openId'] != $sessionData['openid']) error(48001, '小程序openid不对应');

        // 判断该用户是否存在
        $res = $this->query->existUser($wxData['unionId'], $channel);

        if (empty($res)) {
            // 添加
            $bucket = '';//头像bucket
            $pic = '';//图片
//            S::log([ 'data'=>$wxData ],'error');
            if (isset($wxData['avatarUrl']) && !empty($wxData['avatarUrl'])) {
                //有头像
                $bucket = 'wxphoto-pic';
//                S::log([ 'url'=>$wxData['avatarUrl'] ],'error');
                $pic = $this->upPic($wxData['avatarUrl'], $bucket, $channel);
//                S::log([ 'pic'=>$pic ],'error');
                if (empty($pic)) {
                    $bucket = '';
                }
            }

            $wxData['device'] = $data['device'];
            $wxData['bucket'] = $bucket;
            $wxData['pic'] = $pic;

            $ret = $this->query->addUser($channel, $wxData);
            if (!$ret) {
                error(50000, '添加用户失败');
            }
            $user = $ret;
        } else {
            if ($res['stat'] != 1) error(48001, '对不起,您已被禁用');
            $userData = [
                'logins' => $res['logins'] + 1,
                'last_login' => NOW,
                'reg_from' => self::APPLET,
                'last_login_from' => self::APPLET,
                'create_time' => NOW,
                'unionid' => $wxData['unionId'],
                'channel' => $channel,
                'device' => $data['device'],
            ];
            // 进行更新
            $ret = $this->query->updateUser($res['id'], $channel, $userData, $wxData);
            if (!$ret) {
                error(50000, '更新用户失败');
            }
            $user = $res['id'];
        }

        // 将数据放入token中   生成token
        $auth_token_key = redis_prefix() .'token:'. md5(getMicroTime() . '-' . getRand());
        $refresh_token_key = redis_prefix() .'token:'. md5(getMicroTime() . '-' . getRand());
        $auth_token = [
            'u' => $user,   //用户id
            'c' => $channel, //原始店铺id
            'ce' => encrypt($channelInfoId, 3) //店铺id(加密)
        ];
        $refresh_token = ['u' => $user]; //用户id

        if (Redis::set($auth_token_key, $auth_token, self::TOKEN_EXPIRE) && Redis::set($refresh_token_key, $refresh_token, self::REFRESH_EXPIRE)) {
            success(['auth_access_token' => $auth_token_key, 'auth_refresh_token' => $refresh_token_key, 'expire_in' => self::TOKEN_EXPIRE]);
        }

        error(50000, '登录失败');
    }

    private function upPic($avatarUrl, $bucket, $channel)
    {
        $urlPath = '';

        $stream = curl_file_get_contents($avatarUrl);
        if ($stream) { //stream 为图片资源

            $urlPath = '/' . encrypt($channel, 3) . '/' . date('Ymd') . '/' . md5(NOW . $avatarUrl) . mt_rand(1000000, 9999999) . '.jpg';

            $res = Upload::upUpload($bucket, $urlPath, $stream);
//            S::log(['res_pic'=>$res],'error');
            if (!$res) {
                error(50000, '上传头像失败');
            }
        }

        return $urlPath;
    }

    public function auth_refresh_token($params)
    {
        $data = filterData($params, ['shop_id', 'auth_refresh_token', 'sub_shop_id']);
        $refresh_token = $data['auth_refresh_token'];
        $getrefreshArray = Redis::get($refresh_token);
        if (empty($getrefreshArray)) error(40303);

        $fuser_data = $this->query->getUserById($getrefreshArray['u']);
        if (empty($fuser_data)) error(40000, '获取用户信息错误');

        $res = $this->query->getChannelInfoAndThirdUser(encrypt($data['shop_id'], 3, false), self::APPLET);
        if (empty($res)) error(40000, '获取渠道信息错误');
        if ($fuser_data['channel'] != $res['channel']) error(40000, 'shopid错误!');


        $auth_token = [
            'u' => $getrefreshArray['u'],   //用户id
            'c' => $fuser_data['channel'], //原始店铺id
            'ce' => encrypt($res['id'], 3) //店铺id(加密)
        ];
        $auth_token_key = redis_prefix() .'token:'. md5(getMicroTime() . '-' . getRand());
        if (Redis::set($auth_token_key, $auth_token, self::TOKEN_EXPIRE)) {
            success(['auth_access_token' => $auth_token_key, 'auth_refresh_token' => $refresh_token, 'expire_in' => self::TOKEN_EXPIRE]);
        }
        error(50000, 'token刷新失败');
    }


}