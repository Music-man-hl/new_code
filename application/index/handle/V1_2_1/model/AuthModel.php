<?php
namespace app\index\handle\V1_2_1\model;

use lib\Error;
use think\Db;
use third\S;

/**
 * 授权相关Model
 * X-Wolf
 * 2018-6-25
 */
class AuthModel
{
    const STAT_VALID = 1; //有效

    const WEB = 1;
    const WAP = 2;
    const WECHAT = 3;
    const APPLET = 4;

    //账号类型
    const ACCOUNT_WECHAT = 1; //公众号 
    const ACCOUNT_APPLET = 2; //小程序

    // 获取授权信息
    public function getChannelInfoAndThirdUser($id,$type)
    {
        return Db::table('channel_info')->alias('i')->field('i.channel,i.status,u.appid,i.id')->leftjoin('third_user u','i.channel=u.channel')->where(['i.id'=>$id,'u.type'=>$type])->find();
    }

    // 验证用户是否存在
    public function existUser($unionId,$channel)
    {
        return Db::table('user')->field('id,stat,logins')->where(['unionid'=>$unionId,'channel'=>$channel])->find();
    }

    // 添加用户
    public function addUser($channel,$userData)
    {
        Db::startTrans();
        try {

            $data = [
                'nickname'          =>  $userData['nickName'],
                'logins'            =>  1,
                'last_login'        =>  NOW,
                'stat'              =>  self::STAT_VALID,
                'reg_from'          =>  self::APPLET,
                'last_login_from'   => self::APPLET,
                'create_time'       =>  NOW,
                'unionid'           =>  $userData['unionId'],
                'channel'           =>  $channel,
                'device'            =>  $userData['device'],
                'bucket'            =>  $userData['bucket'],
                'pic'               =>  $userData['pic'],
            ];
            $id = Db::table('user')->insertGetId($data);

            if(!$id){
                throw new \Exception('用户添加失败');
            }

            $data = [
                'nickname'      =>  $userData['nickName'],
                'sex'           =>  $userData['gender'],
                'province'      =>  $userData['province'],
                'city'          =>  $userData['city'],
                'country'       =>  $userData['country'],
                'headimgurl'    =>  $userData['avatarUrl'],
                'appid'         =>  $userData['watermark']['appid'],
                'openid'        =>  $userData['openId'],
                'unionid'       =>  $userData['unionId'],
                'channel'       =>  $channel,
                'user'          =>  $id,
                'type'          =>  self::ACCOUNT_APPLET,
                'create_time'   =>  NOW,
                'update_time'   =>  NOW,
            ];

            $row = Db::table('user_info')->insertGetId($data);
            if(!$row){
                throw new \Exception('用户信息添加失败');
            }

            Db::commit();
            return $id;
        } catch (\Exception $e) {
            Db::rollback();
            Error::set(1,$e->getMessage());
            return false;
        }

    }

    // 用户详细信息是否存在
    private function existUserDetail($channel,$unionId)
    {
        return Db::table('user_info')->field('id')->where(['channel'=>$channel,'unionid'=>$unionId])->find();
    }

    // 获取用户详情信息
    public function getUserInfoIdByUser($user)
    {
        return Db::table('user_info')->field('id')->where('user',$user)->find();
    }

    public function getUserById($id)
    {
        return Db::table('user')->where('id',$id)->find();
    }

    // 更新用户信息
    public function updateUser($id,$channel,$userData,$data)
    {
        Db::startTrans();
        try {

            $row = Db::table('user')->where('id',$id)->update($userData);

            if(!$row){
                throw new \Exception('用户更新失败');
            }

            $data = [
                'nickname'      =>  $data['nickName'],
                'sex'           =>  $data['gender'],
                'province'      =>  $data['province'],
                'city'          =>  $data['city'],
                'country'       =>  $data['country'],
                'headimgurl'    =>  $data['avatarUrl'],
                'appid'         =>  $data['watermark']['appid'],
                'openid'        =>  $data['openId'],
                'unionid'       =>  $data['unionId'],
                'type'          =>  self::ACCOUNT_APPLET,
                'update_time'   =>  NOW,
            ];

            $row = Db::table('user_info')->where(['channel'=>$channel,'user'=>$id])->update($data);

            if(!$row){
                S::log(['channel'=>$channel,'user'=>$id,'data'=>$data]);
                throw new \Exception('用户信息更新失败');
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Error::set(1,$e->getMessage());
            return false;
        }
    }

}