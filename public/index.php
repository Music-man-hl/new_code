<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;

//环境参数 1线上2测试3本地
define('APP_EVN', $_SERVER['HTTP_HOST'] == 'api-shop.feekr.com' ? 1 : ($_SERVER['HTTP_HOST'] == 'api-shop.f.com' ? 3 : 2));
define('DOMAIN', APP_EVN == 1 ? 'https://api-shop.feekr.com' : (APP_EVN == 2 ? 'https://tst-api-shop.feekr.com' : 'http://api-shop.f.com'));//本站url
define('DOMAIN_MP', APP_EVN == 1 ? 'https://api-mp.feekr.com' : (APP_EVN == 2 ? 'https://tst-api-mp.feekr.com' : 'http://api-mp.f.com'));//后台接口
define('PROGARM_ROOT', dirname(dirname(__FILE__))); // program root
define('REDIS_SYS', APP_EVN == 1 ? 'o' : (APP_EVN == 2 ? 't' : 'l')); // redis线上/测试/本地前缀标识
define('REDIS_FB', 'f'); // redis前台/后台前缀标识
define('NOW', time()); // define a global timestamp
define('TODAY', strtotime('today'));

// 加载基础文件
require __DIR__ . '/../thinkphp/base.php';

// 支持事先使用静态方法设置Request对象和Config对象
// 执行应用并响应
if (\Request::param('api_version')){ //兼容1.2.1 后期去掉
    Container::get('app')->bind('v3')->run()->send();
}else{
    Container::get('app')->run()->send();
}