<?php
/**
 * Created by PhpStorm.
 * User: 总裁
 * Date: 2018/6/20
 * Time: 18:37
 */

namespace app\index\handle\V1_2_1\hook\ticket;

use app\index\model\Coupon;
use app\index\model\CouponCode;
use app\index\model\MpOrder\OrderTask;
use app\index\model\OrderBookingUserinfo;
use app\index\model\OrderContact;
use app\index\model\OrderExt;
use app\index\model\OrderPayLog;
use app\index\model\OrderTicket;
use app\index\model\Product;
use app\index\model\ProductTicketBooking;
use app\index\model\ProductTicketItem;
use app\index\model\Shop;
use app\index\model\User;
use app\index\Services\IdCardCheck;
use app\index\Services\RabbitMQ;
use Exception;
use lib\MyLog;
use lib\Status;
use think\Db;
use think\db\Query;
use third\S;

class OrderLogic
{
    public static function create($data)
    {
        if (empty($data['ticket_id']) || !isset($data['count'])) {
            error(40000, '参数不全');
        }
        $count = (int)$data['count'];
        $productType = 2;//产品类型
        $channel = $data['channel'];
        $shopId = $data['shop_id'];
        $totalPrice = $data['total_price'];
        $contactId = $data['contact_id'];
        $order = $data['order'];
        $userId = $data['user_id'];
        $ticketId = $data['ticket_id'];
        $useDate = $data['use_date'];//使用日期
        //校验参数
        $productTicketItem = ProductTicketItem::hasWhere('Product', function (Query $query) use ($shopId) {
            $query->where(['Product.status' => Product::STATUS_VALID, 'shop_id' => $shopId])
                ->where('start', '<', NOW)
                ->where('end', '>', NOW);
        })
            ->where('ProductTicketItem.id', $ticketId)
            ->where('ProductTicketItem.channel', $channel)
            ->find();

        if (!$productTicketItem) {
            error(40000, '产品不存在！');
        }
        $product = $productTicketItem->product;

        $shop = Shop::where(['id' => $shopId, 'status' => 1])->find();
        if (!$shop) {
            error(40000, '没有找到店铺');
        }

        if (!$useDate) {
            error(40000, '使用日期未选');
        }

        if (!isset($data['guest_info'])) {
            return error(40000, '出行人信息未填写');
        } else {
            $check = self::checkGuestInfo($productTicketItem->booking_info, count($data['guest_info']), $data['count']);
            if (!$check) {
                return error(50000, '出行人信息错误');
            }
            $people = self::createBookingUserInfo($data['guest_info'], $channel, $userId);
        }

        if ($count < $productTicketItem['min'] || $count > $productTicketItem['max']) {
            error(40000, '购买数量有误！');
        }

        $total_order = OrderTicket::lockCount($ticketId);
        $ticketBooking = ProductTicketBooking::where('item_id', $ticketId)
            ->where('date', strtotime($useDate))
            ->where('status', ProductTicketBooking::STATUS_OPEN)
            ->available($productTicketItem)
            ->find();

        if (!$ticketBooking) {
            error(40000, '该日期不可预约！');
        }
        if (($ticketBooking['allot'] - $ticketBooking['used'] - $total_order - $count) < 0) {
            error(40000, '库存不足！');
        }

        //校验库存和价格是否一致
        if (bcmul($ticketBooking['sale_price'], $count, 2) != $totalPrice) {
            error(40000, '价格不正确');
        }

        $contact = OrderContact::get($contactId);
        if (!$contact) {
            error(40800, '联系人不存在!');
        }

        if (isset($data['coupon']) && isset($data['coupon_price'])) {
            $couponId = $data['coupon'];
            $couponPrice = $data['coupon_price'];
        } else {
            $couponId = 0;
            $couponPrice = 0;
        }

        $orderData = [
            'channel' => $channel,
            'shop_id' => $shopId,
            'order' => $order,
            'pms_id' => $product['pms_id'],
            'goods_code' => $productTicketItem['goods_code'],
            'total' => $totalPrice,                        //总价
            'count' => $count,                             //数量
            'coupon_id' => $couponId,
            'coupon_price' => $couponPrice,
            'product' => $product['id'],
            'product_name' => $product['name'],
            'type' => $productType,
            'booking_info' => $productTicketItem->booking_info,
            'people' => $people,
            'contact' => $contact['name'],
            'mobile' => $contact['mobile'],
            'uid' => $userId,
            'status' => 2,
            'use_date' => $useDate,
            'ip' => getIp(),
            'pv_from' => '微信小程序',

        ];
        $snap = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_title' => $product['title'],
            'product_item_id' => $ticketId,
            'name' => $productTicketItem['name'],
            'product_item_name' => $productTicketItem['name'],
            'product_item_desc' => $productTicketItem['intro'],
            'booking_info' => $product['booking_info'],                         //预约需几个身份证
            'market_price' => $product['market_price'],
            'sale_price' => $ticketBooking['sale_price'],
            'bucket' => $product['bucket'],
            'cover' => $product['pic'],
            'price_total' => bcmul($ticketBooking['sale_price'], $count, 2),   //总价格
            'count' => $count,                                           //购买数量
            'pay_total' => $totalPrice,                                      //实付金额
            'shop_name' => $shop->getChannel->name,
            'sub_shop_name' => $shop['name'],
            'shop_group' => $shop->getChannel->group,
            'people' => $people,
            'intro' => $productTicketItem['intro'],                      //费用包含
            'rule' => $productTicketItem['use_requirements'],           //使用要求
            'refund_type' => $productTicketItem->refund_type,                  //退款规则
            'refund' => $productTicketItem['refund_reason'],              //退款说明
            'end_time' => $productTicketItem['end_time'],
            'check_in' => $useDate,
            'checkin_type' => $productTicketItem['checkin_type'],
            'use_start' => strtotime($useDate),                              // 预定游玩日期
            'tags' => $productTicketItem->tags->column('name') ?: [],
        ];
        if ($productTicketItem['use_start']) {
            $snap['booking_start'] = $productTicketItem['use_start'];
            $snap['booking_end'] = $productTicketItem['use_end'];
        } elseif ($productTicketItem['use_period'] > 0) {
            // 1天指当天有效
            $snap['booking_start'] = strtotime($useDate);
            $snap['booking_end'] = strtotime($useDate) + 86400 * ($productTicketItem['use_period'] - 1);
        } else {
            $snap['booking_start'] = strtotime($useDate);
            $snap['booking_end'] = strtotime($useDate);
        }
        if (!OrderModel::create($orderData, $snap)) {
            return error(50000, '创建订单失败！');
        }
        return true;
    }

    public static function orderDetail($order, $data)
    {
        $orderTicket = OrderTicket::where('order', $order['order'])->select();
        $used = $orderTicket->filter(function ($item) {
            return $item['status'] == Status::TICKET_COMPLETE;
        });
        if (!$orderTicket) {
            error(40000, '订单不存在！');
        }

        $ticket_status = 0;
        if (TODAY > $data['booking_end']) {
            $ticket_status = 95;  //已过期
        }

        if (in_array($order['status'], [Status::ORDER_PAY, Status::ORDER_CONFIRM]) &&
            in_array($order['refund_status'], [Status::REFUND_DEFAULT, Status::REFUND_REFUSE]) &&
            $data['refund_type'] == 1 &&
            $ticket_status != 95
        ) {
            $refund = true;
        } else {
            $refund = false;
        }

        $list = [
            "order_id" => $order['order'],
            "order_count" => $order['count'], // 订单件数
            "order_time" => date('Y-m-d H:m:s', $order['create']),
            "order_status" => $order['status'],
            "ticket_status" => $ticket_status,  //已过期
            "order_total" => floatval($order['total']),//总价
            "order_used" => count($used),
            "coupon" => $order['rebate'],//使用优惠券金额
            "pay_total" => floatval(add($order['total'], -$order['rebate'], -$order['sales_rebate'])),//实际支付金额
            "type" => '2', //产品类型
            "product_id" => encrypt($order['product'], 1),
            "product_name" => $order['product_name'],
            "product_cover" => picture($data['bucket'], $data['cover']),
            'product_desc' => isset($data['product_desc']) ? $data['product_desc'] : '',
            'product_item_id' => $data['product_item_id'],
            'product_item_name' => $data['product_item_name'],
            "product_item_price" => floatval($order['total'] / $order['count']), // 该订单预定期间的单价
            'end_booking_time' => $data['end_time'],//xx:xx前可预订
            'use_date' => $data['check_in'],//游玩日期
            'checkin_type' => $data['checkin_type'],//入园方式
            'user_type' => $data['booking_info'],//是否需填写身份证
            "code" => [
                "qrcode" => $order['qrcode_img_url'],
                "credential_code" => $order['assist_check_no'],
            ],
            'people' => $data['people'],
            'tags' => $data['tags'],
            "contact" => [
                "name" => $order['contact'],
                "mobile" => $order['mobile']
            ],
            "refund" => [
                'is_refundable' => $refund,
                'status' => $order['refund_status'],
            ],
            "intro" => $data['intro'],//费用包含
            "use_rule" => $data['rule'],//使用要求
            "refund_rule" => $data['refund'],//退款规则
        ];

        return $list;
    }

    public static function createBookingUserInfo($guestUsers, $channel, $userId)
    {
        $user_info = [];
        foreach ($guestUsers as $guest) {
            if (!isset($guest['name'])) {
                return error(40000, '出行人姓名未填');
            }
            if (!isset($guest['id_card'])) {
                return error(40000, '出行人身份证未填');
            }
            if (!IdCardCheck::service()->isIDCard($guest['id_card'])) {
                return error(40000, '身份证格式错误');
            }
            $user_info[] = [
                'channel' => $channel,
                'uid' => $userId,
                'type' => 2,
                'name' => $guest['name'],
                'id_card' => $guest['id_card'],
                'size' => 1
            ];
        }
        try {
            OrderBookingUserinfo::create($user_info);
            return $user_info;
        } catch (Exception $e) {
            return error(50000, exceptionMessage($e));
        }
    }

    //支付校验
    public static function payCreateVild($orders)
    {
        $itemId = OrderTicket::where('order', $orders['order'])->value('item_id');
        $item = ProductTicketItem::get($itemId);
        if (empty($item)) {
            error(50000, '此产品已经发生变化，请重新下单');
        }
        return true;
    }

    //支付回调
    public static function pay($getOrder, $param)
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
            OrderPayLog::addLog($channel, $order, '支付的金额不正确');
            return false;
        }

        //查询订单中的券和产品
        $orderTickets = Db::name('order_ticket')->where('order_id', $order_id)->find();
        if (!$orderTickets) {
            OrderPayLog::addLog($channel, $order, 'order_ticket 没有找到');
            return false;
        }

        $count = $getOrder['count'];//更新used
        $itemId = $orderTickets['item_id'];//券id
        $productId = $getOrder['product'];

        //查询优惠券
        $coupon = '';
        if ($getOrder['coupon_id']) {
            $coupon = CouponCode::field('id,coupon_id')->where('id', $getOrder['coupon_id'])->where('status', 0)->find();
        }

        Db::startTrans();
        try {
            $res = \app\index\model\Order::where('id', $order_id)->update([
                'status' => $getOrder['goods_code'] ? $order_status : Status::ORDER_CONFIRM,
                'pay_type' => $pay_type,
                'pay_time' => NOW,
                'update' => NOW,
            ]);
            if (!$res) {
                throw new Exception('order 更新失败');
            }

            $order_ext_update = [
                'pay_account' => '微信',
                'pay_trade' => $param['transaction_id'],
                'out_trade_no' => $param['out_trade_no'],
                'total_fee' => $total_fee
            ];

            if (!$getOrder['goods_code']) {
                //不对接
                $res = OrderTicket::where('order_id', $order_id)->update(['status' => Status::TICKET_CONFIRM, 'update' => NOW]);
                if (!$res) {
                    throw new Exception('order_ticket 更新失败!');
                }
                $order_ext_update['confirm_time'] = NOW;
                $order_ext_update['assist_check_no'] = NOW . mt_rand(10000, 99999);//不对接的辅助检票码
            }

            $res = OrderExt::where('order_id', $order_id)->update($order_ext_update);
            if (!$res) {
                throw new Exception('order_ext 更新失败');
            }

            //接下来更新产品和券
            $res = ProductTicketItem::where('id', $itemId)->inc('sales', $count)->update();
            if (!$res) {
                throw new Exception('product_ticket_item 更新失败');
            }
            //更新产品库存
            $res = ProductTicketBooking::where('item_id', $itemId)->where('date', $orderTickets['checkin'])
                ->inc('used', $count)->update();
            if (!$res) {
                throw new Exception('product_ticket_booking 更新失败');
            }
            // 更新产品库存
            $res = Product::where('id', $productId)->inc('sales', 1)->update();

            if (!$res) {
                throw new Exception('product 更新失败');
            }

            $res = User::where('id', $uid)->inc('buy')->update();
            if (!$res) {
                throw new Exception('user 更新失败');
            }

            //有优惠券的情况
            if ($coupon) {
                $res = CouponCode::where('id', $coupon['id'])->update(['order' => $getOrder['order'], 'status' => 1]);
                if (!$res) {
                    throw new Exception('coupon_code 更新失败');
                }

                $res = Coupon::where('id', $coupon['coupon_id'])->inc('used')->update();
                if (!$res) {
                    throw new Exception('coupon 更新失败');
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();

            OrderPayLog::addLog($channel, $order, $e->getMessage());//记录错误信息
            S::log(exceptionMessage($e), 'error'); // 上线取消
            return false;
        }
        try {
            if ($getOrder['goods_code']) {
                self::pushToTask($getOrder);
                S::log("MQ发送 order:$order", 'info');
                RabbitMQ::service()->publish($order, config('rabbitmq.order_exchange'), config('rabbitmq.order_routing_key'));
            } else {
                //发短信
                self::sendMessage($getOrder);
            }
        } catch (Exception $e) {
            S::log(exceptionMessage($e), 'error');
            return false;
        }
        return true;
    }

    //发送短信
    public static function sendMessage($order)
    {

        $post = [
            'order' => $order->order,
            'msg_type' => 3,
            'token' => md5(config('web.pms.secret') . NOW),
            'timestamp' => NOW,
        ];

        $url = DOMAIN_MP . '/sms/midsend';
        $res = curl_file_get_contents($url, $post);

        S::log("短信发送： order:$order", $res); //线上注释

        $tpl = DOMAIN_MP . '/sms/informreceive';

        unset($post['msg_type']);
        $res = curl_file_get_contents($tpl, $post);

        S::log("模板消息发送： order:$order", $res);//线上注释

        return $res;
    }

    //对接下单数据放入order_task表
    public static function pushToTask(\app\index\model\Order $order)
    {
//        S::log("pushToTask1: ", 'warn');
        $data = [
            'order' => $order->order,
            'channel' => $order->channel,
            'shop_id' => $order->shop_id,
            'status' => 0,
            'pms_id' => $order->pms_id,
            'create' => time(),
        ];
        $orderParams = [
            'orderPrice' => $order->total,
            'linkName' => $order->contact,
            'linkMobile' => $order->mobile,
            'orderCode' => $order->order,
        ];
        $ticketParams = [];
        $people = [];

        foreach ($order->ticket as $ticket) {
            if(!empty($ticket->people)){
                $people = $ticket->people;
            }
            $ticketParams[] = [
                'orderCode' => $ticket->item_order,
                'price' => bcdiv($order->total, $order->count, 2),
                'quantity' => 1,
                'totalPrice' => bcdiv($order->total, $order->count, 2),
                'occDate' => date('Y-m-d', $ticket->checkin),
                'goodsCode' => $order->goods_code,
                'goodsName' => $order->product_name,
                'credential' => [],
            ];
        }
//        MyLog::warn(var_export($people,true));
        if(!empty($people)){
            $orderParams['certificateNo'] = $people[0]->id_card;
            $orderParams['certificateType'] = "id";
        }

        $orderParams ['ticketOrder'] = $ticketParams;
        $data['order_params'] = json_encode($orderParams);

        return OrderTask::create($data);
    }


    // 短信 -支付成功
    public static function smsPaySuccess($order)
    {
        //门票不需要发短信
        return false;
    }


    // 模板消息 - 支付数据
    public static function informPayInfo($order)
    {
        $informMsg = Db::table('inform_msg')->field('prepay_id,appid,openid')->where('order', $order['order'])->find();
        if (empty($informMsg)) {
            S::log('模板消息 - 获取支付数据 获取inform_msg数据失败 订单号:' . $order['order']);
            return false;
        }

        // 获取模板消息
        $where = ['appid' => $informMsg['appid'], 'product_type' => Status::VOUCHER_PRODUCT, 'type' => Status::INFORM_PAY_SUCCESS];
        $informTpl = Db::table('inform_tpl')->field('tpl_id,status')->where($where)->find();
        if (empty($informTpl)) {
            S::log('模板消息 - 获取支付数据 获取inform_tpl数据失败 订单号:' . $order['order']);
            return false;
        }
        if ($informTpl['status'] == Status::DISABLE) {
            S::log('模板消息 - 获取支付数据 inform_tpl模板禁用 订单号:' . $order['order']);
            return false;
        }

        $shop = Db::table('shop')->field('name')->where('id', $order['shop_id'])->find();
        if (empty($shop)) {
            S::log('模板消息 - 获取支付数据 - 获取门店名称失败 订单号:' . $order['order']);
            return false;
        }

        $orderVoucher = Db::table('order_voucher')->field('item_id')->where('order', $order['order'])->find();
        if (empty($orderVoucher)) {
            S::log('模板消息 - 获取支付数据 - 获取产类产品订单失败 订单号:' . $order['order']);
            return false;
        }
        $item = Db::table('product_voucher_item')->where('id', $orderVoucher['item_id'])->find();

        if (empty($item)) {
            S::log('模板消息 - 获取支付数据 - 获取券类产品失败 订单号:' . $order['order']);
            return false;
        }

        $orderExt = Db::table('order_ext')->field('total_fee')->where('order', $order['order'])->find();
        if (empty($orderExt)) {
            S::log('模板消息 - 获取支付数据 - 获取真实支付的金额失败 订单号:' . $order['order']);
            return false;
        }

        $itemName = self::formatItemName($item);
        $keywords = [
            'keyword1' => ['value' => $order['product_name']],//商品名称
            'keyword2' => ['value' => $itemName],//订单内容
            'keyword3' => ['value' => $orderExt['total_fee']],//金额
            'keyword4' => ['value' => $order['order']],//订单号
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

    public static function refund($order, $user)
    {
        $data = OrderModel::getTicketByOrderId($order['order'], '', $user);
        $status_vaild = [Status::TICKET_DEFAUT, Status::TICKET_CONFIRM];
        foreach ($data as $v) {
            if (!in_array($v['ticket_status'], $status_vaild)) {
                error(40000, '券状态不允许退款');
            }
        }
    }

    public static function smsApplyRefund($order)
    {
        return OrderModel::smsApplyRefund($order);
    }

    private static function checkGuestInfo($booking_info, $number, $count)
    {
        $res = false;
        switch ($booking_info) {
            case 1:
                $res = true;
                break;
            case 2:
                if ($number == 1) {
                    $res = true;
                }
                break;
            case 3:
                if ($number == $count) {
                    $res = true;
                }
                break;
        }
        return $res;
    }

    public static function getProductId($id)
    {
        $id = encrypt($id, 2, false);
        $data = OrderModel::getProductId($id);
        $product = OrderModel::getProductById($data['pid']);
        if ($product['is_coupons'] == '0') {
            error(40000, '该产品不支持优惠券！');
        }
        return $data['pid'];
    }
}
