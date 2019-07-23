<?php


namespace app\v6\handle\logic;


use app\v6\model\Main\Channel;
use app\v6\model\Shop\DistributionOrder;
use app\v6\model\Shop\DistributionProduct;
use app\v6\model\Shop\DistributionPromotionConfig;
use app\v6\model\Shop\DistributionQuestion;
use app\v6\model\Shop\DistributionRecruitPage;
use app\v6\model\Shop\DistributionUpgradeCondition;
use app\v6\model\Shop\DistributionUser;
use app\v6\model\Shop\DistributionUserApply;
use app\v6\model\Shop\DistributionWithdrawHistory;
use app\v6\model\Shop\Order;
use app\v6\model\Shop\ProductUnion;
use app\v6\model\Shop\User;
use app\v6\model\Shop\UserInfo;
use app\v6\Services\BaseService;
use app\v6\Services\EasyWeChat;
use app\v6\Services\RabbitMQ;
use lib\MyLog;
use think\facade\Env;
use think\facade\Request;

class ExtensionLogic extends BaseService
{
    const HANDLING_FEE = 0.006;

    public function commission()
    {
        $request = request();
        $limit = startLimit($request->param());
        $profitHistory = DistributionOrder::hasWhere('orderInfo', function ($query) {
            $query->where('status', '<>', 9);
        })
            ->where('distribution_user_id', $request->user)
            ->with(['orderInfo' => function ($query) {
                $query->field('product_name,order, date, status, uid')
                    ->with(['userInfo' => function ($query) {
                        $query->field('user, nickname, headimgurl');
                    }]);
            }])
            ->limit($limit['start'], $limit['limit'])
            ->order('create_time', 'desc')
            ->select();

        $profit = DistributionOrder::hasWhere('orderInfo', function ($query) {
            $query->where('status', '<>', 9);
        })->where('distribution_user_id', $request->user)
            ->sum('commission');

        $canWithdrawal = DistributionUser::where('userid', $request->user)->value('money');

        return success(['can_withdrawal' => round($canWithdrawal, 2), 'profit' => round($profit, 2), 'profit_history' => $profitHistory]);
    }

    public function withdraw()
    {
        Request::only(['payee', 'amount']);
        $payee = Request::param('payee');
        $amount = Request::param('amount');

        if (!$payee || !is_string($payee)) {
            return error(40122);
        }
        if (!$amount || !is_numeric($amount)) {
            return error(40122);
        }
        if ($amount < 10) {
            return error(40112, '提现金额必须大于10元');
        }
        if ($amount > 5000) {
            return error(40112, '提现金额必须小于5000元');
        }

        $userId = request()->user;
        $user = DistributionUser::where('userid', $userId)->find();
        if ($amount > $user['money']) {
            return error(40112, '超出可提现金额');
        }

        $todayCount = DistributionWithdrawHistory::where('user_id', $userId)->where('create_time', '>=', date('Y-m-d 00:00:00'))
            ->where('create_time', '<=', date('Y-m-d 23:59:59'))->count();
        if ($todayCount >= 3) {
            return error(40112, '一天最多只能提现3次');
        }

        $openId = UserInfo::where('user', $user['userid'])->value('openid');
        if (!$openId) {
            return error(50000, '未找到OpenId');
        }

        $realAmount = $amount * 100;
        $result = EasyWeChat::service()->transferToBalance($payee, $realAmount, '提现', $openId);
        MyLog::info('提现信息:金额:' . $realAmount . 'msg' . json_encode($result));

        if ($result['result_code'] !== 'SUCCESS') {
            MyLog::error('提现失败:金额:' . $amount . 'msg' . json_encode($result));
            return error(40112, $result['err_code_des']);
        }

        $user->withdrawal = $user->withdrawal + $amount;
        $user->money = $user->money - $amount;
        if (!$user->save()) {
            $data = json_encode(['userId' => $userId, 'amount' => $amount]);
            MyLog::error('提现保存失败:' . $data);
            return error('50000', '提现保存失败');
        }

        $dwh = new DistributionWithdrawHistory;
        $dwh->user_id = $userId;
        $dwh->withdraw = $amount;
        if (!$dwh->save()) {
            MyLog::error('提现记录保存失败:' . json_encode($dwh));
        }
        return success();
    }

