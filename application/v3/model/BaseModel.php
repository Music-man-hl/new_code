<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-07
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model;


use app\v3\model\Main\Shop;
use think\Model;

class BaseModel extends Model
{

    protected function initialize()
    {
        parent::initialize();

        self::setConf();
    }

    public function setConf()
    {
        if (!$this->connection) {
            $dbConfig = request()->dbConfig;
            $this->connection = $dbConfig;
        }
    }



    public static function validSubId($channel)
    {
        $data = Shop::field('id')->where('channel', $channel)->select();
        if (count($data) > 1 || count($data) == '0') return false;
        return $data[0]['id'];
    }

}
