<?php
namespace third;

use third\WxBizMsgCrypt;
/**
 * 常用加解密操作
 */
class EncryptAndDecrypt
{

	private static $WxMsgCrypt = null;


	// 配置类
	private static function getInstance()
	{

		if(is_null(self::$WxMsgCrypt)){


			self::$WxMsgCrypt = new WxBizMsgCrypt(config('msg_verify_token'), config('msg_encryption_decryption_key'), config('component_app_id'));
		}
		return self::$WxMsgCrypt;
	}

	//加密数据
	public static function encryptData($text, $timeStamp, $nonce, $encryptMsg)
	{
		// 参数验证
		$errCode =  self::getInstance()->encryptMsg($text, $timeStamp, $nonce, $encryptMsg);
		return $errCode == 0 ? $encryptMsg : '';

	}

	/**
	 * 解密数据
	 * @param  string $msg_sign  URL中的签名串
	 * @param  string $timeStamp URL中的时间戳
	 * @param  string $nonce     URL中的随即串
	 * @param  string $from_xml  密文
	 * @param  string $msg       解密后的明文
	 * @return string            返回结果
	 */
	public static function decryptData($msg_sign, $timeStamp, $nonce, $from_xml, $msg ='')
	{
		$xml_tree = new \DOMDocument();
		$xml_tree->loadXML($from_xml);  //导入制定字符串的xml文档
		$array_e = $xml_tree->getElementsByTagName('Encrypt');  //返回指定名字的元素集合 
		$encrypt = $array_e->item(0)->nodeValue;
		$format = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[%s]]></Encrypt></xml>";
		$from_xml = sprintf($format, $encrypt);
		$errCode = self::getInstance()->decryptMsg($msg_sign, $timeStamp, $nonce, $from_xml, $msg);
		return $errCode == 0 ? $msg : '';
	}

}