    public function product_can($user)
    {
        $productId = encrypt(Request::param('product_id'), 1, false);
        $productType = Request::param('product_type');
        if ($productType == 1) {
            $productId = encrypt(Request::param('product_id'), 6, false);
        }
        $isExtension = Channel::where('id', request()->channel['channelId'])->where('extension_status', 1)->find();
        $dp = DistributionProduct::where('id', $productId)->where('type', $productType)
            ->where('status', DistributionProduct::AVAILABLE_STATUS)->find();
        $userInfo = DistributionUser::where('userid', $user)->where('status', DistributionUser::AVAILABLE_STATUS)->find();
        if ($dp && $userInfo && $isExtension) {
            $can = 1;
        } else {
            $can = 0;
        }
        return success([
            'can' => $can,
            'icon' => 'https://article-pic.feekr.com/pic/icon/earn.png',
            'poster' => DistributionProduct::DEFAULT_POSTER,
            'user_id' => $user
        ]);
    }

    public function sendMq($order)
    {
        $orderModel = Order::where('order', $order)->find();
        if ($orderModel->extension_user) {
            $data = [
                'channel' => $orderModel->channel,
                'shop' => $orderModel->shop_id,
                'order' => $order,
                'uid' => $orderModel->extension_user
            ];
            MyLog::info('[rabbitMQ-start]->推广订单事件推送开始');
            RabbitMQ::service()->publish(json_encode($data), config('rabbitMQ.extension_exchange'), config('rabbitMQ.extension_routing_key'));
            MyLog::info('[rabbitMQ-end]->推广订单事件推送结束');
        }
    }

    //申请
    public function application($channel, $userId, $params)
    {
        if (!isset($params['from_id'])) {
            error(40000, "from_id必传");
        }
        $fromId = $params['from_id'];
        //根据当前用户配置判断
        //判断是否开启分销
        $channelInfo = Channel::field('extension_status')->where(['id' => $channel])->find();
        $ex_status = $channelInfo['extension_status'];
        if ($ex_status == 2) {
            error(50000, "商家关闭了分销功能,请联系商家！");
        }

        $shopConfig = DistributionPromotionConfig::field('id,channel,is_apply,is_condition,is_review,is_notice,level_up_node')->where(['channel' => $channel])->find();
        //判断是否允许加入
        if ($shopConfig['is_apply'] == 2) {
            error(50000, "商家关闭了用户申请,请联系商家！");
        }

        //判断是否有条件
        switch ($shopConfig['is_condition']) {
            case 1://无条件加入
                $this->promotion($userId, $fromId, $channel, $shopConfig['is_review']);
                break;
            case 2://需购买任意商品
                //判断用户是否购买过任意商品
                $checkUser = Order::where(['uid' => $userId, 'status' => 8])->select();
                if (count($checkUser) > 0) {
                    $this->promotion($userId, $fromId, $channel, $shopConfig['is_review']);
                    break;
                } else {
                    success(['type' => 6, 'msg' => "必须购买过商品才能申请成为推广员"]);
                }
        }
    }

    //申请成为推广员
    public function promotion($userId, $fromId, $channel, $isReview)
    {
        //判断用户是否已经是推广员
        $checkUser = DistributionUser::where(['channel' => $channel, 'userid' => $userId])->find();
        if ($checkUser) {
            if ($checkUser['status'] == 1) {
                success(['type' => 4, 'msg' => "用户已经是推广员"]);
            } else {
                success(['type' => 7, 'msg' => "用户推广员被禁用"]);
            }
        }
        //判断用户是否已报过名
        $checkUser = DistributionUserApply::where(['channel' => $channel, 'userid' => $userId, 'status' => 1])->find();
        if ($checkUser) {
            success(['type' => 5, 'msg' => "用户已经报名"]);
        }
        //获取用户信息并加入申请表中
        $userInfo = User::alias('u')
            ->field('u.mobile,u.nickname,i.headimgurl,i.openid')
            ->leftJoin(UserInfo::getTable() . ' i', 'u.id = i.user')
            ->where(['u.id' => $userId])->find();
        if (!$userInfo) {
            error(50000, "未找到用户数据");
        }
        $userApply['channel'] = $channel;
        $userApply['userid'] = $userId;
        $userApply['openid'] = $userInfo['openid'];
        $userApply['formid'] = $fromId;
        $userApply['nickname'] = $userInfo['nickname'];
        $userApply['avatar'] = $userInfo['headimgurl'];
        $userApply['mobile'] = $userInfo['mobile'] ?? '';
        //判断是否需审核
        if ($isReview == 1) {
            //不审核
            $user = $userApply;
            $userApply['status'] = 2;
            //不审核则不需要写入申请表
            //DistributionUserApply::create($userApply);
            //查找下一级的升级条件
            $nextLevel = $this->getNextLevel($channel, 1)->toArray();
            $user['status'] = 1;
            $user['level'] = DistributionUpgradeCondition::field('min(level) as level')->where(['channel' => $channel])->find()['level'];
            if ($nextLevel) {
                $user['next_level_order'] = $nextLevel[0]['order_num'];
                $user['next_level_money'] = $nextLevel[0]['money'];
            }
            $res = DistributionUser::create($user);
            if ($res) {
                //发送模版消息
                $url = "https://tst-api-mp.feekr.com/sms/auditnotice";
                $data['channel'] = $channel;
                $data['id'] = $userId;
                $data['status'] = 2;
                curl_file_get_contents($url, $data);
                success(['type' => 1, 'msg' => "成功成为推广员"]);
            } else {
                success(['type' => 3, 'msg' => "写入用户失败"]);
            }
        } else {
            //审核
            $userApply['status'] = 1;
            //写入申请表
            $res = DistributionUserApply::create($userApply);
            if ($res) {
                success(['type' => 2, 'msg' => "报名成功，请等待审核"]);
            } else {
                success(['type' => 3, 'msg' => "写入用户失败"]);
            }
        }

    }

