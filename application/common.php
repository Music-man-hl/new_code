<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +---------------------------------------------------------------------
// 应用公共文件
//打印代码
use think\facade\Cache;

if(!function_exists('p')){
    function p($array,$exit=false){
        if(!headers_sent()) header("Content-type: text/html; charset=utf-8");
        echo '<pre>';
        print_r($array);
        echo '</pre>';
        $filename = '../runtime/p_'.date('Ymd').'.log';
        file_put_contents($filename,date('Y-m-d H:i:s').' '.json_encode($array)."\n\n",FILE_APPEND);//日志
        if($exit) exit();
    }
}

if (!function_exists('dd')) {
    /**
     * 断点打印;
     * @param array $vars
     */
    function dd(...$vars)
    {
        foreach ($vars as $var) {
            dump($var);
        }
        exit;
    }
}

//成功返回json
if(!function_exists('success')){
    function success($array=[],$exit=true){

        $json = ['status'=>[
            'code'=>200,
            'message'=>'ok',
        ],'result'=>$array,
        ];

        if($exit) {
            header('Content-Type: application/json; charset=utf-8');
            die( json_encode($json) );
        } else {
            return json($json);
        }

    }
}
//失败返回json 错误码查看 extra/error.php
if(!function_exists('error')){
    function error($code,$message='',$exit=true,$only=false){

        if(!$only){
            $config = config('error.');
            if(isset($config[$code])) {
                $mess = $config[$code];
            }else{
                $mess = 'error';
            }
            $message = empty($message) ? $mess : ( $mess.'：'.$message);
        }

        config('app.app_debug') && $message .= ' ' . \lib\Error::get();//获取错误类型

        $json = ['status'=>[
            'code'=>(int)$code,
            'message'=>$message,
        ],'result'=>(object)[],
        ];

        if($exit) {
            header('Content-Type: application/json; charset=utf-8');
            die( json_encode($json) );
        } else {
            return json($json);
        }

    }
}

//like过滤
if(!function_exists('escapeLike')){
    function escapeLike($str)
    {
        return strtr($str, array('\\' => '\\\\\\\\', '_' => '\_', '%' => '\%', "'" => "\\'"));
    }
}


//生成redis的可以 用于token和refresh的key
if(!function_exists('getRand')){
    function getRand($len = 32)
    {
        if ($len > 168) $len = 168;

        if( strtoupper(substr(PHP_OS,0,3))==='WIN' ){

            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $charsLen = strlen($chars) - 1;
            $password = '';
            for ($i = 0; $i < $len; $i++) {
                $password .= $chars[mt_rand(0, $charsLen)];
            }
            return $password;

        }else{

            $fp = @fopen('/dev/urandom', 'rb');
            $result = '';
            if ($fp !== FALSE) {
                $result .= @fread($fp, $len);
                @fclose($fp);
            } else {
                trigger_error('Can not open /dev/urandom.');
            }
            // convert from binary to string
            $result = base64_encode($result);
            // remove none url chars
            $result = strtr($result, '+/', '-_');
            return substr($result, 0, $len);

        }

    }
}

//验证手机是否合法
if(!function_exists('isMobile')){
    function isMobile($mobile){

        return preg_match('/^1[3-9]\d{9}$/', $mobile) ? true : false ;

    }
}

//验证是否在微信中
if(!function_exists('isWeixin')){
    function isWeixin(){

        return strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') === false ? false : true;

    }
}
//验证手机验证码是否合法
if(!function_exists('isValidCode')){
    function isValidCode($code){
        return preg_match('/^\d{6}$/', $code) ? true : false ;
    }
}


