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
            'poster' => ['type' => 'GET', 'lived' => false],
            'qrcode' => ['type' => 'GET', 'lived' => false],
            'recruit' => ['type' => 'GET', 'lived' => false],
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

    //获取当前渠道升级规则
    public function upgrade()
    {
        ExtensionLogic::service()->upgrade($this->channels['channel']);
    }

    //推广中心
    public function extension()
    {
        ExtensionLogic::service()->upgradeextension($this->channels['channel'], $this->users);
    }

    //推广商品
    public function product()
    {
        ExtensionLogic::service()->product($this->channels['channel'], $this->users, $this->all_param);
    }

    //单产品推广图
    public function poster()
    {
        ExtensionLogic::service()->poster($this->users, $this->all_param);
    }

    //获取二维码
    public function qrcode()
    {
        ExtensionLogic::service()->qrcode($this->channels['channel'], $this->all_param, $this->users);
    }

    //获取招募页规则
    public function recruit()
    {
        ExtensionLogic::service()->recruit($this->channels['channel']);
    }

}