    public function getNextLevel($channel, $level)
    {
        return DistributionUpgradeCondition::field('id,level,order_num,money,all_money,all_order_num')
            ->where([['channel', 'eq', $channel], ['level', 'gt', $level]])
            ->limit(1)
            ->select();
    }

    //获取当前渠道升级规则
    public function upgrade($channel)
    {
        $result = DistributionUpgradeCondition::field('id,channel,level,level_name,all_order_num,all_money')->where(['channel' => $channel])->select();
        if ($result) {
            $data['upgrade_condition'] = $result;
        } else {
            error(["msg" => "获取升级规则失败"]);
        }
        //获取常见问题
        $result = DistributionQuestion::field('id,question,answer')->where(['channel' => $channel])->select();
        $data['question'] = $result;
        success($data);
    }

    //推广中心
    public function upgradeextension($channel, $userId)
    {
        //获取用户等级等信息
        $userInfo = DistributionUser::alias('u')
            ->field('u.nickname,u.avatar,u.money,u.notify_status,l.level,l.level_name')
            ->leftJoin(DistributionUpgradeCondition::getTable() . ' l', 'u.level = l.level')
            ->where(['u.userid' => $userId, 'l.channel' => $channel])->find();
        $result['user']['nickname'] = $userInfo['nickname'] ?? '';
        $result['user']['avatar'] = $userInfo['avatar'] ?? '';
        $result['user']['level_name'] = $userInfo['level_name'] ?? '';
        $result['user']['notify_status'] = $userInfo['notify_status'] ?? '';
        if ($userInfo['notify_status'] != 3) {
            DistributionUser::where(['userid' => $userId])->update(["notify_status" => 3]);//重置通知状态
        }
        //获取用户收益数据
        $toDayStart = date("Y-m-d 00:00:00", time());
        $toDayEnd = date("Y-m-d 23:59:59", time());
        //当日收益和总收益和可提现金额
        $result['profit']['withdrawal'] = $userInfo['money'] ?? '';
        $toDay = DistributionOrder::field('distribution_user_id,sum(commission) as commission')
            ->where([["distribution_user_id", 'eq', $userId],
                ["create_time", 'egt', $toDayStart],
                ["create_time", 'elt', $toDayEnd]
            ])
            ->group("distribution_user_id")
            ->find();
        $result['profit']['today'] = $toDay['commission'] ?? '';

        $count = DistributionOrder::field('distribution_user_id,sum(commission) as count')
            ->where([["distribution_user_id", 'eq', $userId]
            ])
            ->group("distribution_user_id")
            ->find();
        $result['profit']['count'] = $count['count'] ?? '';
        success($result);
    }

