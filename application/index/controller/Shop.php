<?php
namespace app\index\controller;

use app\common\controller\Common;

/**
 * 门店相关操作
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class Shop extends Common
{
    private $handel;

    private $data_types = ['add','edit']; //返回的数据类型

    protected $beforeActionList = [
        'channelHandle' => ['except'=>'access'],
    ];

    // 权限控制
    protected function access()
    {
        return [
            'sub_list'              =>  [ 'type'=>'GET' ,   'lived'=>false ] ,
            'index'                 =>  [ 'type'=>'GET' ,   'lived'=>false ] ,
            'facilities'            =>  [ 'type'=>'GET' ,   'lived'=>false ] ,
        ];
    }

    protected function channelHandle()
    {
        $logic = $this->api_version."logic\ShopLogic";
        $this->handel = new $logic($this->api_version);
    }

    //门店列表
    public function sub_list()
    {
        $this->handel->lists($this->all_param);
    }

    // 风格设置
    public function style()
    {
        $this->handel->style($this->all_param);
    }

    //门店详情
    public function index()
    {
        $this->handel->index($this->all_param);
    }

    //门店介绍
    public function desc()
    {
        $this->handel->desc($this->all_param);
    }


    //设施详情
    public function facilities()
    {
        $this->handel->facilities($this->all_param);
    }

    // 详情页
    public function detail()
    {
        $this->handel->detail($this->all_param);
    }
}
