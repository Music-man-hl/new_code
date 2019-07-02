<?php 
namespace lib;
/**
 * 日志类
 * X-Wolf
 * 2018-4-18
 */
class MyLog
{
    // 日志根目录
    private static $_Log_path = '../logs/third';

    // 日志文件
    private static  $_Log_file = 'default';

    // 日志自定义目录
    private static $_format = 'Y/m/d';

    // 日志标签
    private static $_tag = '-';

    // 日志文件后缀
    private static $_Log_ext = '.log';

    // 配置设置
    public static function set_config($config)
    {
        if(!$config || !is_array($config)) return false;

        if(isset($config['log_path'])) self::$_Log_path = $config['log_path'];

        if(isset($config['log_file'])) self::$_Log_file = $config['log_file'];

        if(isset($config['log_ext']))  self::$_Log_ext  = $config['log_ext'];
        
        return true;  
    }

    // 写入紧急日志
    public static function emergency($data)
    {
        return self::add('EMERGENCY', $data);
    }
    // 写入错误日志
    public static  function error($data)
    {
        return self::add('ERROR', $data);
    }

    // 写入警告日志
    public static  function warn($data)
    {
        return self::add('WARN', $data);
    }

    // 写入提示日志
    public static  function notice($data)
    {
        return self::add('NOTICE', $data);
    }

    // 写入信息日志
    public static  function info($data)
    {
        return self::add('INFO', $data);
    }

    // 写入调试日志
    public static  function debug($data)
    {
        return self::add('DEBUG', $data);
    }

    /**
     * 写入日志
     * @param  String  $type 日志类型
     * @param  String  $data 日志数据
     * @return Boolean
     */
    private static  function add($type, $data)
    {
    	
    	self::$_Log_file = strtolower($type).self::$_Log_ext; 
        // 获取日志文件
        $Log_file = self::get_Log_file();

        // 创建日志目录
        $is_create = self::create_Log_path(dirname($Log_file));

        // 创建日期时间对象
        $dt = new \DateTime;

        // 日志内容
        $Log_data = sprintf('[%s] %-5s %s %s', $dt->format('Y-m-d H:i:s'), $type, self::$_tag, $data);
        // 写入日志文件
        if($is_create){
            return file_put_contents($Log_file, $Log_data.PHP_EOL, FILE_APPEND);
        }

        return false;
    }

    // 创建日志目录
    private static  function create_Log_path($Log_path)
    {
        if(!is_dir($Log_path)){
            return mkdir($Log_path, 0777, true);
        }
        return true;
    }

    // 获取日志文件名称
    private static  function get_Log_file()
    {
        // 创建日期时间对象
        $dt = new \DateTime;
        // 计算日志目录格式
        return sprintf("%s/%s/%s", self::$_Log_path, $dt->format(self::$_format), self::$_Log_file);
    }

}