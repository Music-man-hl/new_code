<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\v6\handle\hook\hotel;


use app\v6\model\Main\InformTpl;
use app\v6\model\Main\Shop;
use app\v6\model\Shop\Coupon;
use app\v6\model\Shop\CouponCode;
use app\v6\model\Shop\HotelBooking;
use app\v6\model\Shop\HotelRoomType;
use app\v6\model\Shop\InformMsg;
use app\v6\model\Shop\InformSend;
use app\v6\model\Shop\MessageSend;
use app\v6\model\Shop\Order;
use app\v6\model\Shop\OrderContact;
use app\v6\model\Shop\OrderExt;
use app\v6\model\Shop\OrderHotelCalendar;
use app\v6\model\Shop\OrderInfo;
use app\v6\model\Shop\OrderPayLog;
use app\v6\model\Shop\ShopPicture;
use app\v6\model\Shop\User;
use app\v6\model\Shop\WeixinUser;
use lib\Redis;
use lib\Status;
use third\S;

class OrderQuery
{
    //插入支付日志
    static function pay($getOrder, $param)
    {

        $order_status = Status::ORDER_PAY;//支付成功
        $pay_type = Status::PAY_WEIXIN;//微信支付

        $channel = $getOrder['channel'];//渠道
        $uid = $getOrder['uid'];//用户id

        $order_id = $getOrder['id'];//订单id
        $order = $getOrder['order'];

        $total = add($getOrder['total'], -$getOrder['rebate'], -$getOrder['sales_rebate']); //总价格
        $total_fee = bcdiv($param['total_fee'], 100, 2);

        if (bccomp($total, $total_fee, 2)) { //金额不相等
            self::orderPayLog($channel, $order, '支付的金额不正确');
            return false;
        }

        $order_ext_data = OrderExt::where('order_id', $order_id)->find();
        if (empty($order_ext_data)) {
            self::orderPayLog($channel, $order, 'order_ext 没有找到');
            return false;
        }

        $order_hotel_calendar_data = OrderHotelCalendar::where('order_id', $order_id)->find();
        if (empty($order_hotel_calendar_data)) {
            self::orderPayLog($channel, $order, 'order_hotel_calendar 没有找到');
            return false;
        }

        $count = (int)$order_hotel_calendar_data['room_num'];//更新used
        $checkin = (int)$order_hotel_calendar_data['checkin'];
        $checkout = (int)$order_hotel_calendar_data['checkout'];

        $room = $getOrder['product'];
        $date = [];

        for ($i = $checkin; $i < $checkout; $i += 86400) {
            $date[] = $i;
        }

        $hotel_booking_data = HotelBooking::where([
            'channel' => $channel,
            'room' => $room,
            'date' => $date
        ])->select();

        if (empty($hotel_booking_data)) {
            self::orderPayLog($channel, $order, 'hotel_booking 没有找到');
            return false;
        }

        $ids = $hotel_booking_data->column('id');
        if (empty($ids)) {
            self::orderPayLog($channel, $order, 'hotel_booking_data Ids 没有数据');
            return false;
        }

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

            $res = OrderHotelCalendar::where('order_id', $order_id)->update([
                'status' => $order_status,
                'order_status' => $order_status,
            ]);

            if (!$res) {
                throw new \Exception('order_hotel_calendar 更新失败');
            }

            $res = HotelBooking::whereIn('id', $ids)->inc('used', $count)->update();

            if (!$res) {
                throw new \Exception('hotel_booking 更新失败');
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

            self::orderPayLog($channel, $order, $e->getMessage());//记录错误信息

            S::log(exceptionMessage($e), 'error'); // 上线取消

            return false;
        }

        return true;

    }

    //支付

    static function orderPayLog($channel, $order, $data)
    {
        return OrderPayLog::insertGetId(['channel' => $channel, 'order' => $order, 'data' => $data, 'create' => NOW]);
    }

    // 短信 -支付成功

    static function smsPaySuccess($order)
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

