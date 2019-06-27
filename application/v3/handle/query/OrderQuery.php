<?php

namespace app\v3\handle\query;

use app\v3\model\Shop\Coupon;
use app\v3\model\Shop\CouponCode;
use app\v3\model\Shop\CouponProduct;
use app\v3\model\Shop\InformMsg;
use app\v3\model\Shop\Order;
use app\v3\model\Shop\OrderContact;
use app\v3\model\Shop\OrderExt;
use app\v3\model\Shop\OrderPayLog;
use app\v3\model\Shop\OrderRefund;
use app\v3\model\Shop\OrderRefundLog;
use app\v3\model\Shop\OrderRefundType;
use think\Db;

/**
 * 订单Model
 * X-Wolf
 * 2018-6-14
 */
class OrderQuery
{

    const WAIT_PAY = 2; //待支付
    const PAY_SUCCESS = 3; //支付成功
    const RECEIVE = 5; //接单getAllRoom
    const REFUND = 7; //退款中
    const ORDER_OK = 8; //订单完成
    const ORDER_CLOSE = 9; //订单关闭

    const REFUND_APPLY = 1;  //申请退款
    const REFUND_REFUSE = 2;  //拒绝退款
    const REFUND_SUCCESS = 3;  //退款成功

    const REFUND_CLIENT = 1; //客户
    const REFUND_MERCHANT = 2; //商家


    function getOrders($channels, $users, $limit, $status)
    {
        /*if ($status) {
            $where = 'AND o.status= ' . $status;
        } else {
            $where = '';
        }
        $field = 'o.`order`,o.`goods_code`,o.`create`,o.`sub_status`,o.`status`,o.`total`,o.`count`,o.`product`,o.`product_name`,o.`expire`,o.`type`,i.data,o.`refund_status`,o.`product`,o.`status`,o.`refund`,o.`rebate`,o.`sales_rebate`,o.`shop_id`';

        $sql = 'SELECT ' . $field . ' FROM `order` o
                   LEFT JOIN `order_info` i ON o.id=i.order_id
                   WHERE o.channel=:channel  AND o.uid=:uid ' . $where . ' ORDER BY o.create DESC LIMIT ' . $limit['start'] . ',' . $limit['limit'];
        return Db::query($sql, ['channel' => $channels['channel'], 'uid' => $users]);*/

        $orderQuery = Order::where('uid',$users)
            ->where('channel',$channels['channel']);
        if ($status){
            $orderQuery->where('status',$status);
        }
        return $orderQuery->limit($limit['start'],$limit['limit'])
            ->with('info')
            ->order('create','desc')
            ->select();
    }


    //获取总数
    function getOrdersCount($channels, $users, $status)
    {
        /*if (!empty($status)) {
            $where = 'AND o.status= ' . $status;
        } else {
            $where = '';
        }
        $field = 'count(*) as count';

        $sql = 'SELECT ' . $field . ' FROM `order` o 
                   LEFT JOIN `order_info` i ON o.id=i.order_id
                   WHERE o.channel=:channel  AND o.uid=:uid ' . $where . ' ';

        return Db::query($sql, ['channel' => $channels['channel'], 'uid' => $users]);*/

        $orderQuery = Order::where('uid',$users)
            ->where('channel',$channels['channel']);
        if ($status){
            $orderQuery->where('status',$status);
        }
        return $orderQuery->count();
    }

    function getOrderById($channels, $users, $order)
    {

        $field = 'o.*,i.data,e.`remark`,e.assist_check_no,e.qrcode_img_url';

        $sql = 'SELECT ' . $field . ' FROM `order` o 
                   LEFT JOIN `order_info` i ON o.id=i.order_id
                   LEFT JOIN `order_ext`  e ON o.id=e.order_id
                   WHERE o.channel=:channel  AND o.uid=:uid AND o.order=:order';

        return Order::query($sql, ['channel' => $channels['channel'], 'uid' => $users, 'order' => $order]);

    }
    
    //获取联系人
    public function getContactByUid($id)
    {
        return OrderContact::where(['uid' => $id])->find();
    }