if(!function_exists('makeSqlOption')){
    /**
     * 生成and 形式的sql ['key'] => ['value','operation','alias'] 只支持简单的sql 复杂的自己写
     * @param $array
     * @return array
     */
    function makeSqlOption($array){

        $sql = '';
        $value = [];

        if(!empty($array)){
            foreach ($array as $k => $v) {
                $alias = isset($v[2]) ? ($v[2].'.') : '';
                $op = $v[1]; //操作符

                switch ($op){
                    case 'like':
                        $content = escapeLike($v['0']);
                        $sql .= " AND {$alias}`{$k}` $op '%{$content}%' "  ;
                        break;
                    case 'llike':
                        $content = escapeLike($v['0']);
                        $sql .= " AND {$alias}`{$k}` like '%{$content}' "  ;
                        break;
                    case 'rlike':
                        $content = escapeLike($v['0']);
                        $sql .= " AND {$alias}`{$k}` like ':{$content}%' "  ;
                        break;

                    case 'in':
                    case 'not in':
                        if(preg_match('/^([0-9]+,?)*(\d+)$/',$v['0']))
                        {
                            $sql .= " AND {$alias}`{$k}` {$op} ({$v['0']}) "  ;
                        }
                        break;

                    case '>':
                    case '<':
                    case '>=':
                    case '<=':
                    case '!=':
                    case '=':
                        $sql .= " AND {$alias}`{$k}` {$op} :{$k} "  ;
                        $value[$k] = $v['0'];
                        break;
                }


            }
        }

        return ['sql'=>$sql,'value'=>$value];

    }

}

if (!function_exists('jsJump')){

    //js跳转
    function jsJump($url){
        echo '<script>';
        echo 'location.href="'.$url.'";';
        echo '</script>';
        exit();
    }

}


//插入组合
if (!function_exists('insert_array')) {

    function insert_array($data, $multiple = false, $mukey = '')
    {
        if ($multiple || isset($data[0])) {
            $value = array();
            $sql = array();
            foreach ($data as $i => $v) {
                $rs = insert_array($v, false, $i + 1);
                $value = array_merge($value, $rs['value']);
                $sql[] = $rs['sql'];
            }

            $key = array_keys($data[0]);
            $key = '(`' . implode('`,`', $key) . '`)';

            return array('column' => $key, 'sql' => implode(',', $sql), 'value' => $value);

        } else {
            $value = array();
            foreach ($data as $k => $v) {
                if ($v === null) {
                    $data[$k] = 'NULL';
                } else {
                    $value["{$k}{$mukey}"] = $v;
                    $data[$k] = ":{$k}{$mukey}";
                }
            }

            $key = '';
            if ($mukey === '') {
                $key = array_keys($data);
                $key = '(`' . implode('`,`', $key) . '`)';
            }

            return array('column' => $key, 'sql' => '(' . implode(',', $data) . ')', 'value' => $value);
        }
    }

}

//更新
if (!function_exists('update_array')) {

    function update_array($data)
    {
        $value = array();
        foreach ($data as $k => $v) {
            if ($v === null) {
                $data[$k] = "`{$k}` = NULL";
            } else {
                $value[$k] = $v;
                $data[$k] = "`{$k}` = :{$k}";
            }
        }

        return array('sql' => implode(',', $data), 'value' => $value);
    }

}
//curl
if (!function_exists('curl_file_get_contents')){
    function curl_file_get_contents($url, $post=null, $header=array(), $timeout=5)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($header)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if (!is_null($post))
        {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
        }
        if(APP_EVN == 3){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        if(curl_errno($ch)){
            return curl_error($ch);
        }
        curl_close($ch);
        return $content;
    }
}

//获取ip
if(!function_exists('getIp')){
    function getIp(){
        $ip=false;
        if(!empty($_SERVER["HTTP_CLIENT_IP"])){
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode (", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
            if ($ip) { array_unshift($ips, $ip); $ip = FALSE; }
            for ($i = 0; $i < count($ips); $i++) {
                if (!preg_match ("/^(10│172.16│192.168)./i", $ips[$i])) {
                    $ip = $ips[$i];
                    break;
                }
            }
        }
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }
}

//邮箱验证
if(!function_exists('isEmail')){
    function isEmail($email){
        if(empty($email)) return false;

        if(filter_var($email, FILTER_VALIDATE_EMAIL))
            return true;
        else
            return false;
    }
}

/**
 * 二维数组排序
 * @param $array
 * @param $keyid
 * @param $order
 * @param $type
 * @example @sort_array($list, 'keyid', 'desc', 'string');
 */
if(!function_exists('sort_array')){
    function sort_array(&$array, $keyid, $order = 'asc', $type = 'number') {
        if (is_array($array)) {
            foreach($array as $val) {
                $order_arr[] = $val[$keyid];
            }
            $order = ($order == 'asc') ? SORT_ASC: SORT_DESC;
            $type = ($type == 'number') ? SORT_NUMERIC: SORT_STRING;
            array_multisort($order_arr, $order, $type, $array);
        }
    }
}

/**
 * 过滤四个字节的emoji
 * @param string $text 要过滤的文本
 * @return string
 */
if(!function_exists('filterEmoji')) {
    function filterEmoji($text)
    {
        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $text = preg_replace($regexEmoticons, '', $text);

        // Match Miscellaneous Symbols and Pictographs
        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $text = preg_replace($regexSymbols, '', $text);

        // Match Transport And Map Symbols
        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $text = preg_replace($regexTransport, '', $text);

        // Match Miscellaneous Symbols
        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        $text = preg_replace($regexMisc, '', $text);

        // Match Dingbats
        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $text = preg_replace($regexDingbats, '', $text);

        $text = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $text);

        return $text;
    }
}


