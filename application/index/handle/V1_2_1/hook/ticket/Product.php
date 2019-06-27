<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:06
 */

namespace app\index\handle\V1_2_1\hook\ticket;


class Product
{
    //支付
    public function pay($getOrder,$param)
    {
        return OrderLogic::pay($getOrder,$param);
    }

   public function lists($data)
   {
       return ProductLogic::lists($data);
   }

   public function detail($data)
   {
       return ProductLogic::detail($data);
   }

   public function booking_calendar($data)
   {
       return ProductLogic::booking_calendar($data);
   }




}