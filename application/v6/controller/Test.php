<?php

namespace app\v6\controller;

use app\v6\Services\PmsApi;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
dd($id);
    }

    public function publishMq()
    {
        $connection = new AMQPStreamConnection('172.19.0.1', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $str = '我吃有name3!';
        $message = new AMQPMessage($str, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_qos(null, 1, null);
        $channel->basic_publish($message, 'test.direct', 'task_queue');

        echo " [x] Sent $str \n";

        $channel->close();
        $connection->close();
    }

    public function refundPms()
    {
        $data = [
            'channelId' => 1001,
            'pmsId' => 10001,
            'orderCode' => '19041817485929424657',
        ];
        $response = app(PmsApi::class)->cancelOrder($data);
        var_dump($response);
    }

}
