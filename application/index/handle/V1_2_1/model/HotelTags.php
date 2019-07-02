<?php
namespace app\index\handle\V1_2_1\model;

use think\Model;

class HotelTags extends Model
{
    protected $pk = 'id';
    protected $table = 'hotel_tags';

    const HOTEL_FACILITIES = 1;
    const HOTEL_SERVICES = 2;
    const ROOM_FACILITIES = 3;

    /**
     * 根据shop_id来获取tags
     *
     * @return array
     */
    public static function getAllTagsByShop($shop_id)
    {
        $result = self::alias('ht')
            ->field('ht.type,ht.tag')
            ->join('hotel h', 'h.id=ht.hotel_id')
            ->where('h.shop_id', $shop_id)
            ->select();
        $data = [];
        $labels = [1=>'hotel_facilities','hotel_services','room_facilities'];
        foreach ($result as $v) {
            $data[$labels[$v['type']]][] = $v['tag'];
        }
        return $data;
    }
    
    /**
     * 根据 hotel_id 获取tags
     *
     * @param [type] $hotel_id
     * @return array
     */
    public static function getAllTagsByHotel($hotel_id)
    {
        $return = [];

        $return ['hotel_facilities'] = self::getTagsByHotel($hotel_id, self::HOTEL_FACILITIES);
        $return ['hotel_services']   = self::getTagsByHotel($hotel_id, self::HOTEL_SERVICES);
        $return ['room_facilities']  = self::getTagsByHotel($hotel_id, self::ROOM_FACILITIES);
        return $return;
    }

    /**
     * 根据 hotel_id 和 type 获取tags
     *
     * @param [type] $hotel_id
     * @return array
     */
    public static function getTagsByHotel($hotel_id, $type)
    {
        $result = self::where('hotel_id', $hotel_id)
            ->where('type', $type)
            // ->value('tag');
            ->field('tag')
            ->select()
            ->toArray();
        return $result ? array_column($result, 'tag') : [];
    }

    /**
     * 设置tags
     *
     * @param [type] $hotel_id
     * @param [type] $type
     * @param [type] $tags
     * @return void
     */
    public static function setTags($hotel_id, $type, $tags)
    {
        // 先清空所有相关的tag
        self::where('hotel_id', $hotel_id)
            ->where('type', $type)
            ->delete();
        if (empty($tags)) {
            return true;
        }
        $data = [];
        foreach ($tags as $tag) {
            $data[] = [
                'hotel_id' => $hotel_id,
                'type'     => $type,
                'tag'      => $tag,
            ];
        }
        return self::insertAll($data);
    }
}
