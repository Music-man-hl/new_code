<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-09
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\controller;

use app\v3\model\Main\Channel;
use app\v3\model\Main\ChannelInfo;
use lib\Redis;
use think\Controller;
use think\facade\Cache;
use think\facade\Request;

class Base extends Controller
{

    protected $api_version;
    protected $users;
    protected $channels;
    protected $permissions;
    protected $all_param;
    protected $allow_request_type = [1 => 'GET', 2 => 'POST', 3 => 'PUT', 4 => 'DELETE'];

    protected function access()
    {
        //这里控制登陆和请求方式  ['index'=>[ 'type'=>'GET' , 'lived'=>true|false ] ] //lived不存在时不验证users
    }

    public function initialize()
    {
        !config('app_debug') && error_reporting(E_ALL ^ E_NOTICE);

        $access = $this->access();
        $action = Request::action();
        //1.请求类型
        $request_type = Request::method();
        if (!in_array($request_type, $this->allow_request_type)) error(40000, '请求类型错误');

        //2.0获取参数
        $data_method = strtolower($request_type);
        $all_data = Request::$data_method();

        $this->all_param = $all_data;//获取所有参数
        //2.1校验签名
//        if ( !APP_DEBUG && !jsSignVerify( $all_data ) ) error(40301);

        //3.防刷ip
        ipFilter();

        //5.验证权限和登陆
        if (isset($access[$action])) {

            if ($access[$action]['type'] != $request_type) error(40000, '请求类型错误！');; //请求类型

            if (isset($access[$action]['lived'])) {  //登陆判断lived (true 必须登陆 false 尝试登陆但不报错:此目的为获取users)

                $token = Request::param('auth_access_token'); //判断token是否过期
                if (!$token && $access[$action]['lived'] === true) {
                    error(40000, 'auth_access_token不能为空'); //token必须传递
                }
                if ($token) {
                    $shop_id = Request::param('shop_id');
                    if (empty($shop_id) || strlen($shop_id) > 10) {
                        error(40000, 'shop_id错误');
                    }
                    //验证token的合法性
                    if (!redis_prefix($token)) {
                        error(40302); //错误的token前缀 直接refresh
                    }
                    $token_value = Redis::get($token);
                    if (empty($token_value)) error(40302); //过期了
                    if ($token_value['ce'] != $shop_id) error(40000, 'shop_id错误！');
                    $this->users = $token_value['u'];
                    if (empty($this->users)) {
                        error(50000, '用户信息不正确');
                    }
                    $sub_shop = Request::param('sub_shop_id');
                    $this->channels = ['channel' => $token_value['c'], 'sub_shop' => $sub_shop];
                }


            }
        }
        $channelInfoId = encrypt(Request::param('shop_id'), 3, false);
        $data = ChannelInfo::where('id', $channelInfoId)->where('status', 1)->field('channel')->select()->toArray();
        if (count($data) > 1 || count($data) == 0) {
            $channelId = false;
        } else {
            $channelId = $data[0]['channel'];
        }
        $this->all_param['channel'] = encrypt($channelId, 3);

        //Ne
        $channel = [
            'channelId' => $channelId,
            'shopId' => encrypt(Request::param('sub_shop'), 4, false)
        ];

        //缓存DB_CONFIG
        $dbConfigs = Cache::store('redis')->remember(redis_prefix().'dbConfigs', function () {
            return $this->getDbConfigs();
        });

        request()->channel = $channel;
        request()->dbConfig = $dbConfigs[$channel['channelId']];

    }

    public function getDbConfigs()
    {
        $data = Channel::field('id,db_id')->with('database')->select()->toArray();
        $db_data = [];
        foreach ($data as $v) {
            $db_data[] = [
                'id'=>$v['id'],
                'db_config'=>"mysql://{$v['username']}:{$v['password']}@{$v['hostname']}:{$v['hostport']}/{$v['dbname']}"
            ];
        }
        return array_column($db_data, 'db_config', 'id');
    }

    function _empty($name)
    {
        error(40400, $name);
    }

}
