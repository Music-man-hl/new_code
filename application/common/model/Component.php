<?php 
namespace app\common\model;

use think\Model;

/**
 * 第三方verify_ticket和access_token操作
 * X-Wolf
 * 2018-5-25
 */
class Component extends Model
{
	protected $table = 'third_component_data';

	//更新ticket
	public function updateTicket($ticket)
	{
		return Component::where('name','component_verify_ticket')->update(['value'=>$ticket]); //没有设置过期时间
	}

	// 获取ticket
	public function getTicket()
	{
		return Component::where('name','component_verify_ticket')->field('value')->find();
	}

	// 获取access_token
	public function getAccessToken()
	{
		return Component::where('name','component_access_token')->field('value,expire_time')->find();
	}

	// 设置access_token
	public function setAccessToken($componentAccessToken)
	{
		return Component::where('name','component_access_token')->update(['value'=>$componentAccessToken,'expire_time'=>time()]);
	}
}
