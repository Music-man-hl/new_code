<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-04-04
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class ProductPicture extends BaseModel
{

    public function getPicAttr($value, $data)
    {
        return picture($data['bucket'], $data['pic']);
    }

}