if( !function_exists('encrypt') ){

    /**
     * 加密/解密函数
     * @param $string 要解密/解密的字符串
     * @param int $type 类型类1.产品 2.券 3.店铺 4.门店 5.周边 6.房型 9.优惠券id
     * @param bool $operation 加密true/解密false
     * @return bool|string 返回加密/解密结果
     */
    function encrypt($string,$type=1,$operation=true){

        if (config('app.encrypt_close')) {
            return $string;
        }

        $key_list = config('web.encrypt_key_list');
        if( !isset($key_list[$type]) ) {
            return false;
        }else{
            $key=md5($key_list[$type]);
        }
        $data = trim($string);
        if(empty($data)) return '';

        $x  = 0;
        $data = $operation ? $data : base64_decode(str_replace('_','+',$data)); //加密/解密数据
        $len = strlen($data);
        $l  = strlen($key);

        $char='';
        $str='';

        if($operation){ //加密

            for ($i = 0; $i < $len; $i++)
            {
                if ($x == $l)
                {
                    $x = 0;
                }
                $char .= $key{$x};
                $x++;
            }
            for ($i = 0; $i < $len; $i++)
            {
                $str .= chr(ord($data{$i}) + (ord($char{$i})) % 256);
            }

        } else { //解密

            for ($i = 0; $i < $len; $i++)
            {
                if ($x == $l)
                {
                    $x = 0;
                }
                $char .= substr($key, $x, 1);
                $x++;
            }
            for ($i = 0; $i < $len; $i++)
            {
                if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1)))
                {
                    $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
                }
                else
                {
                    $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
                }
            }

        }

        return $operation ? str_replace(['+','='],['_',''],base64_encode($str)) : $str; //返回结果

    }

}

if( !function_exists('redis_prefix') ){
    /**
     * @param string $verify 需要验证的字符串
     * @return string
     */
    function redis_prefix($verify = ''){
        $prefix = REDIS_SYS.REDIS_FB.':';
        if('' === $verify) {
            return $prefix;
        } else {
            return substr($verify,0,3) == $prefix ;
        }
    }
}

if(!function_exists('jsSignVerify')) {
    /**
     * 前台签名验证
     * @param $query
     * @return bool
     */
    function jsSignVerify($query)
    {

        if( empty($query) || !isset($query['param_sign']) || empty($query['param_sign']) || !is_array($query)) return false;
        $secert = config('js_sign_key');

        $sign = $query['param_sign'];
        unset($query['param_sign']);

        $list = [];
        arrayToString($query,$list);
        sort($list);

        $url = implode('&',$list);
        $makeSign = md5( $url.'&key='.$secert );
        if( $sign == $makeSign ) return true; else return false;

    }
}


if(!function_exists('jsSignMake')) {
    /**
     * 生成前台签名
     * @param $query
     * @return string
     */
    function jsSignMake($query)
    {

        if( empty($query) ) return '';
        $secert = config('js_sign_key');

        if(isset($query['param_sign']))
            unset($query['param_sign']);

        $list = [];
        arrayToString($query,$list);
        sort($list);

        $url = implode('&',$list);
        $makeSign = md5( $url.'&key='.$secert );

        $query['param_sign'] = $makeSign;

        return $query;

    }
}

