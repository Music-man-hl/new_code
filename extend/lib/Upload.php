<?php
namespace lib;

use Upyun\Config;
use Upyun\Upyun;
use Upyun\Signature;
use Upyun\Util;
/**
 * 上传相关处理
 * X-Wolf
 * 2018-4-28
 */
class Upload
{
    const UPYUN_USER = 'feekrmicroshp'; //操作员名称

    const UPYUN_PWD  = 'vP3DfYWm9agi2v6I'; //密码

    /**
     * Rest方式上传又拍云
     * @param  string $bucket 域名Bucket
     * @param  string $path   绝路径及文件名
     * @param  source $file   上传的图片流
     */
    public static function upUpload($bucket,$path,$file)
    {
        if( !($bucket && $path && $file) ) {
            return '上传参数错误';
        }

        $configs = config('upyun.');
        if(false === strpos($bucket, '-pic')){
            $bucket .= '-pic';
        }
        if(!array_key_exists($bucket, $configs)) return 'bucket不存在';

        $config = new Config($configs[$bucket]['bucket'],self::UPYUN_USER,self::UPYUN_PWD);
        $client = new Upyun($config);
        $ret = $client->write($path,$file);
        return !empty($ret);
    }

    /**
     * Web直传到又拍云
     * @param String $bucket 	 域名Bucket
     * @param String $dir 		 一级目录
     * @param Array  $extConfigs 额外配置
     */
    public static function upWebUpload($bucket,$dir = 'default',$extConfigs = [] )
    {
        if(!$bucket) return '上传参数错误';

        $configs = config('upyun.');
        if(!array_key_exists($bucket, $configs)) return 'bucket不存在';

        $config = new Config($bucket,self::UPYUN_USER,self::UPYUN_PWD);
        $config->setFormApiKey($configs[$bucket]['form_api_key']);
        // 考虑点  1. 前缀   2. 规则  3. 回调  4. 操作员
        $data = [
            'save-key'				=>	'/'.$dir.'/{year}{mon}{day}/{hour}{min}{sec}{filemd5}{.suffix}',
            'expiration'			=>	time() + 3600*12, //请求的过期时间
            'bucket'				=>	$configs[$bucket]['bucket'], //空间名称
            'allow-file-type'		=>	'jpg,jpeg,png', //允许上传文件扩展名
            'content-length-range'	=>	'0,10240000', //5M上传文件大小
            'image-width-range'		=>	$configs[$bucket]['image-width-range'],
            'image-height-range'	=>	$configs[$bucket]['image-height-range'],
        ]; // 参考 : http://docs.upyun.com/api/form_api/#save-key
        if(!empty($extConfigs)) $data = array_merge($data,$extConfigs);

        $policy = Util::base64Json($data);
        $method = 'POST';
        $uri = '/' . $data['bucket'];
        $signature = Signature::getBodySignature($config, $method, $uri, null, $policy);
        return [
            'policy' 		=> 	$policy,
            'authorization' => 	$signature,
            'domain'		=>	$configs[$bucket]['domain'],
            'upload'		=>	'https://v0.api.upyun.com/'.$data['bucket'],
        ];
    }

    /**
     * 组合需要的图片url
     * @param $bucket
     * @return mixed
     */
    static function domain($bucket){
        $configs = config('upyun.');
        if( !array_key_exists($bucket, $configs) ) error(40000,'bucket不存在');
        return $configs[$bucket]['domain'];
    }

}


