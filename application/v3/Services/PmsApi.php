<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-18
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use lib\MyLog;
use think\facade\Log;

class PmsApi extends BaseService
{
    private $token;

    private $secret;

    private $timestamp;

    private $client;

    private $timeOut = 2.0;

    public function __construct()
    {
        $this->timestamp = time();
        $this->secret = config('web.pms.secret');
        $this->token = $this->generateToken();
        $base_uri = config('web.pms.url');
        $this->client = new Client([
            'base_uri' => $base_uri, 'timeout' => $this->timeOut,
            'headers' => [
                'token' => $this->token,
                'timestamp' => $this->timestamp,
            ]
        ]);
    }

    private function generateToken()
    {
        return $token = md5($this->secret . $this->timestamp);
    }

    /**
     * 订单退款
     * @param $data
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function cancelOrder($data)
    {
        $method = 'POST';
        $uri = '/v1/cancelOrder';
        $action = 'cancelOrder';
        $params = [
            'channelId' => $data['channel'],
            'pmsId' => $data['pms_id'],
            'action' => $action,
            'data' => ['orderCode' => $data['orderCode']],
        ];

        try {
            $response = $this->client->request($method, $uri, ['json' => $params]);
            $result = json_decode((string)$response->getBody(), true);
            MyLog::info("[pms退款返回] ". (string)$response->getBody());
            if ($result['status']['code'] !== 200) {
                return false;
            }
            return true;
        } catch (GuzzleException $e) {
            Log::error('[pms订单退款失败] ' . $e->getMessage());
            return false;
        }
    }
}
