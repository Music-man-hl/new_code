<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v3\handle\hook\voucher;

use app\v3\model\Main\Shop;
use app\v3\model\Shop\Coupon;
use app\v3\model\Shop\CouponCode;
use app\v3\model\Shop\HotelBooking;
use app\v3\model\Shop\InformMsg;
use app\v3\model\Shop\InformSend;
use app\v3\model\Main\InformTpl;
use app\v3\model\Shop\MessageSend;
use app\v3\model\Shop\Order;
use app\v3\model\Shop\OrderBookingUserinfo;
use app\v3\model\Shop\OrderContact;
use app\v3\model\Shop\OrderExt;
use app\v3\model\Shop\OrderHotelCalendar;
use app\v3\model\Shop\OrderInfo;
use app\v3\model\Shop\OrderPayLog;
use app\v3\model\Shop\OrderVoucher;
use app\v3\model\Shop\Product;
use app\v3\model\Shop\ProductVoucherBooking;
use app\v3\model\Shop\ProductVoucherItem;
use app\v3\model\Shop\ProductVoucherStandard;
use app\v3\model\Shop\ShopPicture;
use app\v3\model\Shop\User;
use app\v3\model\Shop\WeixinUser;
use lib\MyLog;
use lib\Redis;
use lib\Status;
use think\Db;
use third\S;

class OrderQuery
{
    const STAT_VALID = 1; //有效

    //插入支付日志

    public static function pay($getOrder, $param)
    {
        $order_status = Status::ORDER_PAY; //支付成功
        $pay_type = Status::PAY_WEIXIN; //微信支付

        $channel = $getOrder['channel']; //渠道
        $uid = $getOrder['uid']; //用户id

        $order_id = $getOrder['id']; //订单id
        $order = $getOrder['order'];

        $total = add($getOrder['total'], -$getOrder['rebate'], -$getOrder['sales_rebate']); //总价格
        $total_fee = bcdiv($param['total_fee'], 100, 2);

        if (bccomp($total, $total_fee, 2)) { //金额不相等
            self::orderPayLog($channel, $order, '支付的金额不正确');
            return false;
        }

        //查询订单扩展信息 不知道后面是否需要
        $order_ext_data = OrderExt::where('order_id', $order_id)->find();
        if (empty($order_ext_data)) {
            self::orderPayLog($channel, $order, 'order_ext 没有找到');
            return false;
        }

        //查询订单中的券和产品
        $order_voucher_data = OrderVoucher::field('item_id')->where('order_id', $order_id)->select();
        if (empty($order_voucher_data)) {
            self::orderPayLog($channel, $order, 'order_voucher 没有找到');
            return false;
        }

        $item_id = $order_voucher_data[0]['item_id']; //券id
        $product_id = $getOrder['product'];

        $count = count($order_voucher_data); //更新used

        //查询优惠券
        $coupon = '';
        if (!empty($getOrder['coupon_id'])) {
            $coupon = CouponCode::field('id,coupon_id')->where('id', $getOrder['coupon_id'])->where('status', 0)->find();
        }

        //Db::startTrans();
        try {
            $res = Order::where('id', $order_id)->update([
                'status' => $order_status,
                'pay_type' => $pay_type,
                'pay_time' => NOW,
                'update' => NOW,
            ]);

            if (!$res) {
                throw new \Exception('order 更新失败');
            }

            $res = OrderExt::where('order_id', $order_id)->update([
                'pay_account' => '微信',
                'pay_trade' => $param['transaction_id'],
                'out_trade_no' => $param['out_trade_no'],
                'total_fee' => $total_fee
            ]);

            if (!$res) {
                throw new \Exception('order_ext 更新失败');
            }

            //接下来更新产品和券
            $res = ProductVoucherItem::where('id', $item_id)->inc('sales', $count)->update();

            if (!$res) {
                throw new \Exception('product_voucher_item 更新失败');
            }

            //查询现有库存
            $allot = ProductVoucherItem::query("SELECT SUM(`allot` - `sales`) as `num` FROM `product_voucher_item` WHERE `pid` = :pid", ['pid' => $product_id]);

            $res = Product::where('id', $product_id)->inc('sales')->update(['allot' => (int)$allot[0]['num']]);

            if (!$res) {
                throw new \Exception('product 更新失败');
            }

            $res = User::where('id', $uid)->inc('buy')->update();

            if (!$res) {
                throw new \Exception('user 更新失败');
            }

            //有优惠券的情况
            if (!empty($coupon)) {
                $res = CouponCode::where('id', $coupon['id'])->update(['order' => $getOrder['order'], 'status' => 1]);

                if ($res === false) {
                    throw new \Exception('coupon_code 更新失败');
                }

                $res = Coupon::where('id', $coupon['coupon_id'])->inc('used')->update();

                if (!$res) {
                    throw new \Exception('coupon 更新失败');
                }
            }

            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();

            self::orderPayLog($channel, $order, $e->getMessage()); //记录错误信息

            S::log(exceptionMessage($e), 'error'); // 上线取消

            return false;
        }

        return true;
    }

