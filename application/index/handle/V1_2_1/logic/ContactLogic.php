<?php
namespace app\index\handle\V1_2_1\logic;

use lib\Log;
use lib\Redis;
use lib\Upload;
use app\common\model\Room;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:28
 */
class ContactLogic
{

    private $handle,$api_version;


    function __construct( $api_version )
    {
        $this->api_version = $api_version;
        $model_path = $api_version."model\ContactModel";
        $this->handle = new $model_path();
    }


    public function edit($channels,$params,$users){
        if(!isset($params['name'])||!isset($params['tel'])) error(40000,'参数不全！');
        $name       = $params['name'];
        $tel        = $params['tel'];
        $contact_id =  isset($params['contact_id'])?$params['contact_id']:'';
        $data       = [
            'uid'       =>$users,
            'channel'   =>$channels['channel'],
            'name'      =>$name,
            'mobile'    =>$tel,
            'create'    =>NOW,
        ];
        if(empty($contact_id))
        {
            $contact_id =  $this->handle->insertContact($data);
        }
        else                $this->handle->editContact($name,$tel,$contact_id);
        success(['contact_id'=>$contact_id,'operation'=>1]);
    }

    //删除联系人
    public function del($channels,$params,$users)
    {
        if(!isset($params['contact_id'])) error(40000,'参数不全！');
        $contact_id = $params['contact_id'];
        $channel    = $channels['channel'];
        $this->handle->delContact($contact_id,$channel,$users);
        success(['operation'=>1]);
    }




    public function lists($channels,$params,$users)
    {
        $channel    = $channels['channel'];
        $list       = $this->handle->getContactList($channel,$users);
        success(['list'=>$list]);
    }


    public function userinfo($channels,$params,$users)
    {
        $channel    = $channels['channel'];
        if(!isset($params['type'])) $params['type'] = 5;
        $list       = $this->handle->userinfo($channel,$users,$params['type']);
        if(isset($list[0]))
        {
            foreach ($list as $k=>$v)
            {
                $list[$k]['level']      = $v['size'];
                $list[$k]['id_card']    = $v['id_info'];
                unset($list[$k]['size']);
                unset($list[$k]['id_info']);
            }
        }
        success(['list'=>$list]);
    }




}