if(!function_exists('arrayToString')) {
    /**
     * 把数组转为key=value字符串
     * @param $arr
     * @param $data
     */
    function arrayToString($arr,&$data)
    {
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                arrayToString($v,$data);
            } else {
                $data[] = $k.'='.$v;
            }
        }
    }
}

if(!function_exists('ipFilter')){
    /**
     * 请求防刷
     */
    function ipFilter(){

        $requests_limit = config('requests_limit');//配置参数

        $ip = \Request::ip();
        $key = redis_prefix(). 'ip:'.md5($ip);

        if(\lib\Redis::exists($key)){
            $count = \lib\Redis::incr($key);
        }else{
            $count = \lib\Redis::set($key,1,$requests_limit['time']);
        }

        if($count > $requests_limit['count']) {
            error(40300);
        }
    }
}

if(!function_exists('getMicroTime')){
    /**
     * 获取微妙字符串 用于加密
     * @return string
     */
    function getMicroTime() {
        list($t1, $t2) = explode(' ', microtime());
        return $t2 . $t1;
    }
}

if(!function_exists('send_mail')){
    /**
     * 系统邮件发送函数
     * @param string $tomail 接收邮件者邮箱
     * @param string $name 接收邮件者名称
     * @param string $subject 邮件主题
     * @param string $body 邮件内容
     * @param string $attachment 附件列表
     * @return boolean
     */
    function send_mail($tomail, $name, $subject = '', $body = '', $attachment = null) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer();  //实例化PHPMailer对象
        $mail->CharSet = 'UTF-8';           //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
        $mail->IsSMTP();                    // 设定使用SMTP服务
        $mail->SMTPDebug = 0;               // SMTP调试功能 0=关闭 1 = 错误和消息 2 = 消息
        $mail->SMTPAuth = true;             // 启用 SMTP 验证功能
        $mail->SMTPSecure = 'ssl';          // 使用安全协议
        $mail->Host = "smtp.exmail.qq.com"; // SMTP 服务器
        $mail->Port = 465;                  // SMTP服务器的端口号
        $mail->Username = "dev@feekr.com";    // SMTP服务器用户名
        $mail->Password = "Fkr.123";     // SMTP服务器密码
        $mail->SetFrom('dev@feekr.com', 'Feekr旅行');
        $replyEmail = '';                   //留空则为发件人EMAIL
        $replyName = '';                    //回复名称（留空则为发件人名称）
        $mail->AddReplyTo($replyEmail, $replyName);
        $mail->Subject = $subject;
        $mail->MsgHTML($body);
        $mail->AddAddress($tomail, $name);
        if (is_array($attachment)) { // 添加附件
            foreach ($attachment as $file) {
                is_file($file) && $mail->AddAttachment($file);
            }
        }
        return $mail->Send() ? true : $mail->ErrorInfo;
    }
}

if(!function_exists('makeThirdRedisKey')){
    /**
     * 通过名称生成真实Redis的key
     */
    function makeThirdRedisKey($name){

        $prefix = config('third_redis_prefix');

        $newName = $prefix.$name;

        return $newName.'_'.md5($newName);
    }
}

if(!function_exists('checkValidate')){
    /**
     * @param array $data 需要验证的是数据
     * @param string $class 验证类路径，用来new的
     * @return bool
     */
    function checkValidate(array $data, $class = ''){
        if(empty($data) || empty($class)) return false;
        $validate = new $class();
        if (!$validate->check($data)) {
            $getErrorString = $validate->getError();
            $error_array = explode('_',$getErrorString,2);
            error($error_array[0],isset($error_array[1]) ? $error_array[1] : '');
        }
    }
}


if(!function_exists('filterData')){
    /**
     * 参数验证,过滤并获取有用数据
     * @param  array $data      原数据
     * @param  array $standard  保留的参数
     */
    function filterData($data,$standard){
        if(empty($data) || !is_array($data) || !is_array($standard)) return false;

        $standardArr = array_fill_keys($standard, '');

        $data = array_intersect_key($data,$standardArr);

        return array_merge($standardArr,$data);
    }
}

if(!function_exists('picture')){
    /** 返回图片
     * @param $bucket
     * @param $cover
     * @return string
     */
    function picture($bucket,$cover){
        if(empty($bucket) || empty($cover)) return '';
        return 'https://'.$bucket.'.feekr.com'.$cover;
    }
}