    //获取退款类型
    public function getRefundType($type)
    {
        return OrderRefundType::where(['type' => $type])->select();
    }

    //申请退款
    public function refund($refund, $refund_log)
    {
        //Db::startTrans();
        try {

            $refund_id = OrderRefund::insertGetId($refund);
            if (empty($refund_id)) {
                //Db::rollback();
                error(50000, 'refund_id 创建失败');
            }

            $refund_log['refund_id'] = $refund_id;
            $res = OrderRefundLog::insert($refund_log);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'refund_log 创建失败');
            }

            $res = Order::where(['order' => $refund['order']])->update(['refund_status' => '1']);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'update 创建失败');
            }
            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
        return $refund_id;
    }


    //重复申请退款
    public function refundAgain($refund, $refund_log, $refundId)
    {
        //Db::startTrans();
        try {

            $refund_id = OrderRefund::where(['order' => $refund['order']])->update($refund);
            if (empty($refund_id)) {
                //Db::rollback();
                error(50000, '修改失败');
            }

            $refund_log['refund_id'] = $refundId;
            $res = OrderRefundLog::insert($refund_log);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'refund_log 创建失败');
            }

            $res = Order::where(['order' => $refund['order']])->update(['refund_status' => '1']);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'update 创建失败');
            }
            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
        return $refund_id;
    }

    public function getRefundReason($type)
    {
        return OrderRefundType::where(['type' => $type])->field('id,name as reason')->select();
    }

    //获取退款订单
    public function RefundOrder($orderId)
    {
        return OrderRefund::field('order_id,id')
            ->where(['order_id' => $orderId])->find();
    }

    //获取订单wo
    public function getOrder($channel, $order)
    {

        return Order::alias('o')->field('o.*,e.pay_count')
            ->join('order_ext e', 'e.order_id=o.id')
            ->where(['o.channel' => $channel, 'o.order' => $order])->find();

    }

    //更新pay_count
    public function updatePayCount($order_id)
    {
        return OrderExt::where('order_id', $order_id)->inc('pay_count')->update();
    }

    //插入支付日志
    public function orderPayLog($channel, $order, $data, $create)
    {
        return OrderPayLog::insertGetId(['channel' => $channel, 'order' => $order, 'data' => $data, 'create' => $create]);
    }

    // 记录支付prepay_id信息
    public function handlerecordInformMsg($order, $prepayId, $appid, $openId)
    {
        $exist = InformMsg::where('order', $order)->find();

        $data = [
            'prepay_id' => $prepayId,
            'appid' => $appid,
            'openid' => $openId,
            'pay_time' => NOW
        ];

        if ($exist) {
            // 更新信息
            return InformMsg::where('order', $order)->update($data);
        } else {
            // 插入信息
            $data['order'] = $order;
            return InformMsg::insert($data);
        }
    }

    public function getCoupon($coupon, $user, $channel)
    {
        return Coupon::alias('co')->field('c.type,c.coupon_id,c.value,c.limit,c.start,c.end,c.status,c.lock_time')
            ->join('coupon_code c', 'c.coupon_id=co.id')
            ->where(['c.channel' => $channel, 'c.id' => $coupon, 'c.uid' => $user])->find();
    }

    public function getProductByCoupon($coupon, $product, $channel, $type)
    {
        return CouponProduct::field('product_id')
            ->where(['channel' => $channel, 'coupon_id' => $coupon, 'product_type' => $type])->select();
    }

    public function getCouponByPro($coupon, $product, $channel, $type)
    {
        $sql = 'select count(p.id) as totalNum from  coupon_code c 
                Left JOIN  coupon co on co.id=c.coupon_id
                LEFT JOIN  coupon_product p ON p.coupon_id=c.coupon_id 
                WHERE c.id=:id
                GROUP BY  co.id';

        return CouponCode::query($sql, ['id' => $coupon]);
    }

    //获取优惠券产品限制的个数
    public function getProductByCouponCount($coupon)
    {
        return CouponProduct::where('coupon_id', $coupon)->count();
    }

}