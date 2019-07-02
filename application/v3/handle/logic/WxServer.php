<?php

namespace app\v3\handle\logic;

use app\v3\model\Main\ThirdComponentData;
use app\v3\Services\BaseService;
use lib\MyLog;
use lib\Redis;
use third\S;

/**
 * 第三方服务
 * X-Wolf
 * 2018-6-25
 */
class WxServer extends BaseService
{

    const EXPIRE_TIME = 7100;

    // 获取登录session_key
    public function getSessionKey($appid, $code)
    {
        $oldapi = $api = 'https://api.weixin.qq.com/sns/component/jscode2session?appid=%s&js_code=%s&grant_type=authorization_code&component_appid=%s&component_access_token=%s';

        $componentAppId = config('component_app_id');
        $componentAccessToken = $this->getComponentAccessToken($componentAppId);
        $api = sprintf($api, $appid, $code, $componentAppId, $componentAccessToken);
        MyLog::debug("1api:" . $api);
        $res = json_decode(curl_file_get_contents($api), true);
        if (!$res || !is_array($res)) {
            error(48002, '获取登录session_key失败');
        }

        if (isset($res['openid'])) {
            return $res;
        }

        if (isset($res['errcode'])) {
            //如果是权限问题，就再拉取一下token
            $componentAccessToken = $this->getComponentAccessToken($componentAppId, true);
            $api = sprintf($oldapi, $appid, $code, $componentAppId, $componentAccessToken);
//            MyLog::debug("2api:".$api);
            $res = json_decode(curl_file_get_contents($api), true);
//            MyLog::debug('2err:'.json_encode($res));
            if (!$res || !is_array($res)) {
                error(48002, '获取登录session_key失败');
            }

            if (isset($res['openid'])) {
                return $res;
            }
        }

        error(48002, 'key:' . $res['errcode'] . $res['errmsg']);
    }


    // 获取第三方component_access_token
    private function getComponentAccessToken($componentAppId, $force_refresh = false)
    {
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
        $key = makeThirdRedisKey('component_access_token');

        if (!$force_refresh) { //如果不是强制刷新，就重新拉取

            $componentAccessToken = Redis::get($key);
            if ($componentAccessToken) return $componentAccessToken;

//            $component = new Component;
//            $obj = $component->getAccessToken();
//            if($obj && ($obj->expire_time + self::EXPIRE_TIME > time() ) ){
//                return $obj->value;
//            }

        }

        $componentAppSecret = config('component_app_secret');
        $ticket = $this->getVerifyTicket();

        if ($ticket) {
            $data = [
                'component_appid' => $componentAppId,
                'component_appsecret' => $componentAppSecret,
                'component_verify_ticket' => $ticket
            ];

            $ret = json_decode(curl_file_get_contents($api, json_encode($data)), true);

            if (is_array($ret) && !empty($ret) && isset($ret['component_access_token']) && !empty($ret['component_access_token'])) {
                // 主动刷新/被动刷新 Token(定时任务,将最新token 刷进数据库)
                Redis::set($key, $ret['component_access_token'], self::EXPIRE_TIME);

//				$component = new Component;
//				$res = $component->setAccessToken($ret['component_access_token']);
//				if(false === $res) error(48002);

                return $ret['component_access_token'];
            }
            error(48002, $ret['errcode'] . ':' . $ret['errmsg']);
        }
        error(48002);
    }

    // 获取推送Ticket
    private function getVerifyTicket()
    {
        $key = makeThirdRedisKey('ticket');
        $ticket = Redis::get($key);
        if ($ticket) return $ticket;

        $value = ThirdComponentData::where('name','component_verify_ticket')->value('value');
        if ($value) {
            $ticket = $value;
        }

        return $ticket;
    }

    // 获取商家的access_token

    public function sender($tpl)
    {
        $api = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=';

        $accessToken = $this->authAccessToken($tpl['appid']);
        if (!$accessToken) {
            S::recordLog('发送模版消息 - 获取授权方的access_token失败 appid:' . $tpl['appid']);
            return false;
        }

        unset($tpl['appid']);

        return json_decode(curl_file_get_contents($api . $accessToken, json_encode($tpl)), true);
    }

    // 通过refresh_token刷新access_token

    public function authAccessToken($appid)
    {
        $accessTokenKey = makeThirdRedisKey('access_token' . $appid);
        $authAccessToken = Redis::get($accessTokenKey);
        if ($authAccessToken) return $authAccessToken;

        $refreshTokenKey = makeThirdRedisKey('refresh_token' . $appid);
        $authRefreshToken = Redis::get($refreshTokenKey);
        if ($authRefreshToken) {
            $authAccessToken = $this->refreshAuthRefreshToken($appid, $authRefreshToken);
            if ($authAccessToken) {
                Redis::set($accessTokenKey, $authAccessToken, self::EXPIRE_TIME);
                return $authAccessToken;
            }
        }

        // 线上环境 从数据库中获取refresh_token
        $auth = new Authorizer();
        $ret = $auth->getRefreshTokenByAppid($appid);
        if ($ret) {
            $authRefreshToken = $ret->refresh_token;
            $authAccessToken = $this->refreshAuthRefreshToken($appid, $authRefreshToken);
            if ($authAccessToken) {
                Redis::set($accessTokenKey, $authAccessToken, self::EXPIRE_TIME);
                return $authAccessToken;
            }
        }
        return false;
    }

    // 发送模版消息

    private function refreshAuthRefreshToken($authAppId, $refreshToken)
    {
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token=';
        $componentAppId = config('component_app_id');
        $componentAccessToken = $this->getComponentAccessToken($componentAppId);
        if (!$componentAccessToken) return false;

        $api .= $componentAccessToken;
        $data = [
            'component_appid' => $componentAppId,
            'authorizer_appid' => $authAppId,
            'authorizer_refresh_token' => $refreshToken
        ];
        $ret = json_decode(curl_file_get_contents($api, json_encode($data)), true);
        if (!empty($ret) && is_array($ret)) {
            return $ret['authorizer_access_token'];
        }
        return false;
    }
}