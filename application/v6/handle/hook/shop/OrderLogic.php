<?php

namespace app\v6\handle\hook\shop;

use app\v6\model\Main\Shop;
use app\v6\model\Shop\Coupon;
use app\v6\model\Shop\CouponCode;
use app\v6\model\Shop\Order;
use app\v6\model\Shop\OrderContact;
use app\v6\model\Shop\OrderExt;
use app\v6\model\Shop\OrderInfo;
use app\v6\model\Shop\OrderPaylog;
use app\v6\model\Shop\OrderRetail;
use app\v6\model\Shop\Product;
use app\v6\model\Shop\ProductRetailItem;
use app\v6\model\Shop\User;
use app\v6\model\Shop\UserAddress;
use Exception;
use lib\Status;
use think\db\Query;
use think\facade\Request;
use third\S;

class OrderLogic
{
    public function create($data)
    {
        if (empty($data['id']) || !isset($data['count']) || !isset($data['total_price']) || !isset($data['total_fee'])) {
            error(40000, '参数不全');
        }
        $allow = ['channel', 'shop_id', 'id', 'order', 'total_price', 'total_fee', 'count', 'user_id',
            'transport_type', 'transport_fee', 'receive_address', 'take_address', 'coupon_id', 'extension_user', 'take_contact',
            'remark', 'extension_user',];
        $data = filterData($data, $allow);

        //校验参数
        $shop = Shop::where(['id' => $data['shop_id'], 'status' => 1])->find();
        if (!$shop) {
            return error(40000, '没有找到店铺');
        }

        $productRetailItem = ProductRetailItem::hasWhere('Product', function (Query $query) use ($data) {
            $query->where('status', Product::STATUS_VALID)
                ->where('Product.shop_id', $data['shop_id'])
                ->where('start', '<', NOW)
                ->where('end', '>', NOW);
        })
            ->where('ProductRetailItem.id', $data['id'])
            ->find();

        if (!$productRetailItem) {
            return error(40000, '产品不存在！');
        }

        $allowTransportType = [$productRetailItem->ext->is_self_mention ? 2 : 0, $productRetailItem->ext->is_transport ? 1 : 0];
        if (!in_array($data['transport_type'], $allowTransportType)) {
            return error(40000, '发货类型不正确');
        }
        if ($data['transport_type'] == 1 && !$data['receive_address']) {
            return error(40000, '收货地址不能为空');
        }
        if ($data['transport_type'] == 2) {
            if (!$data['take_contact']) {
                return error(40000, '自提联系人不能为空');
            }
            $takeContact = OrderContact::get($data['take_contact']);
            if (!$takeContact) {
                return error(40800, '联系人不存在!');
            }
        }

        $product = $productRetailItem->product;

        if ($data['count'] < $product['min'] || $data['count'] > $product['max']) {
            error(40000, '购买数量有误！');
        }

        $lockOrderCount = OrderRetail::alias('t')
            ->field('t.id')
            ->rightJoin('order o', 't.order=o.order')
            ->where('product_item_id', $productRetailItem->id)
            ->where('o.expire', '>', NOW)
            ->where('o.status', 2)
            ->count();


        if (($productRetailItem['allot'] - $productRetailItem['sales'] - $lockOrderCount) < $data['count']) {
            error(40000, '库存不足！');
        }

        //校验库存和价格是否一致
        if (bcmul($productRetailItem['sale_price'], $data['count'], 2) + $data['transport_fee'] != $data['total_price']) {
            error(40000, '价格不正确');
        }


        $userInfo = User::get($data['user_id']);

        $orderData = [
            'channel' => $data['channel'],
            'shop_id' => $data['shop_id'],
            'order' => $data['order'],
            'pms_id' => $product['pms_id'],
            'total' => $data['total_price'],  //总价
            'count' => $data['count'],        //数量
            'coupon_id' => $data['coupon'] ?? 0,
            'rebate' => $data['coupon_price'] ?? 0,
            'product' => $product['id'],
            'product_name' => $product['name'],
            'type' => 4,
            'contact' => $userInfo['nickname'],
            'mobile' => $userInfo['mobile'],
            'uid' => $data['user_id'],
            'status' => 2,
            'ip' => getIp(),
            'expire' => NOW + 1800,
            'pv_from' => '微信小程序',
            'terminal' => 1,
            'extension_user' => $data['extension_user'],
            'update' => NOW,
            'create' => NOW,
            'date' => strtotime(date('Y-m-d')),
        ];
        //快照
        $snap = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_title' => $product['title'],
            'product_item_id' => $productRetailItem['id'],
            'product_item_name' => $productRetailItem['name'],
            'product_item_desc' => $productRetailItem['intro'],
            'market_price' => $product['market_price'],
            'floor_price' => $productRetailItem['floor_price'],
            'sale_price' => $productRetailItem['sale_price'],
            'bucket' => $product['bucket'],
            'cover' => $product['pic'],
            'shop_name' => $shop->getChannel->name,
            'sub_shop_name' => $shop['name'],
            'shop_group' => $shop->getChannel->group,
        ];
        try {
            $order = Order::create($orderData);
            if (!$order) {
                Order::rollback();
                error(50000, 'order_id 创建失败');
            }

            $ext = OrderExt::create([
                'order_id' => $order->id,
                'channel' => $data['channel'],
                'order' => $data['order'],
            ]);
            if (empty($ext)) {
                Order::rollback();
                error(50000, 'order_ext 创建失败');
            }

            $info = OrderInfo::create([
                'order_id' => $order->id,
                'channel' => $data['channel'],
                'order' => $data['order'],
                'data' => json_encode($snap, JSON_UNESCAPED_UNICODE),
            ]);
            if (!$info) {
                Order::rollback();
                error(50000, 'order_info 创建失败');
            }

            $itemData = [
                'order_id' => $order->id,
                'order' => $data['order'],
                'product_item_id' => $data['id'],
                'transport_type' => $data['transport_type'],
                'transport_fee' => $data['transport_fee'] ?: 0,
                'receive_address' => json_encode($data['receive_address']) ?: '',
                'take_address' => json_encode($data['take_address']) ?: '',
                'take_contact' => json_encode($data['take_contact']) ?: '',
            ];
            $orderRetail = OrderRetail::create($itemData);
            if (!$orderRetail) {
                error(50000, 'order_retail创建失败');
            }
            if ($data['coupon_id']) {
                $res = CouponCode::where('id', $data['coupon_id'])->update([
                    'lock_order' => $data['order'],
                    'lock_time' => NOW + 1800,
                ]);
                if ($res === false) {
                    Order::rollback();
                    return error(50000, '优惠券更新失败');
                }
            }
            Order::commit();
        } catch (Exception $e) {
            Order::rollback();
            return error(50000, '订单生产失败');
        }
        if (isset($data['receive_address']['id'])) {
            $userAddress = UserAddress::get($data['receive_address']['id']);
            $userAddress->update_time = NOW;
            $userAddress->save();
        }

