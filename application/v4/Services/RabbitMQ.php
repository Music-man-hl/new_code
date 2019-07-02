<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-15
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v4\Services;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ extends BaseService
{
    protected $connection;
    protected $channel;

    public function __construct()
    {
        $host = config('rabbitmq.host');
        $port = config('rabbitmq.port');
        $username = config('rabbitmq.username');
        $password = config('rabbitmq.password');
        $this->connection = $connection = new AMQPStreamConnection($host, $port, $username, $password);
        $this->channel = $connection->channel();
    }

    public function publish($str, $exchange, $routingKey)
    {
        $message = new AMQPMessage($str, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $message->set("application_headers", new AMQPTable(["Content-Type"=>"application/json"]));
        $this->channel->basic_qos(null, 1, null);
        return $this->channel->basic_publish($message, $exchange, $routingKey);
    }

}