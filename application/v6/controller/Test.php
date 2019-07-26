<?php

namespace app\v6\controller;

use app\v6\handle\hook\OrderInit;
use app\v6\handle\logic\PayLogic;
use app\v6\model\Shop\Order;

class Test extends Base
{

    protected function access()
    {
        return [
            'index' => ['type' => 'GET'],
            'publishMq' => ['type' => 'GET'],
        ];
    }


    public function index()
    {

        $id = encrypt('ZWU', '9', false);
        $re = PayLogic::service()->informSend(OrderInit::class,Order::where('order', 190724165310080015)->find());
        dd($re);
    }


}