        $params = [
            'name' => $user['nickname'],
            'sub_shop_name' => $shop['name'],
            'room_type_name' => $order['product_name'],
            'mobile' => ($shop['tels']['citycode'] ? $shop['tels']['citycode'] . '-' : $shop['tels']['citycode']) . $shop['tels']['tel'],
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


    static function sendWxTmp($order)
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

    static private function getSendTmpKey($appid)
    {
        return redis_prefix() . '_sendTmp_' . md5($appid);
    }

    // 短信 - 申请退款
    static function smsApplyRefund($order)
    {
        $shop = Shop::where(['id' => $order['shop_id']])->with(['tels' => function ($query) {
            $query->where('type', 1);
        }])->find();

        if (empty($shop)) {
            S::log('发送申请退款短信 - 获取门店名称失败 订单:' . $order['order']);
            return false;
        }

        $params = [
            'sub_shop_name' => $shop['name'],
            'room_type_name' => $order['product_name'],
            'order' => $order['order'],
            'mobile' => ($shop->tels['citycode'] ? $shop->tels['citycode'] . '-' : $shop->tels['citycode']) . $shop->tels['tel']
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
    static function informPayInfo($order)
    {
        $informMsg = InformMsg::field('prepay_id,appid,openid')->where('order', $order['order'])->find();
        if (empty($informMsg)) {
            S::log('模板消息 - 获取支付数据 获取inform_msg数据失败 订单号:' . $order['order']);
            return false;
        }

        // 获取模板消息
        $where = ['appid' => $informMsg['appid'], 'product_type' => Status::CALENDAR_PRODUCT, 'type' => Status::INFORM_PAY_SUCCESS];
        $informTpl = InformTpl::field('tpl_id,status')->where($where)->find();
        if (empty($informTpl)) {
            S::log('模板消息 - 获取支付数据 获取inform_tpl数据失败 订单号:' . $order['order']);
            return false;
        }

        if ($informTpl['status'] == Status::DISABLE) {
            S::log('模板消息 - 获取支付数据 inform_tpl模板禁用 订单号:' . $order['order']);
            return false;
        }

        $shop = Shop::field('name')->where('id', $order['shop_id'])->find();
        if (empty($shop)) {
            S::log('模板消息 - 获取支付数据 - 获取门店名称失败 订单号:' . $order['order']);
            return false;
        }

        $product = HotelRoomType::field('name')->where('id', $order['product'])->find();
        if (empty($product)) {
            S::log('模板消息 - 获取支付数据 - 获取房型信息失败 订单号:' . $order['order']);
            return false;
        }

        $orderExt = OrderExt::field('total_fee')->where('order', $order['order'])->find();
        if (empty($orderExt)) {
            S::log('模板消息 - 获取支付数据 - 获取真实支付的金额失败 订单号:' . $order['order']);
            return false;
        }

        $keywords = [
            'keyword1' => ['value' => $shop['name']],//门店名称
            'keyword2' => ['value' => $product['name']],//房型名称
            'keyword3' => ['value' => $orderExt['total_fee']],//金额
            'keyword4' => ['value' => $order['order']],//订单号
            'keyword5' => ['value' => '正在为您确认房间，请耐心等待']
        ];

        $data = [
            'touser' => $informMsg['openid'],
            'template_id' => $informTpl['tpl_id'],
            'form_id' => $informMsg['prepay_id'],
            'data' => $keywords,
            'appid' => $informMsg['appid'],
            'page' => '/pages/order/detail?order_id=' . $order['order'] . '&sub_status=0',
        ];

        S::log('模板消息 - 发送的数据:' . json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;

    }

    // 模板消息 - 支付
    static function informPay($order, $tpl, $errcode, $errmsg)
    {
        $inform = [
            'channel' => $order['channel'],
            'order' => $order['order'],
            'product_type' => Status::CALENDAR_PRODUCT,
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

    //获取房型
    static function getRoomType($channel, $shop_id, $room_id)
    {
        return HotelRoomType::where(['id' => $room_id, 'status' => 1, 'channel' => $channel, 'shop_id' => $shop_id])->find();
    }


    static function getShop($id)
    {
        return Shop::query('SELECT s.`name` as sub_shop_name,c.`name` as shop_name,c.`group`,s.channel from shop s
                                    JOIN channel c on c.id=s.channel 
                                    WHERE s.id=:id AND s.status=1', ['id' => $id]);
    }

    static function getBooking($where)
    {
        return HotelBooking::field('room,date,sale_price,allot,used')->where($where)->order('date')->select();
    }

    static function getOrderCount($channel, $room, $date, $status, $time)
    {
        return OrderHotelCalendar::query('select sum(`room_num`) as cou from order_hotel_calendar where `channel`=:channel AND `room_id`=:room AND `order_status`=:status AND `checkin`<=:date AND `checkout`>:datee AND `create`>:time', ['channel' => $channel, 'room' => $room, 'date' => $date, 'datee' => $date, 'status' => $status, 'time' => $time]);
    }

    //获取联系人
    static function getContact($id)
    {
        return OrderContact::where(['id' => $id])->find();
    }

    static function getPic($shop_id, $type = 1)
    {
        return ShopPicture::where(['shop' => $shop_id, 'type' => $type])->field('cover,bucket')->select();
    }

    //订单创建
    static function create($data, $snap)
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
                'extension_user' => $data['extension_user'],
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
                'remark' => $data['remark'],
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

            $order_hotel_calendar_data = [
                'channel' => $data['channel'],
                'order_id' => $order_id,
                'order' => $data['order'],
                'adult' => $data['adult'],
                'people' => $data['people'],
                'bed' => $data['bed'],
                'status' => 0,
                'room_id' => $data['room_id'],
                'room_num' => $data['room_num'],
                'checkin' => $snap['checkin'],
                'checkout' => $snap['checkout'],
                'terminal' => 2,
                'order_status' => 2,
                'create' => NOW,
            ];

            $calendar_id = OrderHotelCalendar::insertGetId($order_hotel_calendar_data);

            if (empty($calendar_id)) {
                //Db::rollback();
                error(50000, 'calendar_id 创建失败');
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


    static function getProductId($id)
    {
        return HotelRoomType::where(['id' => $id])->field('hid')->find();
    }
}