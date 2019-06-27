<?php
namespace app\index\handle\V1_2_1\model;

use think\Db;
use lib\Error;
/**
 * 订单Model
 * X-Wolf
 * 2018-6-14
 */
class OrderModel
{

    const WAIT_PAY          = 2; //待支付
    const PAY_SUCCESS       = 3; //支付成功
    const RECEIVE           = 5; //接单getAllRoom
    const REFUND            = 7; //退款中
    const ORDER_OK          = 8; //订单完成
    const ORDER_CLOSE       = 9; //订单关闭
    
    const REFUND_APPLY      = 1;  //申请退款
    const REFUND_REFUSE     = 2;  //拒绝退款
    const REFUND_SUCCESS    = 3;  //退款成功
    
    const REFUND_CLIENT     = 1; //客户
    const REFUND_MERCHANT   = 2; //商家






    public function getShop($id)
    {
        return Db::query('SELECT s.`name` as sub_shop_name,c.`name` as shop_name,c.`group`,s.channel from shop s
                                    JOIN channel c on c.id=s.channel 
                                    WHERE s.id=:id AND s.status=1',['id'=>$id]);
    }

    public function getShopName($id)
    {
        return Db::query('SELECT s.`name` as sub_shop_name,c.`name` as shop_name,c.`group`,s.channel,s.id from shop s
                                    JOIN channel c on c.id=s.channel 
                                    WHERE s.channel=:channel ',['channel'=>$id]);
    }



    public function create($data,$snap)
    {
        Db::startTrans();
        try{

            $order_data = [
                'order'         =>$data['order'],
                'total'         =>$data['total'],
                'channel'       =>$data['channel'],
                'shop_id'       =>$data['shop_id'],
                'product'       =>$data['product'],
                'product_name'  =>$data['product_name'],
                'type'          =>$data['type'],
                'contact'       =>$data['contact'],
                'mobile'        =>$data['mobile'],
                'uid'           =>$data['uid'],
                'update'        =>NOW,
                'create'        =>NOW,
                'date'          =>strtotime(date('Y-m-d')),
                'status'        =>$data['status'],
                'ip'            =>$data['ip'],
                'expire'        =>NOW+1800,
                'pv_from'       =>$data['pv_from'],
                'terminal'      =>1,
                'count'         =>$data['count'],
            ];
            $order_id = Db::name('order')->insertGetId($order_data);
            if(empty($order_id)){
                Db::rollback();
                error(50000,'order_id 创建失败');
            }

            $order_ext_data = [
                'order_id'  =>$order_id,
                'channel'   =>$data['channel'],
                'order'     =>$data['order'],
                'remark'    =>$data['remark'],
            ];

            $res = Db::name('order_ext')->insert($order_ext_data);
            if( empty($res) ){
                Db::rollback();
                error(50000,'order_ext 创建失败');
            }

            $order_info_data = [
                'order_id'  =>$order_id,
                'channel'   =>$data['channel'],
                'order'     =>$data['order'],
                'data'      => json_encode($snap,JSON_UNESCAPED_UNICODE ),
            ];

            $res = Db::name('order_info')->insert($order_info_data);
            if( empty($res) ){
                Db::rollback();
                error(50000,'order_info 创建失败');
            }

            $order_hotel_calendar_data = [
                'channel'   =>$data['channel'],
                'order_id'  =>$order_id,
                'order'     =>$data['order'],
                'adult'     =>$data['adult'],
                'people'    =>$data['people'],
                'bed'       =>$data['bed'],
                'status'    =>0,
                'room_id'   =>$data['room_id'],
                'room_num'  =>$data['room_num'],
                'checkin'   =>$snap['checkin'],
                'checkout'  =>$snap['checkout'],
                'terminal'  =>2,
                'order_status'=>2,
                'create'=>NOW,
            ];

            $calendar_id = Db::name('order_hotel_calendar')->insertGetId($order_hotel_calendar_data);

            if(empty($calendar_id)){
                Db::rollback();
                error(50000,'calendar_id 创建失败');
            }

            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            error(50000,exceptionMessage($e));
        }
        return $order_id;
    }

    //获取门店
    function getShopIdAndName($channel,$ids){
        return Db::name('shop')->field('id,name')->where(['status'=>1,'channel'=>$channel,'id'=>$ids])->select();
    }

    //获取产品类型
    function productType(){
        return Db::name('product_type')->field('id,name')->select();
    }

    function getOrders($channels,$users,$limit,$status)
    {
        if($status)
        {
            $where  = 'AND o.status= '.$status;
        }
        else
        {
            $where = '';
        }
        $field = 'o.`order`,o.`goods_code`,o.`create`,o.`sub_status`,o.`status`,o.`total`,o.`count`,o.`product`,o.`product_name`,o.`expire`,o.`type`,i.data,o.`refund_status`,o.`product`,o.`status`,o.`refund`,o.`rebate`,o.`sales_rebate`,o.`shop_id`';

        $sql    = 'SELECT '.$field.' FROM `order` o 
                   LEFT JOIN `order_info` i ON o.id=i.order_id
                   WHERE o.channel=:channel  AND o.uid=:uid '.$where.' ORDER BY o.create DESC LIMIT '.$limit['start'].','.$limit['limit'];
        return  Db::query($sql,['channel'=>$channels['channel'],'uid'=>$users]);

    }


    //获取总数
    function getOrdersCount($channels,$users,$status)
    {
        if(!empty($status))
        {
            $where  = 'AND o.status= '.$status;
        }
        else
        {
            $where = '';
        }
        $field  = 'count(*) as count';

        $sql    = 'SELECT '.$field.' FROM `order` o 
                   LEFT JOIN `order_info` i ON o.id=i.order_id
                   WHERE o.channel=:channel  AND o.uid=:uid '.$where.' ';

        return Db::query($sql,['channel'=>$channels['channel'],'uid'=>$users]);
    }

    function getOrderById($channels,$users,$order)
    {

        $field = 'o.*,i.data,e.`remark`,e.assist_check_no,e.qrcode_img_url';

        $sql    = 'SELECT '.$field.' FROM `order` o 
                   LEFT JOIN `order_info` i ON o.id=i.order_id
                   LEFT JOIN `order_ext`  e ON o.id=e.order_id
                   WHERE o.channel=:channel  AND o.uid=:uid AND o.order=:order';

        return Db::query($sql,['channel'=>$channels['channel'],'uid'=>$users,'order'=>$order]);

    }


    //获取全部room
    function getAllRoom($channel)
    {
        return Db::table('hotel_room_type')->field('id,name,bucket,cover')->where(['channel'=>$channel])->select();
    }


    //获取联系人
    public function getContactByUid($id)
    {
        return Db::table('order_contact')->where(['uid'=>$id])->find();
    }

    //获取退款类型
    public function getRefundType($type)
    {
        return Db::table('order_refund_type')->where(['type'=>$type])->select();
    }

    //申请退款
    public function refund($refund,$refund_log)
    {
        Db::startTrans();
        try{

            $refund_id = Db::name('order_refund')->insertGetId($refund);
            if(empty($refund_id)){
                Db::rollback();
                error(50000,'refund_id 创建失败');
            }

            $refund_log['refund_id'] =$refund_id;
            $res = Db::name('order_refund_log')->insert($refund_log);
            if( empty($res) ){
                Db::rollback();
                error(50000,'refund_log 创建失败');
            }

            $res = Db::name('order')->where(['order'=>$refund['order']])->update(['refund_status'=>'1']);
            if( empty($res) ){
                Db::rollback();
                error(50000,'update 创建失败');
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            error(50000,exceptionMessage($e));
        }
        return $refund_id;
    }


    //重复申请退款
    public function refundAgain($refund,$refund_log,$refundId)
    {
        Db::startTrans();
        try{

            $refund_id = Db::name('order_refund')->where(['order'=>$refund['order']])->update($refund);
            if(empty($refund_id)){
                Db::rollback();
                error(50000,'修改失败');
            }

            $refund_log['refund_id'] =$refundId;
            $res = Db::name('order_refund_log')->insert($refund_log);
            if( empty($res) ){
                Db::rollback();
                error(50000,'refund_log 创建失败');
            }

            $res = Db::name('order')->where(['order'=>$refund['order']])->update(['refund_status'=>'1']);
            if( empty($res) ){
                Db::rollback();
                error(50000,'update 创建失败');
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            error(50000,exceptionMessage($e));
        }
        return $refund_id;
    }

    public function getRefundReason($type)
    {
        return Db::table('order_refund_type')->where(['type'=>$type])->field('id,name as reason')->select();
    }

    //获取退款订单
    public function RefundOrder($orderId){
        return Db::table('order_refund')->field('order_id,id')
            ->where(['order_id'=>$orderId])->find();
    }

    //获取订单wo
    public function getOrder($channel,$order){

        return Db::table('order o')->field('o.*,e.pay_count')
            ->join('order_ext e','e.order_id=o.id')
            ->where(['o.channel'=>$channel,'o.order'=>$order])->find();

    }
    //更新pay_count
    public function updatePayCount($order_id){
        return Db::table('order_ext')->where('order_id',$order_id)->inc('pay_count')->update();
    }

    //插入支付日志
    public function orderPayLog($channel,$order,$data,$create){
        return Db::table('order_pay_log')->insertGetId(['channel'=>$channel,'order'=>$order,'data'=>$data,'create'=>$create]);
    }

    // 记录支付prepay_id信息
    public function handlerecordInformMsg($order,$prepayId,$appid,$openId)
    {
        $exist = Db::table('inform_msg')->where('order',$order)->find();

        $data = [
            'prepay_id'     =>  $prepayId,
            'appid'         =>  $appid,
            'openid'        =>  $openId,
            'pay_time'      =>  NOW
        ];

        if($exist){
            // 更新信息
            return Db::table('inform_msg')->where('order',$order)->update($data);
        }else{
            // 插入信息
            $data['order'] = $order;
            return Db::table('inform_msg')->insert($data);
        }
    }

    public function getCoupon($coupon,$user,$channel)
    {
        return Db::table('coupon co')->field('c.type,c.coupon_id,c.value,c.limit,c.start,c.end,c.status,c.lock_time')
            ->join('coupon_code c','c.coupon_id=co.id')
            ->where(['c.channel'=>$channel,'c.id'=>$coupon,'c.uid'=>$user])->find();
    }
    public function getProductByCoupon($coupon,$product,$channel,$type){
        return Db::table('coupon_product')->field('product_id')
            ->where(['channel'=>$channel,'coupon_id'=>$coupon,'product_type'=>$type])->select();
    }

    public function getCouponByPro($coupon,$product,$channel,$type){
        $sql    = 'select count(p.id) as totalNum from  coupon_code c 
                Left JOIN  coupon co on co.id=c.coupon_id
                LEFT JOIN  coupon_product p ON p.coupon_id=c.coupon_id 
                WHERE c.id=:id
                GROUP BY  co.id';

        return Db::query($sql,['id'=>$coupon]);
    }

    //获取优惠券产品限制的个数
    public function getProductByCouponCount($coupon){
        return Db::table('coupon_product')->where('coupon_id',$coupon)->count();
    }

}