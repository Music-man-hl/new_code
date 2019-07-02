<?php 

/**
 * 短信配置
 * 一维数组key	:	产品类型 1-酒店日历框 2-门票 3-套餐 4-商超
 * 二维数组key	:	模板类型 1-支付成功 2-用户申请退款 3-接单 4-拒单 5-退款成功 6-拒绝退款
 * 二维数组value	: 	需要替换的变量集合(示例如下) 	
 * 					模板内容：订单编号：#order#，订单类型：#order_type#，产品类型：#product_type#。  ==> ['order','order_type','product_type']
 */

return [
	
	1	=>	[	
			1	=>	[
						'name',
						'sub_shop_name',
						'room_type_name',
						'mobile'
					],
			2	=>	[
						'sub_shop_name',
						'room_type_name',
						'order',
						'mobile'
					],
			3	=>	[
						'name',
						'sub_shop_name',
						'room_type_name',
						'checkin_date',
						'checkout_date',
						'hotel_address',
						'confirm_code',
						'mobile'
					],
			4	=>	[
						'sub_shop_name',
						'room_type_name',
						'order',
						'total',
						'mobile'
					],
			5	=>	[
						'sub_shop_name',
						'room_type_name',
						'order',
						'total',
						'mobile'
					],
			6	=>	[
						'sub_shop_name',
						'room_type_name',
						'order',
						'mobile'
					],
		],
	2	=>	[
            2	=>	[
                'product_name',
                'order',
                'mobile'
            ],
            3	=>	[
                'name',
                'product_info',//{产品名称}{套餐名称}*{门票数量}  这个是包含3个变量和一个*
                'date',
                'code',
                'mobile'
            ],
            4	=>	[
                'product_name',
                'date',
                'mobile'
            ],
            5	=>	[
                'product_name',
                'order',
                'total',
                'mobile'
            ],
            6	=>	[
                'product_name',
                'order',
                'mobile'
            ],
		],
	3	=>	[
			1	=>	[],
			2	=>	[],
			3	=>	[],
			4	=>	[],
			5	=>	[],
			6	=>	[],
		],
	4	=>	[
			1	=>	[],
			2	=>	[],
			3	=>	[],
			4	=>	[],
			5	=>	[],
			6	=>	[],
		],
	5	=>	[
            1	=>	[
                'name',
                'product_name',
                'start',
                'end',
                'mobile'
            ],
            2	=>	[
                'product_name',
                'order',
                'mobile'
            ],
            3	=>	[
                'name',
                'product_name',
                'checkin_date',
                'confirm_code',
                'mobile'
            ],
            4	=>	[
                'product_name',
                'checkin_date',
                'mobile'
            ],
            5	=>	[
                'product_name',
                'order',
                'total',
                'mobile'
            ],
            6	=>	[
                'product_name',
                'order',
                'mobile'
            ],
		],
];