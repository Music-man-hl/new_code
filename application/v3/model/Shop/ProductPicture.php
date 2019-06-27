<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-04
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;

class ProductPicture extends BaseModel
{

    public function getPicAttr($value, $data)
    {
        return picture($data['bucket'], $data['pic']);
    }

}