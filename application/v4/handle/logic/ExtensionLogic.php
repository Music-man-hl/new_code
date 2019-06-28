<?php


namespace app\v4\handle\logic;


use app\v4\model\Shop\DistributionOrder;
use app\v4\model\Shop\DistributionProduct;
use app\v4\model\Shop\DistributionPromotionConfig;
use app\v4\model\Shop\DistributionQuestion;
use app\v4\model\Shop\DistributionRecruitPage;
use app\v4\model\Shop\DistributionUpgradeCondition;
use app\v4\model\Shop\DistributionUser;
use app\v4\model\Shop\DistributionUserApply;
use app\v4\model\Shop\Order;
use app\v4\model\Shop\Product;
use app\v4\model\Shop\User;
use app\v4\model\Shop\UserInfo;
use app\v4\Services\BaseService;
use app\v4\Services\EasyWeChat;
use app\v4\Services\RabbitMQ;
use lib\MyLog;
use think\facade\Env;
use think\facade\Request;

class ExtensionLogic extends BaseService
{

    public function commission()
    {
        $request = request();
        $limit = startLimit($request->param());
        $profitHistory = DistributionOrder::hasWhere('orderInfo', function ($query) {
            $query->where('status', '<>', 9);
        })->where('distribution_user_id', $request->user)
            ->with(['orderInfo' => function ($query) {
                $query->field('product_name,order, date, status');
            }, 'user' => function ($query) {
                $query->field('id, nickname, pic, bucket');
            }])
            ->limit($limit['start'], $limit['limit'])
            ->select();

        $profit = DistributionOrder::hasWhere('orderInfo', function ($query) {
            $query->where('status', '<>', 9);
        })->where('distribution_user_id', $request->user)
            ->sum('commission');

        $withdrawalSum = DistributionOrder::hasWhere('orderInfo', function ($query) {
            $query->where('status', 8);
        })->where('distribution_user_id', $request->user)
            ->sum('commission');
        $withdrawal = DistributionUser::where('id', $request->user)->value('withdrawal');
        $canWithdrawal = $withdrawalSum - $withdrawal;

        return success(['can_withdrawal' => $canWithdrawal, 'profit' => $profit, 'profit_history' => $profitHistory]);
    }

    public function withdraw()
    {
        Request::only(['payee', 'amount']);
        $payee = Request::param('payee');
        $amount = Request::param('amount');

        if (!$payee || !is_string($payee)) {
            return error(40022);
        }
        if (!$amount || !is_numeric($amount)) {
            return error(40022);
        }

        $userId = request()->user;
        $user = DistributionUser::where('id', $userId)->find();
        if ($amount > $user['money']) {
            return error('40044', '超出可提现金额');
        }

        $openId = UserInfo::where('user', $user)->value('openid');
        $result = EasyWeChat::service()->transferToBalance($payee, $amount, '提现', $openId);

        if (!$result) {
            //todo
        }

        $user->withdrawal = $user->withdrawal + $amount;
        if (!$user->save()) {
            $data = json_encode(['userId' => $userId, 'amount' => $amount]);
            MyLog::error('提现保存失败:' . $data);
            return error('50000', '提现保存失败');
        }
        return success();
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
                    error(41001, "必须购买过商品才能申请成为推广员！");
                }
        }
    }

    //申请成为推广员
    public function promotion($userId, $fromId, $channel, $isReview)
    {
        //判断用户是否已经是推广员

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
            //写入申请表
            DistributionUserApply::create($userApply);
            //查找下一级的升级条件
            $nextLevel = $this->getNextLevel($channel, 1);

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
                error(['type' => 3, 'msg' => "写入用户失败"]);
            }
        } else {
            //审核
            $userApply['status'] = 1;
            //写入申请表
            $res = DistributionUserApply::create($userApply);
            if ($res) {
                success(['type' => 2, 'msg' => "报名成功，请等待审核"]);
            } else {
                error(['type' => 3, 'msg' => "写入用户失败"]);
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
        $product = DistributionProduct::alias('dp')
            ->field('p.id,p.name,p.type,p.pic,p.price,dp.rate_type,dp.rate,dp.rate_all')
            ->leftJoin(Product::getTable() . ' p', 'dp.id = p.id')
            ->where(['dp.channel' => $channel, 'dp.status' => 1])
            ->order("dp.create_time DESC")
            ->limit(($page - 1) * 5, 5)->select();
        $count = DistributionProduct::alias('dp')
            ->field('p.id,p.name,p.type,p.pic,p.price,dp.rate_type,dp.rate,dp.rate_all')
            ->leftJoin(Product::getTable() . ' p', 'dp.id = p.id')
            ->where(['dp.channel' => $channel, 'dp.status' => 1])
            ->count();
        //获取用户等级信息
        $userInfo = DistributionUser::field('userid,level')->where(['userid' => $userId])->find();
        $userLevel = $userInfo['level'] ?? 1;
        foreach ($product as $key => $value) {
            $product[$key]['pic'] = getBucket('product', 'pic', $value['pic']);
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
                        break;
                    case 3:
                        $product[$key]['rate'] = ($rate['rate3'] ?? 0);
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
            error(40000, "product_id");
        }
        $productId = $params['product_id'];
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
            error(50000, '该商品未设置海报!');
        }
        success($result);
    }

    //获取二维码
    public function qrcode($channel, $params)
    {
        //先从数据库里找 如果没有则调后台接口
        $app_version = Env::get("MP_APP_VERSION");
        $app_url = $params['app_url'];
        $api = DOMAIN_MP . "/link/front_qrcode?api_version=$app_version&channel=$channel&app_url=$app_url";
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

