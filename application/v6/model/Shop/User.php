<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-16
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class User extends BaseModel
{
    public function getCoverAttr($value, $data)
    {
        return picture($data['bucket'], $data['pic']);
    }

}