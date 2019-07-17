<?php


namespace app\v6\handle\logic;


use app\v6\model\Shop\UserAddress;
use app\v6\Services\BaseService;
use think\facade\Request;

class AddressLogic extends BaseService
{
    protected $request;

    public function __construct()
    {
        $this->request = Request::instance();
    }

    public function index()
    {
        return UserAddress::where('user_id', $this->request->user)->order('update_time', 'desc')->select();
    }

    public function create()
    {
        $data = [
            'channel' => $this->request->channel['channelId'],
            'user_id' => $this->request->user,
            'name' => $this->request->name,
            'mobile' => $this->request->mobile,
            'province' => $this->request->province,
            'city' => $this->request->city,
            'district' => $this->request->district,
            'address' => $this->request->address,
        ];
        $result = UserAddress::create($data);
        if (!$result) {
            return error(50000, '新增收货地址失败');
        }
        return success();
    }

    public function update()
    {
        $data = [
            'id' => $this->request->id,
            'channel' => $this->request->channel['channelId'],
            'user_id' => $this->request->user,
            'name' => $this->request->name,
            'mobile' => $this->request->mobile,
            'province' => $this->request->province,
            'city' => $this->request->city,
            'district' => $this->request->district,
            'address' => $this->request->address,
            'postcode' => $this->request->postcode,
        ];

        $result = UserAddress::update($data);
        if (!$result) {
            return error(50000, '更新收货地址失败');
        }
        return success();
    }

    public function del()
    {
        if (!UserAddress::destroy($this->request->id)) {
            return error(50000, '删除失败');
        }
        return success();
    }
}