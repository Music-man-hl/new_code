<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    // 是否严格检查字段是否存在
    'fields_strict'   => true,
    // 数据集返回类型
    'resultset_type'  => 'array',
    // 自动写入时间戳字段
    'auto_timestamp' => false,
    // 是否需要进行SQL性能分析
    'sql_explain' => env('APP_DEBUG'),
    // 开启断线重连
    'break_reconnect' => true,
    //dms_main 数据库
    'dms_main' => [
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => env('DATABASE_HOSTNAME'),
        // 数据库名
        'database' => 'dms_main',
        // 用户名
        'username' => env('DATABASE_USERNAME'),
        // 密码
        'password' => env('DATABASE_PASSWORD'),
        // 端口
        'hostport' => '3306',
    ],

    //dms_product 数据库
    'dms_product' => [
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => env('DATABASE_HOSTNAME'),
        // 数据库名
        'database' => 'dms_product',
        // 用户名
        'username' => env('DATABASE_USERNAME'),
        // 密码
        'password' => env('DATABASE_PASSWORD'),
        // 端口
        'hostport' => '3306',
    ],

    //mp_order数据库
    'mp_order' => [
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => env('DATABASE_HOSTNAME'),
        // 数据库名
        'database' => 'mp_order',
        // 用户名
        'username' => env('DATABASE_USERNAME'),
        // 密码
        'password' => env('DATABASE_PASSWORD'),
        // 端口
        'hostport' => '3306',
    ],
];