    //推广商品
    public function product($channel, $userId, $params)
    {
        $page = $params['page'] ?? 1;
        $query = DistributionProduct::alias('dp')
            ->field('p.id,p.name,p.shop_id as sub_shop_id,dp.type,p.cover as pic,p.price,dp.rate_type,dp.rate,dp.rate_all')
            ->leftJoin(ProductUnion::getTable() . ' p', 'dp.id = p.id AND dp.type = p.type')
            ->where(['p.channel' => $channel, 'dp.status' => 1, 'p.status' => 1]);
        $product = $query->order("dp.create_time DESC")
            ->limit(($page - 1) * 5, 5)->select();
        $count = $query->count();
        //获取用户等级信息
        $userInfo = DistributionUser::field('userid,level')->where(['userid' => $userId])->find();
        $userLevel = $userInfo['level'] ?? 1;
        foreach ($product as $key => $value) {
            //如果是房型产品则需要重新查找数据
            $product[$key]['sub_shop_id'] = encrypt($value['sub_shop_id'], 4);
            if ($value['type'] == 1) {
                $product[$key]['id'] = encrypt($value['id'], 6);
                $product[$key]['pic'] = getBucket('hotel_room_type', 'cover', $value['pic']);
            } else {
                $product[$key]['id'] = encrypt($value['id'], 1);
                $product[$key]['pic'] = getBucket('product', 'pic', $value['pic']);
            }
            if ($value['rate_type'] == 1) {//统一比例
                $product[$key]['rate'] = ($value['rate_all'] ?? 0);
                $product[$key]['predict'] = round($value['price'] * ($value['rate_all'] / 100), 2);
                unset($product[$key]['price'], $product[$key]['rate_type'], $product[$key]['rate_all']);
            } else {
                $rate = json_decode($value['rate'], true);
                switch ($userLevel) {
                    case 1:
                        $product[$key]['rate'] = ($rate['rate1'] ?? 0);
                        break;
                    case 2:
                        $product[$key]['rate'] = ($rate['rate2'] ?? 0);
                        if ($product[$key]['rate'] == 0) {
                            $product[$key]['rate'] = $rate['rate1'] ?? 0;
                        }
                        break;
                    case 3:
                        $product[$key]['rate'] = ($rate['rate3'] ?? 0);
                        if ($product[$key]['rate'] == 0) {
                            $product[$key]['rate'] = $rate['rate2'] ?? 0;
                        }
                        if ($product[$key]['rate'] == 0) {
                            $product[$key]['rate'] = $rate['rate1'] ?? 0;
                        }
                        break;
                }
                $product[$key]['predict'] = round($value['price'] * ($product[$key]['rate'] / 100), 2);
                unset($product[$key]['price'], $product[$key]['rate_type'], $product[$key]['rate_all']);
            }
        }
        success(['list' => $product, 'total_count' => $count]);
    }

    //推广商品
    public function poster($userId, $params)
    {
        if (!isset($params['product_id'])) {
            error(40000, "product_id null");
        }
        if (!isset($params['type'])) {
            error(40000, "type null");
        }
        if ($params['type'] == 1) {
            $productId = encrypt($params['product_id'], 6, false);
        } else {
            $productId = encrypt($params['product_id'], 1, false);
        }
        //获取用户等级等信息
        $userInfo = DistributionUser::field('nickname,avatar')
            ->where(['userid' => $userId])
            ->find();

        $result['product_id'] = $productId;
        $result['nickname'] = $userInfo['nickname'] ?? '';
        $result['avatar'] = $userInfo['avatar'] ?? '';

        //获取商品海报
        $productInfo = DistributionProduct::field('poster')->where(['id' => $productId])->find();
        if (!empty($productInfo['poster'])) {
            $posters = json_decode($productInfo['poster'], true);
            foreach ($posters as $v) {
                $result['poster'][] = getBucket('distribution_product', 'poster', $v);
            }

        } else {
            $result['poster'] = [];
        }
        success($result);
    }

    //获取二维码
    public function qrcode($channel, $params, $user)
    {
        //先从数据库里找 如果没有则调后台接口
        $app_version = Env::get("MP_APP_VERSION");
        $app_url = $params['app_url'];
        $api = DOMAIN_MP . "/link/front_qrcode?api_version=$app_version&channel=$channel&app_url=$app_url";
        if (isset($params['is_hyaline'])) {
            $api .= "&is_hyaline=1";
        }
        //判断是获取小程序二维码还是产品二维码
        if (isset($params['product_id']) && !empty($params['product_id'])) {
            $productId = $params['product_id'];
            $shopId = $params['sub_shop_id'];
            $type = $params['type'];
            $api .= "&product_id=$productId&sub_shop_id=$shopId&uid=$user&type=$type";
        }
        $res = curl_file_get_contents($api);
        print_r($res);
        die;
    }

    //获取招募页规则
    public function recruit($channel)
    {
        $config = DistributionRecruitPage::field("title, content")->where(['channel' => $channel])->find();
        success($config);
    }
}

