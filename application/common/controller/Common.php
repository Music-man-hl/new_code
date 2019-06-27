<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2017/6/7
 * Time: 15:23
 */

namespace app\common\controller;

use lib\Redis;
use think\Controller;
use think\facade\Request;
use app\common\model;;
use app\common\model\Room;

class Common extends Controller
{

    protected $api_version;
    protected $users;
    protected $channels;
    protected $permissions;
    protected $all_param;
    protected $allow_request_type = [ 1=> 'GET',2 => 'POST',3=> 'PUT' ,4=> 'DELETE'];

    protected function access(){
        //这里控制登陆和请求方式  ['index'=>[ 'type'=>'GET' , 'lived'=>true|false ] ] //lived不存在时不验证users
    }

    public function initialize()
    {

        $access = $this->access();
        $action = Request::action();
        //0.取出模块
        $_parse_url_string  = substr(parse_url($_SERVER['REQUEST_URI'])['path'],1);
        if( empty($_parse_url_string) || substr_count($_parse_url_string,'/') > 1 ) error(40400); //路由错误
        //1.请求类型
        $request_type = Request::method();
        if( !in_array($request_type,$this->allow_request_type) ) error(40000,'请求类型错误');

        //2.0获取参数
        $data_method = strtolower( $request_type );
        $all_data    =  Request::$data_method();


        $this->all_param = $all_data;//获取所有参数
        //2.1校验签名
//        if ( !APP_DEBUG && !jsSignVerify( $all_data ) ) error(40301);

        //3.防刷ip
        ipFilter();
        //4.获取版本号
        $api_version = isset($all_data['api_version']) ? $all_data['api_version'] : '';//版本号必传
        $this->getVersion( trim($api_version) );
        //5.验证权限和登陆
        if (isset($access[$action])) {

            if ($access[$action]['type'] != $request_type)error(40000,'请求类型错误！');; //请求类型

            if (isset($access[$action]['lived'])) {  //登陆判断lived (true 必须登陆 false 尝试登陆但不报错:此目的为获取users)

                $token = isset($all_data['auth_access_token']) ? $all_data['auth_access_token'] : ''; //判断token是否过期
                if ( !$token && $access[$action]['lived'] === true) {
                    error(40000,'auth_access_token不能为空'); //token必须传递
                }
                if (!empty($token)) {
                    $shop_id = isset( $all_data['shop_id'] ) ?  $all_data['shop_id'] : 0;
                    if (empty($shop_id) || strlen($shop_id) > 10) {
                        error(40000, 'shop_id错误');
                    }
                    //验证token的合法性
                    if (!redis_prefix($token)) {
                        error(40302); //错误的token前缀 直接refresh
                    }
                    $token_value = Redis::get($token);
                    if(empty($token_value)) error(40302); //过期了
                    if($token_value['ce'] != $shop_id) error(40000, 'shop_id错误！');
                    $this->users = $token_value['u'];
                    if (empty($this->users)) {
                         error(50000, '用户信息不正确');
                    }
                    $sub_shop   = isset($all_data['sub_shop_id'])?$all_data['sub_shop_id']:0;
                    $this->channels = ['channel'=>$token_value['c'], 'sub_shop' =>$sub_shop ];
                }


            }
        }
        $channelInfoId = encrypt( $this->all_param['shop_id'],3,false);
        $res           =  Room::getRealChannel($channelInfoId);
        $this->all_param['channel'] = encrypt($res,3);
    }

    private function getVersion($api_version){
        if(!empty($api_version) && preg_match("/^[\.\d]*$/i",$api_version)){
            $api_version = 'V'.str_replace('.','_',$api_version);
            $version_dir = PROGARM_ROOT.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'index'.
                DIRECTORY_SEPARATOR.'handle'.DIRECTORY_SEPARATOR.$api_version;
            if(is_dir($version_dir)) {
                $this->api_version = "\app\index\handle\\{$api_version}\\";
                return true;
            }
        }

        error(40000,'api_version错误');
    }

    function _empty($name){
        error(40400,$name);
    }

}