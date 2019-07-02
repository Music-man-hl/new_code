<?php
namespace app\index\handle\V1_2_1\model;

use think\Db;
use lib\Error;

/**
 * 门店相关操作
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:29
 */
class MyModel
{

    const STATUS_OK     = 1; //正常
    const STATUS_DELETE = 0; //删除

    const TEL_SHOP = 1; //门店类型

    const PICTURE_SCROLL = 1; //轮播图
    const PICTURE_BANNER = 2; //导航图
    const PICTURE_COVER  = 3; //封面图
    const PICTURE_AROUND = 4; //周边图片

    const AROUND_DIABLE = 0;  //无效
    const AROUND_ABLE   = 1;  //可用


    //获取手机号
    public function getTel($user,$channel)
    {
         return Db::name('user')->where('channel', $channel )->where('id',$user)
            ->field('mobile')->find();
    }

    //获取手机号
    public function getTelByTel($tel,$channel)
    {
         return Db::name('user')->where('channel', $channel )->where('mobile',$tel)
            ->field('mobile')->find();
    }


    //绑定手机号
    public function bindUser($tel,$channel,$users)
    {
        Db::startTrans();
        try{

            $res = Db::name('user')->where(['channel'=>$channel,'id'=>$users])->update(['mobile'=>$tel,'create_time'=>NOW]);
            if( empty($res) ){
                Db::rollback();
                error(50000,'update 创建失败');
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            error(50000,exceptionMessage($e));
        }
    }

    //存验证码入库
    public function saveCode($code,$channel,$tel,$time)
    {
        Db::startTrans();
        try{

            $data = [
                'channel'       =>$channel,
                'code'          =>$code,
                'mobile'        =>$tel,
                'create'        =>NOW,
                'verify'        =>0,
            ];
            $id = Db::name('message')->insertGetId($data);
            if(empty($id)){
                Db::rollback();
                error(50000,'order_id 创建失败');
            }

            $data = [
                'channel'       =>$channel,
                'mobile'        =>$tel,
                'addtime'       =>$time,
            ];
            $id = Db::name('message_prevent')->insertGetId($data);
            if(empty($id)){
                Db::rollback();
                error(50000,'order_id 创建失败');
            }

            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            error(50000,exceptionMessage($e));
        }
    }

    public function getCode($tel,$channel,$create)
    {
        $sql = 'SELECT `code` FROM message WHERE `channel`=:channel AND `verify`=0 AND `mobile`=:mobile AND `create`>:create ORDER BY `create` DESC';
        return Db::query($sql,array('channel'=>$channel,'mobile'=>$tel,'create'=>$create));
    }

    public function CountCode($tel,$time,$channel)
    {
        $sql = 'SELECT count(`id`) as count FROM message_prevent WHERE `channel`=:channel  AND `mobile`=:mobile AND `addtime`=:create ORDER BY `addtime` DESC';
        return Db::query($sql,array('channel'=>$channel,'mobile'=>$tel,'create'=>$time));
    }



}