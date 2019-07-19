<?php


namespace app\v6\model\Shop;


use app\v6\model\BaseModel;

class ProductRetailItem extends BaseModel
{

    public function product()
    {
        return $this->belongsTo(Product::class, 'pid', 'id');
    }

    public function ext()
    {
        return $this->belongsTo(ProductRetailExt::class, 'pid', 'pid');
    }

    public function getNameAttr($value, $data)
    {
        $level = [$data['level1'], $data['level2']];
        $standard = ProductRetailStandard::whereIn('id', $level)->select();
        return $name = $standard[0]->value . ' ' . $standard[1]->value;
    }

}