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

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 缓存配置为复合类型
    'type'  =>  'complex',

    'default'	=>	[
        'type'	=>	'file',
        // 全局缓存有效期（0为永久有效）
        'expire'=>  86400,
        // 缓存前缀
        'prefix'=>  'think',
        // 缓存目录
        'path'  =>  '../runtime/cache/',
    ],

    'redis' =>	[  //这个cache不要用 自己写的lib\redis
        // 驱动方式
        'type' => 'redis',
        //端口
        'port' => 6379,
        //服务器地址
        'host' => env('REDIS_HOST', '127.0.0.1'),
        // redis 密码
        'password' => env('REDIS_PASSWORD', '123456'),
        //前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存 默认2小时
        'expire' => 7200,
    ],

    // 添加更多的缓存类型设置
];

