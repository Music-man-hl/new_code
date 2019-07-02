<?php

/**
 * 日常站点配置
 */

return [
    // 产品类型
    'product_types' => [
        1 => 'hotel',    //酒店
        2 => 'ticket',   //门票
        3 => 'suit',     //套餐
        4 => 'shop',     //商超
        5 => 'voucher'   //券类
    ],
    // 又拍云回调地址
    'upyun_callback_url' => DOMAIN . '/upyun/callback',
    // 调用相关接口key
    'validate_key' => '3f3c6c1cef4525e1c0cc41e28dc3b1b9',

    // pms接口配置
    'pms' => [
        'secret' => env('PMS_SECRET'),
        'url' => env('PMS_URL'),
    ],

    'encrypt_key_list'       => [
        1 => 'Vudeh]bmFM@khc*g', //产品id
        2 => 'AdWt8sJUoNX#1+%8', //券id
        3 => '9+f*tFnGYx]*3hSw', //shopid
        4 => 'dczJlBz~qa[6GafQ', //sub_shopid
        5 => 'bfDn9dnkfb@+*9bf', //周边id
        6 => 'QiAD7q25d$Sjmokj', //房型id
        7 => 'bmFM@FnGYxDn9bka', //申请退款时的shopid
        8 => 'e9/KyfN;jQ7pDF^o', //产品分组id
        9 => '8Wqc&oYP`%bFLur6', //优惠券id
        10=> '~!|S[1F.,%@i_Qd]', //产品类型+产品id组合id
        11=> 'I8D%3BIHhL@rgbEs',//资讯id
        12=> 'khTdczr5ztBe@8JO',//poiid
        13=> '5MXDV3WM2PyYvy5N',//推广员
        14=> 'xpe4lnCXR7zGP0Et',//推广员审核
        15=> 'Vyh5FZ6~zv7nNFPm',//数字专线
    ],

];