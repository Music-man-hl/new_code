<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\index\controller;


use app\common\controller\Common;

class Room extends Common
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

        $logic = $this->api_version."logic\RoomLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->lists($this->all_param);

    }

    //房型详情
    public function detail(){

        $logic = $this->api_version."logic\RoomLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->detail($this->all_param);

    }



    //房态列表
    public function calendar(){

        $logic = $this->api_version."logic\RoomLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->calendar($this->all_param);

    }


    public function tags()
    {
        $logic = $this->api_version . "logic\RoomLogic"; //获取调用的空间名
        $handel = new $logic($this->api_version);
        $handel->tags($this->all_param);
    }


}