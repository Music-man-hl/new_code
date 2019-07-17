<?php
/**
 * 数字专线相关
 * User: 总裁
 * Date: 2019/6/17
 * Time: 10:41
 */

namespace app\v6\controller;


use app\v6\handle\logic\DigitalLogic;

class Digital extends Base
{

    // 权限控制
    protected function access()
    {
        return [
            'index' => ['type' => 'GET', 'lived' => false],//数字专线首页
            'consultation' => ['type' => 'GET', 'lived' => false],//独家资讯
            'scenic_type' => ['type' => 'GET', 'lived' => false],//数字专线景点推荐分类
            'scenic_list' => ['type' => 'GET', 'lived' => false],//数字专线景点推荐列表
            'area_list' => ['type' => 'GET', 'lived' => false],//数字专线列表
            'area' => ['type' => 'GET', 'lived' => false],//数字专线地区列表
            'article_detail' => ['type' => 'GET', 'lived' => false],//数字专线文章详情
            'single_index' => ['type' => 'GET', 'lived' => false],//数字单线主页
            'single_list' => ['type' => 'GET', 'lived' => false],//数字单线线路列表
            'number' => ['type' => 'GET', 'lived' => false],//数字专线容量
            'single_detail' => ['type' => 'GET', 'lived' => false],//数字单线详情poi
            'type_list' => ['type' => 'GET', 'lived' => false],//数字单线分类
            'commend_list' => ['type' => 'GET', 'lived' => false],//数字单线首页四美推荐
            'poi_list' => ['type' => 'GET', 'lived' => false],//数字单线四美列表
            'buy_type' => ['type' => 'GET', 'lived' => false],//数字单线购买方式
        ];
    }

    // 数字专线首页
    public function index()
    {
        DigitalLogic::service()->index($this->channels, $this->all_param); //,$this->users
    }

    // 独家资讯
    public function consultation()
    {
        DigitalLogic::service()->consultation($this->channels, $this->all_param); //,$this->users
    }

    // 数字专线景点推荐分类
    public function scenic_type()
    {
        DigitalLogic::service()->scenic_type($this->channels, $this->all_param); //,$this->users
    }

    //数字专线景点推荐列表
    public function scenic_list()
    {
        DigitalLogic::service()->scenic_list($this->channels, $this->all_param); //,$this->users
    }

    //数字专线列表
    public function area_list()
    {
        DigitalLogic::service()->area_list($this->channels, $this->all_param); //,$this->users
    }

    //数字专线地区列表
    public function area()
    {
        DigitalLogic::service()->area($this->channels, $this->all_param); //,$this->users
    }

    //数字专线文章详情
    public function article_detail()
    {
        DigitalLogic::service()->article_detail($this->channels, $this->all_param); //,$this->users
    }

    //数字单线主页
    public function single_index()
    {
        DigitalLogic::service()->single_index($this->channels, $this->all_param); //,$this->users
    }

    //数字单线线路列表
    public function single_list()
    {
        DigitalLogic::service()->single_list($this->channels, $this->all_param); //,$this->users
    }

    //数字专线容量
    public function number()
    {
        DigitalLogic::service()->number($this->channels, $this->all_param); //,$this->users
    }

    //数字单线详情poi
    public function single_detail()
    {
        DigitalLogic::service()->single_detail($this->channels, $this->all_param); //,$this->users
    }

    //数字单线分类
    public function type_list()
    {
        DigitalLogic::service()->type_list($this->channels, $this->all_param); //,$this->users
    }

    //数字单线首页四美推荐
    public function commend_list()
    {
        DigitalLogic::service()->commend_list($this->channels, $this->all_param); //,$this->users
    }

    //数字单线四美列表
    public function poi_list()
    {
        DigitalLogic::service()->poi_list($this->channels, $this->all_param); //,$this->users
    }

    //数字单线购买方式
    public function buy_type()
    {
        DigitalLogic::service()->buy_type($this->channels, $this->all_param); //,$this->users
    }

}