    //支付

    public static function orderPayLog($channel, $order, $data)
    {
        return OrderPayLog::insertGetId(['channel' => $channel, 'order' => $order, 'data' => $data, 'create' => NOW]);
    }

    // 短信 -支付成功

    public static function smsPaySuccess($order)
    {
        $user = User::field('nickname')->where('id', $order['uid'])->find();
        if (empty($user)) {
            S::log('发送支付成功短信 - 获取用户名称失败 订单:' . $order['order']);
            return false;
        }

        $shop = Shop::field('`id`,`name`')
            ->where('id', $order['shop_id'])
            ->with(['tels' => function ($query) {
                $query->field("citycode,tel,objid")
                    ->where("type", 1);
            }])
            ->find();

        if (empty($shop)) {
            S::log('发送支付成功短信 - 获取门店名称失败 订单:' . $order['order']);
            return false;
        }

        $order_info = OrderInfo::field('data')->where('order_id', $order['id'])->find();
        if (empty($order_info)) {
            S::log('发送支付成功短信 - 获取order_info失败 订单:' . $order['order']);
            return false;
        }

        $order_info_data = json_decode($order_info['data'], true);

        $params = [
            'name' => $user['nickname'],
            'product_name' => $order['product_name'],
            'mobile' => ($shop['tels']['citycode'] ? $shop['tels']['citycode'] . '-' : $shop['tels']['citycode']) . $shop['tels']['tel'],
            'start' => date('Y/m/d', $order_info_data['booking_start']),
            'end' => date('Y/m/d', $order_info_data['booking_end']),
        ];

        $msg = [
            'channel' => $order['channel'],
            'product_type' => $order['type'],
            'msg_type' => Status::SMS_PAY_SUCCESS,
            'mobile' => $order['mobile'],
            'order' => $order['order'],
            'data' => json_encode($params),
            'create' => NOW
        ];

        S::log('发送支付成功短信 - 发送短信数据:' . json_encode($msg, JSON_UNESCAPED_UNICODE));
        return MessageSend::insert($msg);
    }


