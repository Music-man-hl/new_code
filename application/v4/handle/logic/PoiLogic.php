<?php
/**
 * 资讯文章
 * User: 总裁
 * Date: 2019/6/21
 * Time: 17:17
 */

namespace app\v4\handle\logic;

use app\v4\model\Main\Shop;
use app\v4\model\Shop\PoiArticle;
use app\v4\model\Shop\PoiArticleTag;
use app\v4\model\Shop\PoiCategory;
use app\v4\model\Shop\Tags;
use app\v4\Services\BaseService;
use lib\Status;
use think\Db;

class PoiLogic extends BaseService
{

    const POI_STATUS_OK = 1;//POI状态ok

//    poi列表
    public function lists($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $limit = startLimit($all_param);

        $id = isset($all_param['id']) ? intval($all_param['id']) : 0;
        if (empty($id)) error(40000, 'id必传');

        $shop = Shop::where('id', $shop_id)->field('lng,lat')->find();
        $lat = floatval($shop->lat);
        $lng = floatval($shop->lng);

        if (empty($lat) && empty($lng)) error(50000, '店铺未设置经纬度');

        $db = Db::connect(PoiArticle::getConfig());
        $table = PoiArticle::getTable();

        $total_count = PoiArticle::where('status', self::POI_STATUS_OK)->where('category', $id)->count();
        $sql = "SELECT `id`,`name`,`intro`,`cover`,`lat`,`lng`,ROUND(6378.138*2*ASIN(SQRT(POW(SIN(({$lat}*PI()/180-lat*PI()/180)/2),2)+
COS({$lat}*PI()/180)*COS(lat*PI()/180)*POW(SIN(({$lng}*PI()/180-lng*PI()/180)/2),2)))*1000) AS distance 
FROM `{$table}` WHERE `status`=:status AND `category`=:category ORDER BY distance Asc LIMIT :start,:limit";

        $param = [
            'status' => self::POI_STATUS_OK,
            'category' => $id,
            'start' => $limit['start'],
            'limit' => $limit['limit'],
        ];

        $data = $db->query($sql, $param);

        if (!empty($data)) {
            $ids = array_column($data, 'id');

            $tagsArr = PoiArticleTag::alias('p')->leftJoin([Tags::getTable() => 't'], 'p.tag_id=t.id')
                ->field('t.name,p.article_id')->whereIn('p.article_id', $ids)->select()->toArray();

            $tags = [];
            if (!empty($tagsArr)) {
                foreach ($tagsArr as $v) {
                    $tags[$v['article_id']][] = $v['name'];
                }
            }

            foreach ($data as $v) {
                $v['distance'] = empty($v['lat']) && empty($v['lng']) ? 0 : $v['distance'];
                $v['cover'] = getBucket('poi_article', 'cover', $v['cover']);
                $v['tag'] = isset($tags[$v['id']]) ? $tags[$v['id']] : [];
                $v['id'] = encrypt($v['id'], Status::ENCRYPT_POI);
                unset($v['lat'], $v['lng']);
                $list[] = $v;
            }
        }

        success(['list' => $list, 'total_count' => $total_count]);

    }

    //    poi目录
    public function catalog($channels, $all_param)
    {

        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        $category = PoiArticle::alias('p')->leftJoin([PoiCategory::getTable() => 'c'], 'p.category=c.id')->field('c.id,c.name')->where('p.channel', $channel)
            ->where('p.status', self::POI_STATUS_OK)->where('p.shop_id', $shop_id)
            ->group('c.id')->order('c.id')->select()->toArray();

        success(['list' => $category]);

    }

}