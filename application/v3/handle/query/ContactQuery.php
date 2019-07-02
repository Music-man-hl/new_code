<?php

namespace app\v3\handle\query;

use app\v3\model\Shop\OrderBookingUserinfo;
use app\v3\model\Shop\OrderContact;
use think\Db;

/**
 * 门店相关操作
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:29
 */
class ContactQuery
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


    //插入联系人数据
    public function insertContact($data)
    {
        //Db::startTrans();
        try {

            $id = OrderContact::insertGetId($data);
            if (empty($id)) {
                //Db::rollback();
                error(50000, 'order_contact 创建失败');
            }
            //Db::commit();

        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
        return $id;
    }


    //修改联系人数据
    public function editContact($name, $tel, $contact_id)
    {

        //Db::startTrans();
        try {

            $res = OrderContact::where(['id' => $contact_id])->update(['name' => $name, 'mobile' => $tel]);
            if (empty($res)) {
                //Db::rollback();
                error(50000, '更新数据失败');
            }
            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
    }

    //删除联系人
    public function delContact($contact_id, $channel, $users)
    {
        //Db::startTrans();
        try {

            $res = OrderContact::where(array('id' => $contact_id, 'channel' => $channel, 'uid' => $users))->delete();
            if (empty($res)) {
                //Db::rollback();
                error(50000, '删除数据失败');
            }
            //Db::commit();
        } catch (\Exception $e) {
            //Db::rollback();
            error(50000, exceptionMessage($e));
        }
    }

    //联系人列表
    public function getContactList($channel, $uid)
    {
        return OrderContact::where(array('channel' => $channel, 'uid' => $uid))->field('id as contact_id,mobile as tel,name')->select();
    }


    public function userinfo($channel, $uid, $type)
    {
        return OrderBookingUserinfo::where(array('channel' => $channel, 'uid' => $uid, 'type' => $type))->field('name,id_info,size')->limit(0, 5)->select();
    }
}