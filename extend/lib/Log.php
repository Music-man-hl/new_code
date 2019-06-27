<?php 
namespace lib;

use think\Db;
/**
 * 日志类(产品,店铺,门店,房态,用户)
 * X-Wolf
 * 2018-5-18
 */
class Log
{
	private static $types = [1 => 'ADD',2 => 'UPDATE',3 => 'DELETE']; //操作方式
	//建表规则: 必要字段(channel,uid,type,create,data)
	private static $logConfigs = [
		'product_log'		=>	['channel','uid','type','product_id','product_type'],
		'shop_around_log'	=>	['channel','uid','type','shop_id','around_id'],
		'shop_log'			=>	['channel','uid','type','shop_id'],
		'hotel_booking_log'	=>	['channel','uid','type','room'],
		'channel_log'		=>	['channel','uid','type',],
		'rbac_user_log'		=>	['channel','uid','type'],
	];

	/**
	 * 记录日志 例:Log::rbac_user(2,2,2,['hello'])
	 * @param  String $name 方法名(方法名 + _log = 表名)
	 * @param  Array $args 传递的参数(顺序: 'channel','uid','type' ... 'data')(最后一个参数一定是data)
	 */
	static function __callStatic($name,$args)
	{
		$table = $name.'_log';
		if(!array_key_exists($table, self::$logConfigs)) error(40000,'日志写入方法错误');

		$data = array_pop($args);
		if(count($args) !== count(self::$logConfigs[$table])) error(40000,'日志写入数据错误');

		$res = array_combine(self::$logConfigs[$table], $args);
		if(!array_key_exists($res['type'], self::$types)) error(40000,'日志写入操作方式错误');

		$res['create']  = NOW;
		$res['type']	= self::$types[$res['type']];
		$res['data'] 	= !is_scalar($data) ? json_encode($data,JSON_UNESCAPED_UNICODE) : $data;

		return Db::name($table)->insert($res);
	}
	
}