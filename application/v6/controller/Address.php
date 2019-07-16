<?php


namespace app\v6\controller;


use app\v6\handle\logic\AddressLogic;

class Address extends Base
{
    // 权限控制
    protected function access()
    {
        return [
            'index' => ['type' => 'GET', 'lived' => true],
            'create' => ['type' => 'POST', 'lived' => true],
            'update' => ['type' => 'PUT', 'lived' => true],
            'del' => ['type' => 'DELETE', 'lived' => true],
        ];
    }

    public function index()
    {
        return AddressLogic::service()->index();
    }

    public function create()
    {
        return AddressLogic::service()->create();
    }

    public function update()
    {
        return AddressLogic::service()->update();
    }

    public function del()
    {
        return AddressLogic::service()->del();
    }

    public function _empty($name)
    {

        error(40400, $name);

    }
}