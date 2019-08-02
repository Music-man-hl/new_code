<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-15
 * Email: <haoliang.yang@gmail.com>
 */

// +----------------------------------------------------------------------
// | rabbitMQ配置
// +----------------------------------------------------------------------
return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => 5672,
    'username' => env('RABBITMQ_USERNAME', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),

    //订单exchange
    'order_exchange' => 'order',
    //订单routing_key
    'order_routing_key' => 'order_key',
    //订单queue
    'order_queue' => 'order_queue',

    //分销exchange
    'extension_exchange' => 'micro',
    //分销routing_key
    'extension_routing_key'=>'distribution.reckon',

    'extension_commission_routing_key'=>'distribution.commission',
];