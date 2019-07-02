<?php
/**
 * Created by PhpStorm.
 * User: haoli
 * Date: 2019/3/26
 * Time: 11:02
 */

namespace app\index\handle\V1_2_1\logic;


class TicketLogic
{
    private $handle;
    private $api_version;//设置版本，随时可以更新


    function __construct( $api_version )
    {
        $this->api_version = $api_version;
        $model_path = $api_version."model\RoomModel"; //用户模型
        $this->handle = new $model_path();
    }

    public function lists($allParam)
    {
        $channel = encrypt($allParam['channel'],3,false);//渠道id
    }

}