<?php

namespace app\v4\handle\validate\auth;

use think\Validate;

/**
 * 小程序登录验证
 * X-Wolf
 * 2018-6-25
 */
class WxLogin extends Validate
{
    protected $rule = [
        'shop_id' => 'require',
        'code' => 'require',
        'rawData' => 'require',
        'signature' => 'require',
        'encryptedData' => 'require',
        'iv' => 'require',
        'device' => 'require',
    ];

    protected $message = [
        'shop_id.require' => '40000_店铺id必传',
        'code.require' => '40000_登录code必传',
        'rawData.require' => '40000_原始数据必传',
        'signature.require' => '40000_签名必传',
        'encryptedData.require' => '40000_加密数据必传',
        'iv.require' => '40000_初始向量必传',
        'device.require' => '40000_设备必传',
    ];

}