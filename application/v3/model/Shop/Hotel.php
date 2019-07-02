<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-14
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\v3\model\Shop;


use app\v3\model\BaseModel;

class Hotel extends BaseModel
{

    public function tags()
    {
        return $this->hasMany(HotelTags::class, 'hotel_id', 'id');
    }

    /**
     * 根据shop_id来获取tags
     *
     * @return array
     */
    public static function getAllTagsByShop($shop_id)
    {
        $result = self::field('id')
            ->where('shop_id', $shop_id)
            ->with(['tags' => function ($query) {
                $query->field('hotel_id,type,tag');
            }])->find();
        $data = [];
        $labels = [1 => 'hotel_facilities', 'hotel_services', 'room_facilities'];
        foreach ($result->tags as $v) {
            $data[$labels[$v['type']]][] = $v['tag'];
        }
        return $data;
    }

}