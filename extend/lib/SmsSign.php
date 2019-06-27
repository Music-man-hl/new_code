<?php 
namespace lib;
/**
 *
 * Class SmsSign
 * @package lib
 */
class SmsSign
{

    const POST_URL = 'https://yun.tim.qq.com/v5/tlssmssvr/';

    /**
     * 生成随机数
     * @return int 随机数结果
     */
    static private function getRandom()
    {
        return rand(100000, 999999);
    }

    /**
     * 生产签名
     * @param $random 随机数
     * @param $time 时间戳
     * @return string
     */
    static private function makeSig($random,$time){
        $appkey = config('AppKey');
        return hash("sha256", "appkey={$appkey}&random={$random}&time={$time}" , FALSE);
    }

    /**
     * 组装要发送的url
     * @param $method 请求的方法
     * @param $random 随机数
     * @return string
     */
    static private function url($method,$random){
        $appid = config('SDKAppID');
        return self::POST_URL. $method . "?sdkappid=" . $appid . "&random=" . $random;
    }


    /**
     * 发送请求
     *
     * @param string $url      请求地址
     * @param array  $dataObj  请求内容
     * @return string 应答json字符串
     */
    static private function sendCurlPost($url, $dataObj)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataObj));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $ret = curl_exec($curl);
        if (false == $ret) {
            // curl_exec failed
            $result = "{ \"result\":" . -2 . ",\"errmsg\":\"" . curl_error($curl) . "\"}";
        } else {
            $rsp = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "{ \"result\":" . -1 . ",\"errmsg\":\"". $rsp
                    . " " . curl_error($curl) ."\"}";
            } else {
                $result = $ret;
            }
        }

        curl_close($curl);

        return $result;
    }

    /**
     * 添加签名
     * @param $pic Base64的图片字符串
     * @param $text 签名内容，不带【】
     * @param string $remark 签名备注
     * @return string
     */
    static function add_sign($text,$pic='',$remark=''){

        $random = self::getRandom();
        $curTime = NOW;
        $wholeUrl = self::url(__FUNCTION__,$random);//组合的url

        // 按照协议组织 post 包体
        $data = new \stdClass();

        $data->pic = $pic;
        $data->remark = $remark;
        $data->text = $text;
        $data->sig = self::makeSig($random,$curTime);
        $data->time = $curTime;

        return self::sendCurlPost($wholeUrl, $data);

    }

    /**
     * 修改签名
     * @param $sign_id 待修改的签名对应的签名 id
     * @param $pic Base64的图片字符串
     * @param $text 签名内容，不带【】
     * @param string $remark 签名备注
     * @return string
     */
    static function mod_sign($sign_id,$text,$pic='',$remark=''){

        $random = self::getRandom();
        $curTime = NOW;
        $wholeUrl = self::url(__FUNCTION__,$random);//组合的url

        // 按照协议组织 post 包体
        $data = new \stdClass();

        $data->pic = $pic;
        $data->remark = $remark;
        $data->text = $text;
        $data->sig = self::makeSig($random,$curTime);
        $data->time = $curTime;
        $data->sign_id = (int)$sign_id;

        return self::sendCurlPost($wholeUrl, $data);

    }

    /**
     * 删除签名
     * @param array|int $sign_id 签名id 数组或者int
     * @return string
     */
    static function del_sign($sign_id){

        $random = self::getRandom();
        $curTime = NOW;
        $wholeUrl = self::url(__FUNCTION__,$random);//组合的url

        // 按照协议组织 post 包体
        $data = new \stdClass();

        $data->sig = self::makeSig($random,$curTime);
        $data->time = $curTime;
        $data->sign_id = $sign_id;

        return self::sendCurlPost($wholeUrl, $data);
    }

    /**
     * 获取签名
     * @param array|int $sign_id 签名id 数组或者int
     * @return string
     */
    static function get_sign($sign_id){

        $random = self::getRandom();
        $curTime = NOW;
        $wholeUrl = self::url(__FUNCTION__,$random);//组合的url

        // 按照协议组织 post 包体
        $data = new \stdClass();

        $data->sig = self::makeSig($random,$curTime);
        $data->time = $curTime;
        $data->sign_id = $sign_id;

        return self::sendCurlPost($wholeUrl, $data);

    }

}


