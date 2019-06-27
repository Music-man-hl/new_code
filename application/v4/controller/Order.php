<?php 
namespace app\v4\controller;

use app\v4\handle\logic\OrderLogic;

/**
 * 订单相关操作
 * X-Wolf
 * 2018-6-14
 */
class Order extends Base
{
	private $handel;
    protected $beforeActionList = [
    ];

	// 权限限制
	protected function access()
	{
	   return [
	       'create' => ['type'=>'POST' , 'lived' => true ],
	       'detail' => ['type'=>'GET'  , 'lived' => true ],
	       'list'   => ['type'=>'GET'  , 'lived' => true ],
	       'lists'   => ['type'=>'GET'  , 'lived' => true ],
	       'refund'  => ['type'=>'POST' , 'lived' => true ],
	       'booking' => ['type'=>'POST' , 'lived' => true ]
	   ];
	}


	protected function channelHandle()
    {
    	$this->handel = new OrderLogic;
    }

    // 创建订单
    public function create()
    {
        OrderLogic::service()->create($this->channels,$this->all_param,$this->users);
    }

    // 订单列表
    public function  lists()
    {
        OrderLogic::service()->lists($this->channels,$this->all_param,$this->users);
    }

    // 订单详情
    public function detail()
    {
        OrderLogic::service()->detail($this->channels,$this->all_param,$this->users);
    }

    //预约
    public function booking()
    {
        OrderLogic::service()->booking($this->channels,$this->all_param,$this->users);
    }

    //申请退款
    public function refund()
    {
        OrderLogic::service()->refund($this->channels,$this->all_param,$this->users);
    }


    function _empty($name){

        if($name == 'list'){
            $this->lists();
        }else{
            error(40000,$name);
        }

    }
}