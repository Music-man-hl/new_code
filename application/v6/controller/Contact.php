<?php
namespace app\v6\controller;

use app\v6\handle\logic\ContactLogic;

/**
 * 我的操作
 * User: 83876
 * Date: 2018/4/25
 * Time: 19:23
 */
class Contact extends Base
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
        ContactLogic::service()->edit($this->channels,$this->all_param,$this->users);
    }

    //门店详情
    public function del()
    {
        ContactLogic::service()->del($this->channels,$this->all_param,$this->users);
    }

    //联系人列表
    public function lists()
    {
        ContactLogic::service()->lists($this->channels,$this->all_param,$this->users);
    }

    function _empty($name){
        if($name == 'list'){
            $this->lists();
        }else{
            error(40400,$name);
        }

    }

    function userinfo(){
        ContactLogic::service()->userinfo($this->channels,$this->all_param,$this->users);
    }
}