<?php 
namespace app\index\controller;

use app\common\controller\Common;
/**
 * 订单相关操作
 * X-Wolf
 * 2018-6-14
 */
class Order extends Common
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
    	$logic = $this->api_version.'logic\OrderLogic';
    	$this->handel = new $logic($this->api_version);
    }

    // 创建订单
    public function create()
    {
        $logic = $this->api_version."logic\OrderLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->create($this->channels,$this->all_param,$this->users);
    }

    // 订单列表
    public function  lists()
    {
        $logic = $this->api_version."logic\OrderLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->lists($this->channels,$this->all_param,$this->users);
    }

    // 订单详情
    public function detail()
    {
        $logic = $this->api_version."logic\OrderLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->detail($this->channels,$this->all_param,$this->users);
    }

    //预约
    public function booking()
    {
        $logic = $this->api_version."logic\OrderLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->booking($this->channels,$this->all_param,$this->users);
    }

    //申请退款
    public function refund()
    {
        $logic = $this->api_version."logic\OrderLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->refund($this->channels,$this->all_param,$this->users);
    }


    function _empty($name){

        if($name == 'list'){
            $this->lists();
        }else{
            error(40000,$name);
        }

    }
}