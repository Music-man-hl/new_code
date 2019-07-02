<?php

namespace app\v4\handle\logic;

use app\v4\handle\query\ContactQuery;
use app\v4\Services\BaseService;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/18 0018
 * Time: 下午 15:28
 */
class ContactLogic extends BaseService
{

    private $query;


    function __construct()
    {
        $this->query = new ContactQuery();
    }


    public function edit($channels, $params, $users)
    {
        if (!isset($params['name']) || !isset($params['tel'])) error(40000, '参数不全！');
        $name = $params['name'];
        $tel = $params['tel'];
        $contact_id = isset($params['contact_id']) ? $params['contact_id'] : '';
        $data = [
            'uid' => $users,
            'channel' => $channels['channel'],
            'name' => $name,
            'mobile' => $tel,
            'create' => NOW,
        ];
        if (empty($contact_id)) {
            $contact_id = $this->query->insertContact($data);
        } else                $this->query->editContact($name, $tel, $contact_id);
        success(['contact_id' => $contact_id, 'operation' => 1]);
    }

    //删除联系人
    public function del($channels, $params, $users)
    {
        if (!isset($params['contact_id'])) error(40000, '参数不全！');
        $contact_id = $params['contact_id'];
        $channel = $channels['channel'];
        $this->query->delContact($contact_id, $channel, $users);
        success(['operation' => 1]);
    }


    public function lists($channels, $params, $users)
    {
        $channel = $channels['channel'];
        $list = $this->query->getContactList($channel, $users);
        success(['list' => $list]);
    }


    public function userinfo($channels, $params, $users)
    {
        $channel = $channels['channel'];
        if (!isset($params['type'])) $params['type'] = 5;
        $list = $this->query->userinfo($channel, $users, $params['type']);
        if (isset($list[0])) {
            foreach ($list as $k => $v) {
                $list[$k]['level'] = $v['size'];
                $list[$k]['id_card'] = $v['id_info'];
                unset($list[$k]['size']);
                unset($list[$k]['id_info']);
            }
        }
        success(['list' => $list]);
    }


}