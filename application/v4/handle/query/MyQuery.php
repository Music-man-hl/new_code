<?php

namespace app\v4\handle\query;

use app\v4\model\Main\Channel;
use app\v4\model\Main\ChannelInfo;
use app\v4\model\Shop\DistributionOrder;
use app\v4\model\Shop\DistributionPromotionConfig;
use app\v4\model\Shop\DistributionUser;
use app\v4\model\Shop\Message;
use app\v4\model\Shop\MessagePrevent;
use app\v4\model\Shop\Order;
use app\v4\model\Shop\User;
use think\Db;

/**
 * 门店相关操作
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:29
 */
class MyQuery
{

    const STATUS_OK = 1; //正常
    const STATUS_DELETE = 0; //删除

    const TEL_SHOP = 1; //门店类型

    const PICTURE_SCROLL = 1; //轮播图
    const PICTURE_BANNER = 2; //导航图
    const PICTURE_COVER = 3; //封面图
    const PICTURE_AROUND = 4; //周边图片

    const AROUND_DIABLE = 0;  //无效
    const AROUND_ABLE = 1;  //可用


    //获取手机号
    public function getTel($user, $channel)
    {
        return User::where('channel', $channel)->where('id', $user)
            ->field('mobile')->find();
    }

    // 获取授权信息
    public function getChannelInfoAndThirdUser($id, $type)
    {
        return ChannelInfo::where('id', $id)->with(['thirdUser' => function ($query) use ($type) {
            $query->where('type', $type)->field('appid,channel');
        }])->find();
    }

    //获取店铺信息
    public function getChannel($channel)
    {
        return Channel::where('id', $channel)
            ->field('user_cover,extension_status')->find();
    }

    //获取用户分销记录
    public function getUserDistribution($channel, $user)
    {
        return DistributionOrder::where(['channel' => $channel, 'distribution_user_id' => $user])
            ->select();
    }

    //获取用户记录
    public function getUser($channel, $user)
    {
        return DistributionUser::where(['channel' => $channel, 'userid' => $user])
            ->find();
    }

    //获取配置记录
    public function getExtensionConfig($channel)
    {
        return DistributionPromotionConfig::where(['channel' => $channel])
            ->find();
    }

    //获取用户购买记录
    public function getUserOrder($channel, $users)
    {
        return Order::where(['channel' => $channel, 'uid' => $users, 'status' => 8])
            ->select();
    }

    //获取手机号
    public function getTelByTel($tel, $channel)
    {
        return User::where('channel', $channel)->where('mobile', $tel)
            ->field('mobile')->find();
    }


    //绑定手机号
    public function bindUser($tel, $channel, $users)
    {
        //Db::startTrans();
        try {

            $res = User::where(['channel' => $channel, 'id' => $users])->update(['mobile' => $tel, 'create_time' => NOW]);
            if (empty($res)) {
                //Db::rollback();
                error(50000, 'update 创建失败');
            }
            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
    }

    //存验证码入库
    public function saveCode($code, $channel, $tel, $time)
    {
        //Db::startTrans();
        try {

            $data = [
                'channel' => $channel,
                'code' => $code,
                'mobile' => $tel,
                'create' => NOW,
                'verify' => 0,
            ];
            $id = Message::insertGetId($data);
            if (empty($id)) {
                //Db::rollback();
                error(50000, 'order_id 创建失败');
            }

            $data = [
                'channel' => $channel,
                'mobile' => $tel,
                'addtime' => $time,
            ];
            $id = MessagePrevent::insertGetId($data);
            if (empty($id)) {
                //Db::rollback();
                error(50000, 'order_id 创建失败');
            }

            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
    }

    public function getCode($tel, $channel, $create)
    {
        return Message::where('channel', $channel)
            ->where('verify', 0)
            ->where('mobile', $tel)
            ->where('create', '>', $create)
            ->order('create', 'desc')
            ->select();
    }

    public function CountCode($tel, $time, $channel)
    {
        return MessagePrevent::where('channel', $channel)
            ->where('mobile', $tel)
            ->where('addtime', $time)
            ->order('addtime', 'desc')
            ->count();
    }


}