if(!function_exists('formatTime')){
    /** 格式化时间
     * @param int $dateTime
     * @param bool $date
     * @return false|string
     */
    function formatTime($dateTime=0,$date = false){

        if(empty($dateTime)) $dateTime = NOW;

        if(!is_numeric($dateTime)){
            $time = strtotime($dateTime);
        }else{
            $time = $dateTime;
        }

        return $date ? date('Y-m-d',$time) : date('Y-m-d H:i:s',$time);
    }
}

if(!function_exists('startLimit')){
    /**
     * 根据参数返回start & limit
     * @param $param
     * @param int $defaultCount
     * @return array
     */
    function startLimit($param,$defaultCount = 20){

        $defaultPage = 1; //默认第一页
        $page    = isset($param['page']) ? abs(intval($param['page'])) : $defaultPage;
        $count   = isset($param['count']) ? abs(intval($param['count'])) : $defaultCount;
        if(empty($page)) $page = $defaultPage ;
        if(empty($count)) $count = $defaultCount;

        $start = ($page - 1) * $count;

        return ['start'=>$start,'limit'=>$count];
    }
}

if(!function_exists('makeWhere')){

    /**
     * 返回生成的where查询
     * @param array $where
     * @return array
     */
    function makeWhere( $where = []){
        if(empty($where) || !is_array($where))  return [];
        $data  = [];
        foreach ($where as $k => $v) {
            $data[] = [$k,'=',$v];
        }
        return $data;
    }
}

if(!function_exists('exceptionMessage')){
    /**
     * 异常信息组合 传递异常对象
     * @param $e
     * @return string
     */
    function exceptionMessage($e){
        if(!$e) {
            return '$e不存在';
        }else{
            if (config('app.app_debug')) {
                return  $e->getMessage() . ' 在 ' . $e->getFile() . ' ' . $e->getLine() .'行 trace <br/>'
                    .json_encode(debug_backtrace(false, 5));
            }else{
                return $e->getMessage() ;
            }
        }
    }
}

if(!function_exists('dayTimestamp')){
    /**
     * 返回当前零点时间戳
     * @param $time
     * @return false|int
     */
    function dayTimestamp($time){

        if( !is_numeric($time) ) {
            $time = strtotime($time);
        }

        return strtotime(date('Y-m-d',$time));

    }
}

if(!function_exists('checkTime')){
    /**
     * 时间格式验证
     * @param  string $time 时间 例:6:26
     * @return boolean
     */
    function checkTime($time)
    {
        return preg_match("/([0-9]|1[0-9]|2[0-3])\:(0[0-9]|[1-5][0-9])/", $time);
    }
}

if (!function_exists('makeOrder')) {
    /**
     * 返回订单号 18位
     * @param $channelId
     * @return string
     */
    function makeOrder($channelId)
    {
        $minute = date('i');
        $key = "order.$channelId.$minute";
        Cache::store('redis')->remember($key, 0, 80);
        $number = Cache::inc($key,rand(1,5));
        return date('ymdHi') . $channelId . str_pad($number, 4, '0', STR_PAD_LEFT); //18
    }
}

if (!function_exists('makeWithdrawOrder')) {
    /**
     * 返回提现订单号 18位
     * @param $channelId
     * @return string
     */
    function makeWithdrawOrder($channelId)
    {
        $minute = date('i');
        $key = "withdraw_order:$channelId:$minute";
        Cache::store('redis')->remember($key, 0, 80);
        $number = Cache::inc($key,rand(1,5));
        return date('ymdHi') . $channelId . str_pad($number, 4, '0', STR_PAD_LEFT); //18
    }
}

if(!function_exists('createRefundNum')){
    /**
     * 生成退款号(22)
     */
    function createRefundNum()
    {
        return date('YmdHis').mt_rand(10000000,99999999);

    }
}

if(!function_exists('add')){
    /**
     * 浮点数求和（如果是减 就把参数前加 - 号）
     * @param array ...$params
     * @return mixed 保留两位小数
     */
    function add(...$params) {
        return array_reduce($params,function($base,$n){
            $base = bcadd($base,+$n,2);
            return $base;
        });
    }
}