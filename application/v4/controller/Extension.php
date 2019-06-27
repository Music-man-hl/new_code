<?php


namespace app\v4\controller;


use app\v4\handle\logic\ExtensionLogic;

class Extension extends Base
{
    protected function access()
    {
        return [
            'commission' => ['type' => 'GET', 'lived' => false],
            'withdraw' => ['type' => 'GET', 'lived' => false],
            'detail' => ['type' => 'GET', 'lived' => false],
            'application' => ['type' => 'POST', 'lived' => false],
            'upgrade' => ['type' => 'GET', 'lived' => false],
            'extension' => ['type' => 'GET', 'lived' => false],
            'product' => ['type' => 'GET', 'lived' => false],

        ];
    }

    //产品列表
    public function commission()
    {

        return ExtensionLogic::service()->commission();

    }

    //产品详情
    public function withdraw()
    {
        return ExtensionLogic::service()->withdraw();

    }


    public function booking_calendar()
    {
        ExtensionLogic::service()->booking_calendar($this->all_param);
    }

    //申请成为推广员
    public function application()
    {
        ExtensionLogic::service()->application($this->channels['channel'], $this->users, $this->all_param);
    }

    //申请成为推广员
    public function upgrade()
    {
        ExtensionLogic::service()->upgrade($this->channels['channel']);
    }

    //推广中心
    public function extension()
    {
        ExtensionLogic::service()->extension($this->channels['channel'], $this->users);
    }

    //推广中心
    public function product()
    {
        ExtensionLogic::service()->product($this->channels['channel'], $this->users, $this->all_param);
    }

}