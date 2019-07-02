<?php
/**
 * Created by PhpStorm.
 * User: 83876
 * Date: 2018/5/16
 * Time: 15:23
 */

namespace app\v3\controller;


use app\v3\handle\logic\ProductLogic;

class Product extends Base
{
//  $this->users; $this->channels; $this->permissions; $this->all_param;
    protected function access(){
        return [
            'list'         => [ 'type'=>'GET' , 'lived'=>false ] ,
            'lists'         => [ 'type'=>'GET' , 'lived'=>false ] ,
            'detail'        =>  [ 'type'=>'GET' , 'lived'=>false ] ,

        ];
    }

    function _empty($name){

        if($name == 'list'){
            $this->lists();
        }else{
            error(40000,$name);
        }

    }

    //产品列表
    public function lists(){

        ProductLogic::service()->lists($this->all_param);

    }

    //产品详情
    public function detail(){
        ProductLogic::service()->detail($this->all_param);

    }


    public function booking_calendar()
    {
        ProductLogic::service()->booking_calendar($this->all_param);
    }



}