    public static function sendWxTmp($order)
    {
        $user = WeixinUser::alias('u')->field('u.`openid`,p.`model`,p.`keywordnum`,p.`appid`,p.`secret`,p.`model`')->leftjoin('weixin_param p', 'u.appid = p.appid')->where(['u.shopid' => $order['shop_id'], 'p.channel' => $order['channel'], 'u.stat' => '1'])->select();
        if (isset($user[0])) {
            $key = self::getSendTmpKey($user[0]['appid']);
            //下面这部分是为了拿token
            $token = Redis::get($key);
            if (empty($token)) {
                $url = sprintf("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s", $user[0]['appid'], $user[0]['secret']);
                $res = json_decode(curl_file_get_contents($url, '', array(), 2), true);
                S::log('微信模板推送:' . json_encode($res, JSON_UNESCAPED_UNICODE));
                if (isset($res['access_token'])) {
                    Redis::set($key, $res['access_token'], 7000);
                    $token = $res['access_token'];
                }
            }
            if (!empty($token)) {
                //模板推送
                foreach ($user as $v) {
                    $send = '{
               "touser":"' . $v['openid'] . '",
           "template_id":"' . $v['model'] . '",
           "url":"https://mp.feekr.com/order/detail?id=' . $order['order'] . '", 
           "data":{
               "first": {
                   "value":"' . '您收到了一个新的订单，请尽快接单处理' . '",
                       "color":"#173177"
                   },
                   "keyword1":{
                   "value":"' . $order['order'] . '",
                       "color":"#173177"
                   },
                   "keyword2": {
                   "value":"' . $order['contact'] . $order['mobile'] . '",
                       "color":"#173177"
                   },
                   "keyword3": {
                   "value":"' . $order['total'] . '",
                       "color":"#173177"
                   },
                   "keyword4": {
                   "value":"' . $order['product_name'] . '",
                       "color":"#173177"
                   },
                   "keyword5": {
                   "value":"' . '当天确认' . '",
                       "color":"#173177"
                   },
                   "remark":{
                   "value":"",
                       "color":"#173177"
                   }
           }
       }';


                    $msg = curl_file_get_contents('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token, $send, array(), 3);
                    S::log('微信模板推送:' . json_encode($msg, JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }

    private static function getSendTmpKey($appid)
    {
        return redis_prefix() . '_sendTmp_' . md5($appid);
    }

    // 短信 - 申请退款
    public static function smsApplyRefund($order)
    {
        // $shop = Shop::alias('s')->field('s.`name`,t.`citycode`,t.`tel`')->leftjoin('tels t', 's.id = t.objid')->where(['s.id' => $order['shop_id'], 'type' => 1])->find();
        $shop = Shop::field('`id`,`name`')
            ->where('id', $order['shop_id'])
            ->with(['tels' => function ($query) {
                $query->field("citycode,tel,objid")
                    ->where("type", 1);
            }])
            ->find();
        
        if (empty($shop)) {
            S::log('发送申请退款短信 - 获取门店名称失败 订单:' . $order['order']);
            return false;
        }

        $params = [
            'product_name' => $order['product_name'],
            'order' => $order['order'],
            'mobile' => ($shop['tels']['citycode'] ? $shop['tels']['citycode'] . '-' : $shop['tels']['citycode']) . $shop['tels']['tel']
        ];

        $msg = [
            'channel' => $order['channel'],
            'product_type' => $order['type'],
            'msg_type' => Status::SMS_APPLY_REFUND,
            'mobile' => $order['mobile'],
            'order' => $order['order'],
            'data' => json_encode($params),
            'create' => NOW
        ];
        S::log('发送申请退款短信 - 发送短信数据:' . json_encode($msg, JSON_UNESCAPED_UNICODE));
        return MessageSend::insert($msg);
    }

    // 模板消息 - 支付数据
    public static function informPayInfo($order)
    {
        MyLog::debug("进入infrom开始获取数据");
        $informMsg = InformMsg::field('prepay_id,appid,openid')->where('order', $order['order'])->find();
        if (empty($informMsg)) {
            S::log('模板消息 - 获取支付数据 获取inform_msg数据失败 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("msgok".json_encode($informMsg));
        // 获取模板消息
        $where = ['appid' => $informMsg['appid'], 'product_type' => Status::VOUCHER_PRODUCT, 'type' => Status::INFORM_PAY_SUCCESS];
        $informTpl = InformTpl::field('tpl_id,status')->where($where)->find();
        if (empty($informTpl)) {
            S::log('模板消息 - 获取支付数据 获取inform_tpl数据失败 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("tplok".json_encode($informTpl));
        if ($informTpl['status'] == Status::DISABLE) {
            S::log('模板消息 - 获取支付数据 inform_tpl模板禁用 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("statusok".json_encode($informTpl));
        $shop = Shop::field('name')->where('id', $order['shop_id'])->find();
        if (empty($shop)) {
            S::log('模板消息 - 获取支付数据 - 获取门店名称失败 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("shopok".json_encode($shop));
        $orderVoucher = OrderVoucher::field('item_id')->where('order', $order['order'])->find();
        if (empty($orderVoucher)) {
            S::log('模板消息 - 获取支付数据 - 获取产类产品订单失败 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("orderVoucherok".json_encode($orderVoucher));
        $item = ProductVoucherItem::where('id', $orderVoucher['item_id'])->find();

        if (empty($item)) {
            S::log('模板消息 - 获取支付数据 - 获取券类产品失败 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("itemok".json_encode($item));

        $orderExt = OrderExt::field('total_fee')->where('order', $order['order'])->find();
        if (empty($orderExt)) {
            S::log('模板消息 - 获取支付数据 - 获取真实支付的金额失败 订单号:' . $order['order']);
            return false;
        }
        MyLog::debug("orderok".json_encode($orderExt));
        $itemName = self::formatItemName($item);
        $keywords = [
            'keyword1' => ['value' => $order['product_name']], //商品名称
            'keyword2' => ['value' => $itemName], //订单内容
            'keyword3' => ['value' => $orderExt['total_fee']], //金额
            'keyword4' => ['value' => $order['order']], //订单号
            'keyword5' => ['value' => '为确保理想出行，建议确定出行日期后立即预约']
        ];

        $data = [
            'touser' => $informMsg['openid'],
            'template_id' => $informTpl['tpl_id'],
            'form_id' => $informMsg['prepay_id'],
            'data' => $keywords,
            'appid' => $informMsg['appid'],
            'page' => '/pages/order/detail?order_id=' . $order['order'] . '&sub_status=' . $order['sub_status'],
        ];

        S::log('模板消息 - 发送的数据:' . json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }

    // 模板消息 - 支付

    public static function formatItemName($item)
    {
        $names = [];
        for ($i = 1; $i <= 3; $i++) {
            if (isset($item['level' . $i]) && $item['level' . $i]) {
                $names[] = ProductQuery::getItemNameById($item['level' . $i]);
            }
        }
        return implode('-', $names);
    }

    //获取房型

    public static function informPay($order, $tpl, $errcode, $errmsg)
    {
        $inform = [
            'channel' => $order['channel'],
            'order' => $order['order'],
            'product_type' => Status::VOUCHER_PRODUCT,
            'type' => Status::INFORM_PAY_SUCCESS,
            'prepay_id' => $tpl['form_id'],
            'appid' => $order['appid'],
            'openid' => $order['openid'],
            'template' => $tpl['template_id'],
            'data' => json_encode($tpl['data'], JSON_UNESCAPED_UNICODE),
            'status' => $errcode,
            'errmsg' => $errmsg,
            'create' => NOW
        ];
        S::log('模板消息 - 发送支付完成 记录inform_send 数据:' . json_encode($inform));
        return InformSend::insert($inform);
    }

    public static function getProduct($channel, $shop_id, $voucher_id)
    {
        return ProductVoucherItem::query('SELECT i.`sale_price`,i.`level1`,i.`level2`,i.`allot`,i.`sale_price`,i.`sales`,i.`intro` as voucher_intro,p.`name`,p.`title`,p.`market_price`,p.`bucket`,p.`pic`,p.`allot` as p_allot,p.`start`,p.`end`,p.`min`,p.`max`,p.`status`,p.`is_refund`,p.`is_card`,p.`is_invoice`,p.`is_coupons`,p.`booking_type`,p.`booking_start`,p.`booking_end`,p.`booking_info`,p.`id`,f.`intro`,f.`rule`,f.`refund` from product_voucher_item i
   LEFT JOIN product p on i.pid=p.id
   LEFT JOIN product_info f on f.id=i.pid
     WHERE i.id=:id AND p.status=:status AND i.channel=:channel AND p.shop_id=:shop_id', ['id' => $voucher_id, 'channel' => $channel, 'shop_id' => $shop_id, 'status' => self::STAT_VALID]);
    }

    public static function getOrderByVoucher($voucherId)
    {
        return OrderVoucher::query('select v.id from order_voucher v 
        LEFT JOIN  `order` o on o.`order`= v.`order`
        where v.item_id=:item_id AND o.expire>:expire AND o.status=2', ['item_id' => $voucherId, 'expire' => NOW]);
    }

    public static function getShop($id)
    {
        return Shop::query('SELECT s.`name` as sub_shop_name,c.`name` as shop_name,c.`group`,s.channel from shop s
                                    JOIN channel c on c.id=s.channel 
                                    WHERE s.id=:id AND s.status=:status', ['id' => $id, 'status' => self::STAT_VALID]);
    }

    public static function getBooking($where)
    {
        return HotelBooking::field('room,date,sale_price AS price,allot,used')->where($where)->order('date')->select();
    }


    //获取层级

    public static function getOrderCount($channel, $room, $date, $status, $time)
    {
        return OrderHotelCalendar::query('select sum(`room_num`) as cou from order_hotel_calendar where `channel`=:channel AND `room_id`=:room AND `order_status`=:status AND `checkin`<=:date AND `checkout`>:datee AND `create`>:time', ['channel' => $channel, 'room' => $room, 'date' => $date, 'datee' => $date, 'status' => $status, 'time' => $time]);
    }

    //获取联系人

    public static function getStandard($level1, $level2)
    {
        return ProductVoucherStandard::query('SELECT * FROM product_voucher_standard WHERE id=:id1 OR id=:id2', ['id1' => $level1, 'id2' => $level2]);
    }

    public static function getContact($id)
    {
        return OrderContact::where(['id' => $id])->find();
    }

    //订单创建

    public static function getPic($shop_id, $type = 1)
    {
        return ShopPicture::where(['shop' => $shop_id, 'type' => $type])->field('cover,bucket')->select();
    }

    public static function create($data, $snap)
    {
        //Db::startTrans();
        try {
            $order_data = [
                'order' => $data['order'],
                'total' => $data['total'],
                'channel' => $data['channel'],
                'shop_id' => $data['shop_id'],
                'product' => $data['product'],
                'coupon_id' => $data['coupon_id'],
                'rebate' => $data['rebate'],
                'product_name' => $data['product_name'],
                'type' => $data['type'],
                'contact' => $data['contact'],
                'mobile' => $data['mobile'],
                'uid' => $data['uid'],
                'update' => NOW,
                'create' => NOW,
                'date' => strtotime(date('Y-m-d')),
                'status' => $data['status'],
                'ip' => $data['ip'],
                'expire' => NOW + 1800,
                'pv_from' => $data['pv_from'],
                'terminal' => 1,
                'count' => $data['count'],
            ];
            $order_id = Order::insertGetId($order_data);
            if (empty($order_id)) {
                //Db::rollback();
                error(50000, 'order_id 创建失败');
            }

            $order_ext_data = [
                'order_id' => $order_id,
                'channel' => $data['channel'],
                'order' => $data['order'],
            ];

            $res = OrderExt::insert($order_ext_data);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'order_ext 创建失败');
            }

            $order_info_data = [
                'order_id' => $order_id,
                'channel' => $data['channel'],
                'order' => $data['order'],
                'data' => json_encode($snap, JSON_UNESCAPED_UNICODE),
            ];
            $res = OrderInfo::insert($order_info_data);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'order_info 创建失败');
            }
            for ($i = $data['count']; $i > 0; $i--) {
                $order_voucher_data = [
                    'channel' => $data['channel'],
                    'order_id' => $order_id,
                    'order' => $data['order'],
                    'status' => 0,
                    'item_id' => $snap['voucher_id'],
                    'update' => NOW,
                    'terminal' => 1,
                    'create' => NOW,
                ];
                $voucher_id = OrderVoucher::insertGetId($order_voucher_data);
                if (empty($voucher_id)) {
                    //Db::rollback();
                    error(50000, 'voucher_id 创建失败');
                }
            }

            if ($data['coupon_id'] != '') {
                $res = CouponCode::where('id', $data['coupon_id'])->update([
                    'lock_order' => $data['order'],
                    'lock_time' => NOW + 1800,
                ]);
                if ($res === false) {
                    //Db::rollback();
                    error(50000, '优惠券更新失败');
                }
            }


            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
        return $order_id;
    }

    public static function getOrderVoucherByOrder($order_id)
    {
        return OrderVoucher::where(['order' => $order_id])->field('id,remark,people,status,item_id,checkin,checkout,terminal')->select();
    }

    public static function getVoucherById($item_id)
    {
        return ProductVoucherItem::where(['id' => $item_id])->field('id,intro,level1,level2')->find();
    }

    //查询产品的券是否存在

    public static function getStandardById($level1, $level2)
    {
        return ProductVoucherStandard::query('select `value`,`level` from product_voucher_standard WHERE id in(:id1,:id2)', ['id1' => $level1, 'id2' => $level2]);
    }

    public static function productVoucherExist($order_id)
    {
        return OrderVoucher::alias('o')->join('product_voucher_item i', 'o.item_id = i.id')
            ->where('o.order_id', $order_id)->field('i.id,i.allot,i.sales')->find();
    }

    public static function getVoucherByOrderId($order_id, $voucher_id, $user)
    {
        $sql = 'SELECT o.id,o.status,v.status as voucher_status,o.product,v.id as voucher_id,v.item_id,i.data,o.`count` FROM `order` o 
            LEFT JOIN order_voucher v on o.id=v.order_id 
            LEFT JOIN order_info i on o.id=i.order_id 
            WHERE o.order = :order  AND o.uid=:uid AND v.status <> 4';
        return Order::query($sql, ['order' => $order_id, 'uid' => $user]);
    }

    public static function getProductById($id)
    {
        return Product::where(['id' => $id])->field('booking_start,booking_end,is_coupons')->find();
    }

    public static function getProductByIdForOrder($id)
    {
        return Product::where(['id' => $id])->field('booking_info')->find();
    }

    public static function getVoucherDate($itemId, $date)
    {
        return ProductVoucherBooking::where(['item_id' => $itemId, 'date' => $date, 'status' => 1])->field('allot,used')->find();
    }

    // 格式化套餐名称

    public static function setVoucherBooking($data)
    {
        //Db::startTrans();
        try {
            OrderBookingUserinfo::where(array('uid' => $data['user'], 'type' => 5))->delete();
            OrderBookingUserinfo::insertAll($data['user_info']);
            $res = OrderVoucher::where('id', $data['voucher_id'])->update([
                'status' => Status::TICKET_BOOKING,
                'adult' => $data['adultCount'],
                'child' => $data['childCount'],
                'people' => json_encode($data['people']),
                'checkin' => $data['checkin'],
                'update' => NOW,
                'terminal' => 1,
                'remark' => $data['remark'],
            ]);
            if (!$res) {
                throw new \Exception('order_voucher 更新失败');
            }
            $res = Order::where('order', $data['order_id'])->update([
                'status' => Status::ORDER_BOOKING,
                'sub_status' => $data['sub_status'],
                'update' => NOW,

            ]);
            if (!$res) {
                throw new \Exception('order 更新失败!');
            }

            $res = OrderExt::where('order', $data['order_id'])->update([
                'booking_time' => NOW,
            ]);
            if (!$res) {
                throw new \Exception('order_ext 更新失败!');
            }
            $sql = 'UPDATE product_voucher_booking SET `used` = `used` + 1 WHERE `item_id`=:item_id AND `date`=:date';
            $res = ProductVoucherBooking::query($sql, ['item_id' => $data['item_id'], 'date' => $data['checkin']]);

            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
        return true;
    }

    public static function getProductId($id)
    {
        return ProductVoucherItem::where(['id' => $id])->field('pid')->find();
    }
}
