<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/7/6
 * Time: 10:50
 */

namespace lib;

class Status
{
    const ORDER_UNPAY               = 2; //订单未支付
    const ORDER_PAY                 = 3; //订单支付成功
    const ORDER_BOOKING             = 4; //预约中
    const ORDER_CONFIRM             = 5; //订单确认[接单,已发货,]
    const ORDER_BOOKING_FAIL        = 6; //预约失败
    const ORDER_COMPLETE            = 8; //订单完成
    const ORDER_CLOSE               = 9; //订单关闭

    const PAY_DEFAULT               = 0; //不知道
    const PAY_ALI                   = 1; //支付宝支付
    const PAY_WEIXIN                = 2; //微信支付
    const PAY_UNION                 = 3; //银联支付
    const PAY_OFFLINE               = 4; //线下支付

    const REFUND_DEFAULT            = 0; //默认状态
    const REFUND_APPLY              = 1; //前台申请退款
    const REFUND_REFUSE             = 2; //拒绝退款
    const REFUND_SUCCESS            = 3; //退款成功
    const REFUND_REVIEW             = 4; //审核中

    const WEIXIN_REFUND_OK          = 1; //已经拉取微信退款回调
    const WEIXIN_REFUND_NO          = 2; //未拉取微信退款回调

    const GROUP_PRODUCT             = 0; //分组产品(二维码链接)
    const CALENDAR_PRODUCT          = 1; //日历框
    const TICKET_PRODUCT            = 2; //门票
    const SUIT_PRODUCT              = 3; //套餐
    const MARKET_PRODUCT            = 4; //商超
    const VOUCHER_PRODUCT           = 5; //券类
    const COUPON                    = 100; //优惠券  类型
    const ARTICLE_PRODUCT           = 101;//文章  类型
    const POI                       = 102;//POI  类型
    const DIGITAL                   = 103;//数字专线  类型

    const SMS_PAY_SUCCESS           = 1; //支付成功
    const SMS_APPLY_REFUND          = 2; //用户申请退款
    const SMS_RECEIVE               = 3; //接单
    const SMS_REFUSE                = 4; //拒单
    const SMS_REFUND_SUCCESS        = 5; //退款成功
    const SMS_REFUND_REFUSE         = 6; //拒绝退款

    const PAY_SUCCESS               = 3; //支付成功

    const INFORM_PAY_SUCCESS        = 1; //支付成功
    const INFORM_RECEIVE_SUCCESS    = 2; //接单成功
    const INFORM_RECEIVE_FAILURE    = 3; //接单失败

    const DISABLE                   = 0; //不可用
    const USABLE                    = 1; //可用

    const DAY                       = 60*60*24; //一天

    const EXPERIENCE_QRCODE         = 1; //体验二维码
    const APPLET_QRCODE             = 2; //小程序二维码
    const APPLET_CODE_A             = 3; //小程序码A
    const APPLET_AODE_B             = 4; //小程序码B

    const WECHAT                    = 1; //公众号
    const APPLET                    = 2; //小程序

    const ENCRYPT_PRODUCT           = 1; //产品
    const ENCRYPT_ITEM              = 2; //券
    const ENCRYPT_SHOP              = 3; //店铺
    const ENCRYPT_SUB_SHOP          = 4; //子店铺
    const ENCRYPT_AROUND            = 5; //周边
    const ENCRYPT_ROOM_TYPE         = 6; //房型
    const ENCRYPT_APPLY_REFUND_SHOP = 7; //申请退款店铺
    const ENCRYPT_GROUP             = 8; //产品分组
    const ENCRYPT_COUPON            = 9; //优惠券
    const ENCRYPT_COMBINE_PRODUCT   = 10;//组合产品
    const ENCRYPT_ARTICLE           = 11;//文章
    const ENCRYPT_POI               = 12;//poi
    const ENCRYPT_PROMOTER          = 13;//推广员
    const ENCRYPT_PROMOTER_APPLY    = 14;//审核推广员
    const ENCRYPT_DIGITAL           = 15;//数字专线

    const BOOKING_TYPE_PHONE        = 1; //电话预约
    const BOOKING_TYPE_AFTER_ORDER  = 2; //下单后自主预约
    const BOOKING_TYPE_ORDER        = 3; //下单时选择出行时间

    const PRODUCT_STATUS_WAITING    = 0;  //产品待上线
    const PRODUCT_STATUS_ONLINE     = 1;  //产品上线
    const PRODUCT_STATUS_OFFLINE    = 2;  //产品下线
    const PRODUCT_STATUS_DELETE     = 3;  //产品删除

    const BOOKING_INFO_NAME         = 1;  //预约信息-姓名
    const BOOKING_INFO_NAME_ID      = 2;  //预约信息-姓名+身份证

    const TICKET_DEFAUT             = 0; //券未预约
    const TICKET_BOOKING            = 4; //券预约中
    const TICKET_CONFIRM            = 5; //券已确认 已发货
    const TICKET_BOOKING_FAIL       = 6; //券预约失败
    const TICKET_COMPLETE           = 8; //券已完成

    const PRODUCT_TICKET_ONLINE     = 1; //券上线
    const PRODUCT_TICKET_OFFLINE    = 2; //券下线
    const PRODUCT_TICKET_DELETE     = 3; //券删除

    const ENV_ONLINE                = 1; //线上环境
    const ENV_TEST                  = 2; //测试环境
    const ENV_LOCAL                 = 3; //本地环境

    const COUPON_TYPE_ALL           = -1; //优惠券全部类型
    const COUPON_TYPE_VALUE         = 1; //优惠券面值类型
    const COUPON_TYPE_DISCOUNT      = 2; //优惠券折扣类型

    const COUPON_STATUS_DEFAULT     = 1; //优惠券未开始或正在进行中
    const COUPON_STATUS_OVER        = 0; //优惠券已结束
    const COUPON_VM_ALL             = -1; //优惠券全部
    const COUPON_VM_NOTSTART        = 1; //优惠券未开始
    const COUPON_VM_HAVING          = 2; //优惠券进行中
    const COUPON_VM_OVER            = 3; //优惠券已结束

    const TICKET_BOOKING_INFO_NO_ID = 1; //无需填写身份证(门票下单信息)
    const TICKET_BOOKING_INFO_ONE_ID= 2; //只需填写一人身份证
    const TICKET_BOOKING_INFO_ALL_ID= 3; //需填写所有人身份证

    const TICKET_REFUND_TYPE_YES    = 1; //无条件退票(门票退票规格)
    const TICKET_REFUND_TYPE_NO     = 2; //不得退票
    const TICKET_REFUND_TYPE_REASON = 3; //有理由退票

    const SHOP_STATUS_NORMAL        = 1;//店铺正常状态
    const SHOP_STATUS_DELETE        = 0;//店铺删除

    const SHOP_TYPE_ALL             = 1;//默认所有的店铺功能
    const SHOP_TYPE_DIGITAL         = 2;//数字专线

}
