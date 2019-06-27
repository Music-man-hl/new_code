<?php

/**
 * 又拍云相关配置
 */

return [

    'room-pic'		=>	[
        'bucket'				=>	'room-p',
        'form_api_key'			=>	'M3UnoaF1A4l3gDrNGi34IjG24PI=',
        'domain'				=>	'https://room-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'sub-shop-pic'	=>	[
        'bucket'				=>	'sub-shop-p',
        'form_api_key'			=>	'NE1t9XS50Q/YnnAfFWfvOaeYN1Q=',
        'domain'				=>	'https://sub-shop-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'shop-pic'		=>	[
        'bucket'				=>	'shop-p',
        'form_api_key'			=>	'SLgwZ1DbDevS1Ou6PgGiVGWfHHI=',
        'domain'				=>	'https://shop-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'third-pic'		=>	[
        'bucket'				=>	'third-p',
        'form_api_key'			=>	'eTJRZ9AMqbuTN7QuX3Pe8r1dyjs=',
        'domain'				=>	'https://third-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'around-pic'	=>	[
        'bucket'				=>	'around-p',
        'form_api_key'			=>	'J9dNnA+66z16ZCP3020VcVWZVFo=',
        'domain'				=>	'https://around-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'article-pic'	=>	[
        'bucket'				=>	'article-p',
        'form_api_key'			=>	'6b94UNUb1FV6F8JyMllTozk2jKk=',
        'domain'				=>	'https://article-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'weixin-pic'	=>	[
        'bucket'				=>	'weixin-p',
        'form_api_key'			=>	'7PEDwNlwqenK5ohEynzgG+cmQFA=',
        'domain'				=>	'https://weixin-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'wxphoto-pic'	=>	[
        'bucket'				=>	'wxphoto-p',
        'form_api_key'			=>	'dvAZpJjEj9HOqY1xOVtUx6Bqtmk=',
        'domain'				=>	'https://wxphoto-pic.feekr.com',
        'image-width-range'     =>  '100,10000',
        'image-height-range'    =>  '100,10000',
    ],
    'license-pic'   =>  [
        'bucket'                =>  'license-p',
        'form_api_key'          =>  'PfJ1viWgkH5rV06PXlM1QFgcgzM=',
        'domain'                =>  'https://license-pic.feekr.com',
        'params'                =>   [
            'allow-file-type'       =>  'jpg,jpeg,png', 
            'content-length-range'  =>  '0,1048576',    
            'image-width-range'     =>  '300,2000',
            'image-height-range'    =>  '300,2000',
        ],
    ],
    'product-pic'   =>  [
        'bucket'                =>  'product-p',
        'form_api_key'          =>  '29njJSf0p5SKFUHF50Un1t8seFQ=',
        'domain'                =>  'https://product-pic.feekr.com',
        'params'                =>   [
            'allow-file-type'       =>  'jpg,jpeg,png', 
            'content-length-range'  =>  '0,1048576',    
            'image-width-range'     =>  '300,2000',
            'image-height-range'    =>  '300,2000',
        ],
    ],
    'product-video1'    =>  [
        'bucket'                =>  'product-v',
        'form_api_key'          =>  '1H4C6QZMF3F6cOlNqh1574c3lLA=',
        'domain'                =>  'https://product-video.feekr.com',
        'params'                =>   [
            'allow-file-type'       =>  'jpg,jpeg,png', 
            'content-length-range'  =>  '0,1048576',    
            'image-width-range'     =>  '300,2000',
            'image-height-range'    =>  '300,2000',
        ],
    ],

    // 视频
    'product-video2'    =>  [
        'bucket'                =>  'product-v',
        'form_api_key'          =>  '1H4C6QZMF3F6cOlNqh1574c3lLA=',
        'domain'                =>  'https://product-video.feekr.com',
        'params'                =>  [
            'allow-file-type'       =>  'mp4,mov,m4v,flv,x-flv,mkv,wmv,avi,rmvb,3gp', 
            'content-length-range'  =>  '0,20971520',   
            'apps'                  =>  [
                [
                    'name'          =>  'naga',     //视频异步处理
                    'type'          =>  'video',    //操作类型 video-标准转码 thumbnail-
                    'avopts'        =>  '/s/720p(16:9)/sp/auto/sm/false/f/mp4', //视频转码参数
                    'return_info'   =>  false,       //回调信息是否包含输出文件的元数据，元数据格式为 JSON，默认 false/true(多回调一次)
                    'save_as'       =>  '',         //结果图片保存路径
                ],
                [
                    'name'          =>  'naga',   
                    'type'          =>  'thumbnail',
                    'avopts'        =>  '/o/true',
                    'return_info'   =>  false,
                    'save_as'       =>  '',
                ]
            ],
        ],
    ],
];