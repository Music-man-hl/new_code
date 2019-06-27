<?php
namespace app\index\controller;

use app\common\controller\Common;

/**
 * 我的操作
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class Contact extends Common
{

    // 权限控制
    protected function access()
    {
        return [
            'edit'                  =>  [ 'type'=>'POST' ,   'lived'=>true ] ,
            'del'                   =>  [ 'type'=>'POST' ,   'lived'=>true ] ,
            'list'                  =>  [ 'type'=>'GET' ,   'lived'=>true ] ,
            'lists'                 =>  [ 'type'=>'GET' ,   'lived'=>true ] ,
            'userinfo'              =>  [ 'type'=>'GET' ,   'lived'=>true ] ,
        ];
    }


    //绑定手机号
    public function edit()
    {
        $logic = $this->api_version."logic\ContactLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->edit($this->channels,$this->all_param,$this->users);
    }

    //门店详情
    public function del()
    {
        $logic = $this->api_version."logic\ContactLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->del($this->channels,$this->all_param,$this->users);
    }

    //联系人列表
    public function lists()
    {
        $logic = $this->api_version."logic\ContactLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->lists($this->channels,$this->all_param,$this->users);
    }

    function _empty($name){
        if($name == 'list'){
            $this->lists();
        }else{
            error(40400,$name);
        }

    }

    function userinfo(){
        $logic = $this->api_version."logic\ContactLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->userinfo($this->channels,$this->all_param,$this->users);
    }
}