        return true;
    }


    public static function orderDetail($order, $data)
    {
        $orderRetailData = OrderRetail::where('order', $order['order'])->find();

        if (!$orderRetailData) {
            error(40000, '订单不存在！');
        }

        $refund = false;
        if (in_array($order['status'], [Status::ORDER_PAY, Status::ORDER_CONFIRM]) &&
            in_array($order['refund_status'], [Status::REFUND_DEFAULT, Status::REFUND_REFUSE]) &&
            $data['refund_type'] == 1
        ) {
            $refund = true;
        }
        $detail = [
            "order_id" => $order['order'],
            "order_status" => $order['status'],
            "receive_address" => $orderRetailData['receive_address'],
            "take_address" => $orderRetailData['receive_address'],
            "product_name" => $order['product_name'],
            "product_item_name" => $data['product_item_name'],
            "product_item_price" => floatval($order['total'] / $order['count']),
            "order_count" => $order['count'], // 订单件数
            "order_total" => floatval($order['total']),//总价
            "transport_type" => $orderRetailData['transport_type'],
            "transport_fee" => $orderRetailData['transport_fee'],
            "remark" => $order['remark'],
            "coupon" => $order['rebate'],//使用优惠券金额
            "pay_total" => floatval(add($order['total'], -$order['rebate'], -$order['sales_rebate'])),//实际支付金额
            "product_id" => encrypt($order['product'], 1),
            "product_cover" => picture($data['bucket'], $data['cover']),
            'product_desc' => $data['product_desc'] ?? '',
            'product_item_id' => $data['product_item_id'],

            "take_contact" => $orderRetailData['take_contact'],
            "refund" => [
                'is_refundable' => $refund,
                'status' => $order['refund_status'],
            ],
            "order_time" => date('Y-m-d H:m:s', $order['create']),
            "complete_time" => max($order['confirm_time'] + (14 * 24 * 60 * 60) - NOW, 0),
        ];

        return $detail;
    }

    //支付回调

    public static function payCreateVild($orders)
    {
        $itemId = OrderRetail::where('order', $orders['order'])->value('item_id');
        $item = ProductRetailItem::get($itemId);
        if (empty($item)) {
            error(50000, '此产品已经发生变化，请重新下单');
        }
        return true;
    }


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
        $orderItem = OrderRetail::where('order_id', $order_id)->find();
        if (!$orderItem) {
            OrderPayLog::addLog($channel, $order, 'item产品没有找到');
            return false;
        }

        $count = $getOrder['count'];//更新used
        $itemId = $orderItem['item_id'];//券id
        $productId = $getOrder['product'];

        //查询优惠券
        $coupon = '';
        if ($getOrder['coupon_id']) {
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
                throw new Exception('order 更新失败');
            }

            $order_ext_update = [
                'pay_account' => '微信',
                'pay_trade' => $param['transaction_id'],
                'out_trade_no' => $param['out_trade_no'],
                'total_fee' => $total_fee
            ];

            $res = OrderExt::where('order_id', $order_id)->update($order_ext_update);
            if (!$res) {
                throw new Exception('order_ext 更新失败');
            }

            //接下来更新产品和券
            $res = ProductRetailItem::where('id', $itemId)->inc('sales', $count)->update();
            if (!$res) {
                throw new Exception('product_retail_item 更新失败');
            }
            // 更新产品库存
            $res = Product::where('id', $productId)->inc('sales', $count)->update();

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

            //Db::commit();
        } catch (Exception $e) {
            //Db::rollback();

            OrderPayLog::addLog($channel, $order, $e->getMessage());//记录错误信息
            S::log(exceptionMessage($e), 'error'); // 上线取消
            return false;
        }
        return true;
    }


    public static function refund($order, $user)
    {
        $data = OrderQuery::getTicketByOrderId($order['order'], '', $user);
        $status_vaild = [Status::TICKET_DEFAUT, Status::TICKET_CONFIRM];
        foreach ($data as $v) {
            if (!in_array($v['ticket_status'], $status_vaild)) {
                error(40000, '券状态不允许退款');
            }
        }
    }

    public static function smsApplyRefund($order)
    {
        return OrderQuery::smsApplyRefund($order);
    }

    public static function getProductId($id)
    {
        $id = encrypt($id, 1, false);
        $data = OrderQuery::getProductId($id);
        $product = OrderQuery::getProductById($data['pid']);
        if ($product['is_coupons'] == '0') {
            error(40000, '该产品不支持优惠券！');
        }
        return $data['pid'];
    }

    public function complete()
    {
        $order = Order::where('order', Request::param('order_id'))->find();
        if (!$order) {
            return error(40400, '订单不存在');
        }

        $order->status = 8;
        $order->ext->complete_time = NOW;
        if (!$order->together('ext')->save()) {
            return error(50000, '订单操作失败');
        }
        return success();
    }
}
