<?php 
namespace lib;

//require __DIR__ . "/vendor/autoload.php";

use app\v3\model\Main\MessageSign;
use app\v3\model\Main\MessageTpl;
use Qcloud\Sms\SmsSingleSender;
use Qcloud\Sms\SmsMultiSender;
use Qcloud\Sms\SmsVoiceVerifyCodeSender;
use Qcloud\Sms\SmsVoicePromptSender;
use Qcloud\Sms\SmsStatusPuller;
use Qcloud\Sms\SmsMobileStatusPuller;
use think\Db;
/**
 * 房型相关处理
 * X-Wolf
 * 2018-4-28
 */
class SmsSend
{
    public  function sendSms($type,$proid,$channel,$params,$tel,$tpl='')
    {
        if(empty($type))
        {
            //纯粹为了发送短信验证码多出来的步骤
        }
        else
        {
            $sql   = 'SELECT `tpl` FROM message_tpl WHERE `msg_type`=:type AND `product_type`=:proid ';
            $param = array('type'=>$type,'proid'=>$proid);
            $data  = MessageTpl::query($sql,$param);
            if(empty($data)) return false;
            $msg   = $data[0]['tpl'];
            $tpl   = $this->changeParams($msg,$params);
        }
        $fix   = $this->getFix($channel);
        $ssender = new SmsSingleSender(config('SDKAppID'),config('AppKey'));
        $result = $ssender->send(0, "86", $tel,$fix.$tpl, "", "");
        $rsp = json_decode($result);
        return $rsp;



    }

    protected function  changeParams($msg,$data)
    {
        foreach ($data as $k=>$v)
        {
            $msg = str_replace($k,$v,$msg);
        }
        return $msg;
    }

    protected function getFix($channel)
    {
        $sql = 'SELECT `sign` FROM message_sign WHERE `channel`=:channel AND status=0 ';
        $data  = MessageSign::query($sql,array('channel'=>$channel));
        if(empty($data)) error(40000,'该shop_id不存在可使用签名!');
        return $data[0]['sign'];
    }
}


