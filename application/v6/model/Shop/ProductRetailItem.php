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
        $name = '';
        $level = [$data['level1'], $data['level2']];
        $standard = ProductRetailStandard::whereIn('id', $level)->select();
        foreach ($standard as $item) {
            $name .= $item->value . ' ';
        }
        return $name;
    }

}