<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/6/12
 * Time: 18:38
 */

namespace lib;

use lib\Redis;
class ValidPic
{
    private $expire_count = 86400;//一天
    private $expire = 600;//10分钟
    private $ipCount = 100; //最多请求100次

    /**
     *ImageCode 生成包含验证码的GIF图片的函数
     *@param $string 字符串
     *@param $width 宽度
     *@param $height 高度
     **/
    function ImageCode($string='',$width=75,$height=25){
        $authstr=$string?$string:((time()%2==0)?mt_rand(1000,9999):mt_rand(10000,99999));
        $board_width=$width;
        $board_height=$height;
        // 生成一个32帧的GIF动画
        for($i=0;$i<32;$i++){
            ob_start();
            $image=imagecreate($board_width,$board_height);
            imagecolorallocate($image,0,0,0);
            // 设定文字颜色数组
            $colorList[]=ImageColorAllocate($image,15,73,210);
            $colorList[]=ImageColorAllocate($image,0,64,0);
            $colorList[]=ImageColorAllocate($image,0,0,64);
            $colorList[]=ImageColorAllocate($image,0,128,128);
            $colorList[]=ImageColorAllocate($image,27,52,47);
            $colorList[]=ImageColorAllocate($image,51,0,102);
            $colorList[]=ImageColorAllocate($image,0,0,145);
            $colorList[]=ImageColorAllocate($image,0,0,113);
            $colorList[]=ImageColorAllocate($image,0,51,51);
            $colorList[]=ImageColorAllocate($image,158,180,35);
            $colorList[]=ImageColorAllocate($image,59,59,59);
            $colorList[]=ImageColorAllocate($image,0,0,0);
            $colorList[]=ImageColorAllocate($image,1,128,180);
            $colorList[]=ImageColorAllocate($image,0,153,51);
            $colorList[]=ImageColorAllocate($image,60,131,1);
            $colorList[]=ImageColorAllocate($image,0,0,0);
            $fontcolor=ImageColorAllocate($image,0,0,0);
            $gray=ImageColorAllocate($image,245,245,245);
            $color=imagecolorallocate($image,255,255,255);
            $color2=imagecolorallocate($image,255,0,0);
            imagefill($image,0,0,$gray);
            $space=15;// 字符间距
            if($i>0){// 屏蔽第一帧
                $top=0;
                for($k=0;$k<strlen($authstr);$k++){
                    $colorRandom=mt_rand(0,sizeof($colorList)-1);
                    $float_top=rand(0,4);
                    $float_left=rand(0,3);
                    imagestring($image,6,$space*$k,$top+$float_top,substr($authstr,$k,1),$colorList[$colorRandom]);
                }
            }
            for($k=0;$k<20;$k++){
                $colorRandom=mt_rand(0,sizeof($colorList)-1);
                imagesetpixel($image,rand()%70,rand()%15,$colorList[$colorRandom]);

            }
            // 添加干扰线
            for($k=0;$k<3;$k++){
                $colorRandom=mt_rand(0,sizeof($colorList)-1);
                $todrawline=1;
                if($todrawline){
                    imageline($image,mt_rand(0,$board_width),mt_rand(0,$board_height),mt_rand(0,$board_width),mt_rand(0,$board_height),$colorList[$colorRandom]);
                }else{
                    $w=mt_rand(0,$board_width);
                    $h=mt_rand(0,$board_width);
                    imagearc($image,$board_width-floor($w / 2),floor($h / 2),$w,$h, rand(90,180),rand(180,270),$colorList[$colorRandom]);
                }
            }
            imagegif($image);
            imagedestroy($image);
            $imagedata[]=ob_get_contents();
            ob_clean();
            ++$i;
        }
        $gif=new GIFEncoder($imagedata);
        header('Content-type:image/gif');
        echo $gif->GetAnimation();
    }



    //这个验证如果多过请求次数就不能再请求了
    function valid($channel) {

        $ipKey = $this->getIPlimitKey($channel);

        $ipTtl = Redis::ttl($ipKey);

        if($ipTtl < 0){ // 没有就生成
            Redis::set($ipKey,1,$this->expire_count);
        }else{

            $ipVal = Redis::get($ipKey);

            if( (int)$ipVal > $this->ipCount ) {
                error(500,'请求太多了哦！');
            } else {
                Redis::incr($ipKey);
            }

        }

    }
    //获取图片拉取限制的key
    private function getIPlimitKey($channel){
        $reIP = getIp();
        return redis_prefix().'vaild:ip:'. md5($reIP);
    }

    //获取key
    private function getKey($channel,$code){
        return redis_prefix().'vaild:'. $code;
    }



    //验证码检查
    function check($channel,$code,$vcode) {
        $key = $this->getKey($channel,$code);
        $value = Redis::get($key);
        if(empty($value)) return false;
        Redis::del($key);//清理
        return strtoupper($value) == strtoupper($vcode) ;
    }

    //设置code
    function setCode($channel,$code,$value) {
        $key = $this->getKey($channel,$code);
        return Redis::set($key,$value,$this->expire);
    }

}