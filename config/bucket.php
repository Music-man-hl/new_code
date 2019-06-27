<?php
/**
 * 数据库字段对应BUCKET配置
 * 参数说明:
 * 最外层键名（如information_article）对应数据库中表名
 * 第二层键名（如cover）对应表中的字段
 * 第二层键值（如cover的键值room-pic）对应config文件中upyun的第一层键名
 */

return [
    // 图片
    "information_article" => [
        'cover' => 'article-pic',
        'avatar' => 'weixin-pic',
    ],
    "poi_article" => [
        'cover' => 'article-pic',
        'images' => 'article-pic',
    ],
    'digital_line'=>[
        'line_cover'=>'article-pic',
        'cover'=>'article-pic',
    ],
    "product" => [
        'pic' => 'product-pic',
    ],
    "channel" => [
        'user_cover' => 'shop-pic',
    ]
];
