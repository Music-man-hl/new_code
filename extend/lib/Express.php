<?php
/**
 * 快递鸟接口对接
 * User: 总裁
 * Date: 2019/7/29
 * Time: 10:41
 */

namespace lib;

class Express
{

    const EBusinessID = '1557669'; //电商ID
    const AppKey = 'cd8be0e9-18c2-4a77-91cb-790f7409e7fd'; //电商加密私钥，快递鸟提供，注意保管，不要泄漏
    const ReqURL = 'http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';  // 查询订单物流轨迹

    /**
     * @param $ShipperCode string 快递公司编码
     * @param $LogisticCode string 快递单号
     * @param string $OrderCode 订单编号不填
     * @param string $CustomerName JD和SF必填
     * @return bool|mixed|string
     */
    static function getTrace($ShipperCode, $LogisticCode,  $CustomerName = '',$OrderCode = '')
    {
        if (empty($ShipperCode) || empty($LogisticCode)) return false;
        if ($ShipperCode == 'CNWL') return '';
        $post = [
            'OrderCode' => $OrderCode,
        ];
        if (in_array($ShipperCode, ['SF', 'JD'])) {
            if (empty($CustomerName)) error(50000, '特殊商家编码必填');
            $post['CustomerName'] = $CustomerName;
        }
        $post = array_merge($post, ['ShipperCode' => $ShipperCode, 'LogisticCode' => $LogisticCode]);
        $logisticResult = self::getOrderTracesByJson(json_encode($post));
        if (empty($logisticResult)) return '';
        $data = json_decode($logisticResult, true);
        return $data;
    }

    // CustomerName ShipperCode 为 JD，必填，对应京东的青 龙配送编码，也叫商家编码，
    // 格式：数字 ＋字母＋数字，9 位数字加一个字母，共 10 位，举例：001K123450；
    // ShipperCode 为 SF，且快递单号非快递鸟 渠道返回时，必填，对应收件人/寄件人手 机号后四位；
    // ShipperCode 为 SF，且快递单号为快递鸟 渠道返回时，不填； ShipperCode 为其他快递时，不填

    //$requestData= "{'OrderCode':'非必填',,'ShipperCode':'物流公司编码','LogisticCode':'快递单号'}";// CustomerName
    static private function getOrderTracesByJson($requestData)
    {

        $datas = array(
            'EBusinessID' => self::EBusinessID,
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData),
            'DataType' => '2',
        );
        $datas['DataSign'] = self::encrypt($requestData, self::AppKey);
        $result = self::sendPost(self::ReqURL, $datas);
        return $result;
    }

    /**
     *  post提交数据
     * @param string $url 请求Url
     * @param array $datas 提交的数据
     * @return url响应返回的html
     */
    static private function sendPost($url, $datas)
    {
        $temps = array();
        foreach ($datas as $key => $value) {
            $temps[] = sprintf('%s=%s', $key, $value);
        }
        $post_data = implode('&', $temps);
        $url_info = parse_url($url);
        if (empty($url_info['port'])) {
            $url_info['port'] = 80;
        }
        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader .= "Host:" . $url_info['host'] . "\r\n";
        $httpheader .= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader .= "Content-Length:" . strlen($post_data) . "\r\n";
        $httpheader .= "Connection:close\r\n\r\n";
        $httpheader .= $post_data;
        $fd = fsockopen($url_info['host'], $url_info['port']);
        fwrite($fd, $httpheader);
        $gets = "";
        $headerFlag = true;
        while (!feof($fd)) {
            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                break;
            }
        }
        while (!feof($fd)) {
            $gets .= fread($fd, 128);
        }
        fclose($fd);

        return $gets;
    }

    /**
     * 电商Sign签名生成
     * @param data 内容
     * @param appkey Appkey
     * @return DataSign签名
     */
    static private function encrypt($data, $appkey)
    {
        return urlencode(base64_encode(md5($data . $appkey)));
    }
}