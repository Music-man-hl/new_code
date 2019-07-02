<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/22
 * Time: 12:01
 */

namespace app\index\controller;

use think\Db;
use lib\Redis;
use think\facade\Request;
use lib\Error;

class Weixin
{
    const TOKEN_EXPIRE = 7000;//token过期时间
    private $wxappid = '';
    private $wxsecret = '';
    //微信公众号关注
    public function index(){
        $request_type = Request::method();
        $data_method =  strtolower( $request_type );
        $all_data    =  Request::$data_method();
        $echostr = isset($all_data['echostr'])?$all_data['echostr']:'';
        if(!empty($echostr)){
            echo $echostr;die;
        }

        $postObj = simplexml_load_string($GLOBALS["HTTP_RAW_POST_DATA"],
            'SimpleXMLElement', LIBXML_NOCDATA);
//        $postObj = json_decode('{"ToUserName":"gh_8c87eb3cb684","FromUserName":"oUzqAt_FFRIId6va_0S15aoQ72ZQ","CreateTime":"1541489512","MsgType":"event","Event":"subscribe","EventKey":{},"Encrypt":"cFIotybMtXUy2eQHWxAr5SwgRh6xgOMjCqxG6Y+\/4URQAXBKaGAye1j+f8JLmVMRQwI\/uNI\/b50efrO4l6B+SKGqmpbYdMdfCAjW3o4COqXNwi94lZSxR+7cOdFBngAFzF4TGAHKjZ3WC8eNMSK6VppKS1K2Td\/rmXEnNZirk1XW0xEzoP0HcS3T5GsE3ak8KB8zkuhlVEx\/E9hlL2wws76GQgRAKvTcsCLW15zWRxqa4K187Vy68vC9mvzgsT2D+ETbvPqVQlozbAJUGOI+e+\/\/mnFWo2JAiow2\/TYaop55qckGcUy3HSgQ1rPCGMxxQt7dRJrMKXrRiLGXEYBVsdDYG5n9roG\/+0Jxia0UvgYRzGEiZfgcpMjVh0EhHPGVPIGL07e7J4lHJZDXrCD27qVBZp4Ejs3F2RqMxtkF2nk="}');
        $openid = $postObj -> FromUserName;
        $busId = $postObj -> ToUserName;
        $Event = strtolower($postObj -> Event);
        $wxData= Db::query('SELECT * from weixin_param WHERE busid=:busid ',['busid'=>$busId]);
        if(!isset($wxData[0])) die('未识别用户');
        $this->wxappid = $wxData[0]['appid'];
        $this->wxsecret = $wxData[0]['secret'];
        if (strtolower($Event) == 'subscribe')
        {
            $personInfo = Db::query('SELECT * from weixin_user WHERE openid=:openid ',['openid'=>$openid]);
            if(empty($personInfo))
            {
                $userInfo   = $this->getUserInfoByOpenid($openid,$busId);
                $data       = array(
                    'appid'       =>$this->wxappid,
                    'openid'      =>$openid,
                    'headimgurl'  =>$userInfo['headimgurl'],
                    'nickname'    =>$userInfo['nickname'],
                );
                Db::name('weixin_user')->insert($data);
            }
        }
    }


    function getUserInfoByOpenid($openid)
    {
        $URL = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN";

        $access_token = $this->getToken();

        $URL = sprintf($URL,$access_token,$openid);

        $response = $this->getcurl($URL);

        $userinfo = (array)json_decode($response,true);

        return $userinfo;
    }

    function test()
    {
        Redis::set($this->wxappid.'_token','',7000);die('ok');
    }

    function getToken()
    {
        $token =   Redis::get($this->wxappid.'_token');
        if (empty($token))
        {
            $url = sprintf("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s",$this->wxappid, $this->wxsecret);
            $res = json_decode(curl_file_get_contents($url,'',array(),2),true);
            if (isset($res['access_token'])) {
                Redis::set($this->wxappid.'_token',$res['access_token'],7000);
                $token = $res['access_token'];
            }
        }
        return $token;
    }

    function getcurl($url, $postValue = array(), $json = FALSE, $type = "POST")
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 14);
        if ($postValue) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postValue);
        }
        if ($json) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($postValue)));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = 'Errno' . curl_error($ch);
        }
        curl_close($ch);

        return $response;
    }



}