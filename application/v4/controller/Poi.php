<?php
/**
 * poi文章相关
 * User: 总裁
 * Date: 2019/6/17
 * Time: 10:41
 */

namespace app\v4\controller;


use app\v4\handle\logic\PoiLogic;

class Poi extends Base
{
    // 权限控制
    protected function access()
    {
        return [
            'lists' => ['type' => 'GET', 'lived' => false],//poi列表
            'catalog' => ['type' => 'GET', 'lived' => false],//poi目录
            'article_list' => ['type' => 'GET', 'lived' => false],//文章目录
        ];
    }

    // poi列表
    public function lists()
    {
        PoiLogic::service()->lists($this->channels, $this->all_param); //,$this->users
    }

    // poi目录
    public function catalog()
    {
        PoiLogic::service()->catalog($this->channels, $this->all_param); //,$this->users
    }

    // poi目录
    public function article_list()
    {
        PoiLogic::service()->article_list($this->channels, $this->all_param); //,$this->users
    }


}