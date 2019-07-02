<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\v3\controller;


use app\v3\handle\logic\RoomLogic;

class Room extends Base
{
//  $this->users; $this->channels; $this->permissions; $this->all_param;
    protected function access(){
        return [
            'list'         => [ 'type'=>'GET' , 'lived'=>false ] ,
            'lists'         => [ 'type'=>'GET' , 'lived'=>false ] ,
            'detail'        =>  [ 'type'=>'GET' , 'lived'=>false ] ,
            'tags' => ['type' => 'GET', 'lived' => false],
        ];
    }

    function _empty($name){

        if($name == 'list'){
            $this->lists();
        }else{
            error(40000,$name);
        }

    }

    //房型列表
    public function lists(){
        RoomLogic::service()->lists($this->all_param);
    }

    //房型详情
    public function detail(){
        RoomLogic::service()->detail($this->all_param);
    }



    //房态列表
    public function calendar(){

        RoomLogic::service()->calendar($this->all_param);

    }


    public function tags()
    {
        RoomLogic::service()->tags($this->all_param);
    }


}