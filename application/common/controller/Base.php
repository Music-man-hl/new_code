<?php
namespace app\common\controller;

use think\Controller;
use lib\Redis;
use think\facade\Request;

use app\common\model\Channel;
/**
 * 微信第三方服务基类
 * X-Wolf
 * 2018-4-16
 */
class Base extends Controller
{
    protected $requestAllow = [1 => 'GET',2 => 'POST',3 => 'PUT',4 => 'DELETE'];

    protected $userInfo,$channels,$permissions,$all_param;


    protected function initialize()
    {
        defined('LOG_SWITCH') or define('LOG_SWITCH', true); 
    }

    // 前端验证模式
    protected function authValidation()
    {  
        $url = ltrim(Request::baseUrl(),'/');
        if(empty($url) || substr_count($url,'/') > 1) error(40000,'路由错误');

        $requestMethod = Request::method();  
        if(!in_array($requestMethod, $this->requestAllow,true)) error(40301);

        $data = Request::$requestMethod();
        $this->all_param = $data;
//        if(!APP_DEBUG && !jsSignVerify($data)) error(40301);

        ipFilter();

        $action = Request::action();
        $access = $this->access();

        if(isset($access[$action])){
            if($access[$action]['type'] != $requestMethod) error(40000,'请求类型错误！');

            if(isset($access[$action]['lived'])){
                $token = isset($data['auth_token']) ? $data['auth_token'] : '';

                if(!$token && $access[$action]['lived'] === true){
                    error(40000,'auth_token不能为空');
                }

                if($token){
                    if (!redis_prefix($token)) {
                        error(40302); 
                    }

                    $tokenValue = Redis::get($token);
                    if(!empty($tokenValue)){
                        $shopId = isset($data['shop_id']) ? $data['shop_id'] : 0;
                        if(!$shopId || strlen($shopId) > 10){
                            error(40000, 'shop_id错误');
                        }

                        $shopId = encrypt($shopId,3,false);
                        if(!$shopId) error(40000, 'shop_id错误！');
                        if(!isset($tokenValue['channel'][$shopId])){
                            error(40000,'没有找到店铺信息');
                        }

                        $this->userInfo = $tokenValue['user'];
                        if(empty($this->userInfo) || !isset($this->userInfo['id']) || empty($this->userInfo['id'])){
                            error(50000, '用户信息不正确');
                        }

                        $this->channels  =  [
                                                'channel'   => $shopId,
                                                'sub_shop'  => $tokenValue['channel'][$shopId]['sub_shop'],
                                                'mult_shop' => $tokenValue['channel'][$shopId]['mult_shop']
                                            ];
                        $this->permissions = $tokenValue['channel'][$shopId]['permissions'];
                        if(empty($this->permissions)) error(40306,'权限为空');

                        $requestTypeKey = array_search($requestMethod, $this->requestAllow);
                        if(!isset($this->permissions[$url]) || false === strpos((string)$this->permissions[$url], (string)$requestTypeKey) ){
                            error(40306);
                        }

                    }else{
                        error(40302);
                    }
                }
            }
            
        }else{
            error(40400);
        }

    }

    // 接口验证模式
    protected function keyValidation()
    {
        $apiKey = '3f3c6c1cef4525e1c0cc41e28dc3b1b9'.APP_EVN;
        $allowIps = ['127.0.0.1'];

        $key = Request::get('key','','trim');
        if(!$key) error(4000,'请输入秘钥');        
        if(strcmp($apiKey, $key) !== 0) error(40000,'缺少接口Key');

        $clientIp = Request::ip();
        if(!in_array($clientIp, $allowIps,true)) error(48000,'IP不在白名单内');

        $requestMethod = Request::method(); 
        if(!in_array($requestMethod, $this->requestAllow,true)) error(40301);

        $this->all_param = Request::$requestMethod();
        $this->channels['channel'] = isset($this->all_param['shop']) ? $this->all_param['shop'] : 0;
    }

    // 空操作
    public function _empty()
    {
        echo '空操作';
    }

}