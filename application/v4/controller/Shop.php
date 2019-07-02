<?php
namespace app\v4\controller;


use app\v4\handle\logic\ShopLogic;

/**
 * 门店相关操作
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class Shop extends Base
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
        $this->handel = new ShopLogic();
    }

    //门店列表
    public function sub_list()
    {
        ShopLogic::service()->lists($this->all_param);
    }

    // 风格设置
    public function style()
    {
        ShopLogic::service()->style($this->all_param);
    }

    //门店详情
    public function index()
    {
        ShopLogic::service()->index($this->all_param);
    }

    //门店介绍
    public function desc()
    {
        ShopLogic::service()->desc($this->all_param);
    }


    //设施详情
    public function facilities()
    {
        ShopLogic::service()->facilities($this->all_param);
    }

    // 详情页
    public function detail()
    {
        ShopLogic::service()->detail($this->all_param);
    }
}
