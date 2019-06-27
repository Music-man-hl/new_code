<?php
/**
 * Created by PhpStorm.
 * User: haoli
 * Date: 2019/3/26
 * Time: 11:02
 */

namespace app\v4\handle\logic;


use app\v4\handle\query\RoomQuery;
use app\v4\Services\BaseService;

class TicketLogic extends BaseService
{
    private $query;

    function __construct()
    {
        $this->query = new RoomQuery();
    }

    public function lists($allParam)
    {
        $channel = encrypt($allParam['channel'], 3, false);//渠道id
    }

}