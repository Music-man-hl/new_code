<?php
/**
 * 数字专线相关
 * User: 总裁
 * Date: 2019/6/17
 * Time: 13:17
 */

namespace app\v4\handle\logic;

use app\v4\handle\hook\ProductInit;
use app\v4\model\Main\Shop;
use app\v4\model\Shop\DigitalArea;
use app\v4\model\Shop\DigitalArticleRecommend;
use app\v4\model\Shop\DigitalLine;
use app\v4\model\Shop\DigitalProduct;
use app\v4\model\Shop\DigitalProductRelation;
use app\v4\model\Shop\DigitalScenic;
use app\v4\model\Shop\DigitalSite;
use app\v4\model\Shop\DigitalSitePoi;
use app\v4\model\Shop\InformationArticle;
use app\v4\model\Shop\InformationArticleExt;
use app\v4\model\Shop\PoiArticle;
use app\v4\model\Shop\PoiArticleTag;
use app\v4\model\Shop\PoiCategory;
use app\v4\model\Shop\PoiTheme;
use app\v4\model\Shop\PoiThemeRelation;
use app\v4\model\Shop\Tags;
use app\v4\model\Shop\Tels;
use app\v4\Services\BaseService;
use lib\Brain;
use lib\Status;
use third\S;

class DigitalLogic extends BaseService
{

    const LINE_STATUS_OK = 1;//专线状态ok
    const POI_STATUS_OK = 1;//POI状态ok
    const ARTICLE_STATUS_OK = 1;//资讯状态ok
    const AREA_STATUS_OK = 1;//area ok

//  数字专线首页
    public function index($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $product_list = $slider = [];
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        $data['sub_shop_id'] = $shop_id;
        $data['start'] = 0;
        $data['limit'] = 2;
        $data['type'] = Status::TICKET_PRODUCT;
        $data['channel'] = $channel;//门店id

        $list = ProductInit::factory(Status::TICKET_PRODUCT)->apply('lists', $data);
        $productList = $list[0];
        $tagArr = $list[1];
        //product_list
        if (!empty($productList)) {

            $tels = Tels::field('citycode,tel')->where(['channel' => $channel, 'objid' => $shop_id, 'type' => 1])->find();
            $mobile = '';
            if (!empty($tels)) {
                $mobile = isMobile($tels['tel']) ? $tels['tel'] : ($tels['citycode'] . '-' . $tels['tel']);
            }

            $pids = array_column($productList->toArray(), 'id');
            $sites = [];
            if (!empty($pids)) {

                $getAllSites = DigitalProduct::alias('dp')->join([DigitalSite::getTable() => 'ds'], 'ds.line_id=dp.line_id')
                    ->where('dp.channel', $channel)->where('dp.shop_id', $shop_id)->whereIn('dp.product_id', $pids)
                    ->field('dp.product_id,ds.name,ds.sorts')->order(['dp.product_id', 'ds.sorts'])->select()->toArray();

                if (!empty($getAllSites)) {
                    $getSites = [];

                    foreach ($getAllSites as $v) {
                        $getSites[$v['product_id']][] = $v;
                    }

                    foreach ($getSites as $k => $v) {
                        $first = current($v);
                        $last = end($v);
                        $sites[$k] = ['first' => $first['name'], 'last' => $last['name']];
                    }

                }

            }

            foreach ($productList as $v) {
                $product_list[] = [
                    'id' => encrypt($v['id'], Status::ENCRYPT_PRODUCT),
                    'name' => $v['name'],
                    'cover' => picture($v['bucket'], $v['pic']),
                    'price' => floatval($v['price']),
                    'tag' => isset($tagArr[$v['id']]) ? $tagArr[$v['id']] : [],
                    'start' => isset($sites[$v['id']]['first']) ? $sites[$v['id']]['first'] : '',
                    'end' => isset($sites[$v['id']]['last']) ? $sites[$v['id']]['last'] : '',
                    'tel' => $mobile,
                ];
            }

        }

        //slider
        $areas = [];
        $area = DigitalArea::where('channel', $channel)->where('status', self::AREA_STATUS_OK)->field('id,name')->order('sorts')->select()->toArray();
        !empty($area) && $areas = array_column($area, 'name', 'id');

        $getSliders = DigitalLine::where('channel', $channel)->where('shop_id', $shop_id)->where('status', self::LINE_STATUS_OK)
            ->field('id,name,line_cover,area_id')->order(['sorts', 'id' => 'desc'])->select();

        if (!empty($getSliders)) {
            foreach ($getSliders as $v) {
                $slider[] = [
                    'id' => encrypt($v['id'], Status::ENCRYPT_DIGITAL),
                    'name' => $v['name'],
                    'url' => getBucket('digital_line', 'line_cover', $v['line_cover']),
                    'area' => isset($areas[$v['area_id']]) ? $areas[$v['area_id']] : ''
                ];
            }
        }

        success(compact('slider', 'product_list'));

    }

    //独家资讯
    public function consultation($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];
        $limit = startLimit($all_param);

        $getData = DigitalArticleRecommend::alias('r')->join([InformationArticle::getTable() => 'a'], 'a.id=r.article_id')
            ->where('r.channel', $channel)->where('r.shop_id', $shop_id)->where('a.status', self::ARTICLE_STATUS_OK);

        $total_count = $getData->count();//拉取数量

        $getData = $getData->field('a.id,a.create_time,a.cover,a.title')->order(['r.sorts', 'r.id'])
            ->limit($limit['start'], $limit['limit'])->select()->toArray();  //获取总数据

        $list = [];
        if (!empty($getData)) {
            foreach ($getData as $v) {
                $list[] = [
                    'id' => encrypt($v['id'], Status::ENCRYPT_ARTICLE),
                    'title' => $v['title'],
                    'add_time' => $v['create_time'],
                    'cover' => getBucket('information_article', 'cover', $v['cover']),
                ];
            }
        }

        success(['list' => $list, 'total_count' => (int)$total_count]);

    }


    //数字专线景点推荐分类
    public function scenic_type($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        $getData = PoiTheme::where('channel', $channel)->where('shop_id', $shop_id)->where('status', self::POI_STATUS_OK)
            ->field('id,name')->order(['sorts', 'id'])->limit(3)->select()->toArray();

        success(['list' => empty($getData) ? [] : $getData]);

    }

    //数字专线景点推荐列表
    public function scenic_list($channels, $all_param)
    {

        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];
        $id = intval($all_param['id']);
        if (empty($id)) error(40000, 'ID不能为空');

        $getData = PoiThemeRelation::alias('r')->join([PoiArticle::getTable() => 'a'], 'a.id=r.poi_id')
            ->field('a.id,a.`name`,a.intro,a.cover')
            ->where('r.channel', $channel)->where('r.shop_id', $shop_id)->where('r.theme_id', $id)->where('a.status', self::POI_STATUS_OK)
            ->order(['r.sorts', 'r.id'])->limit(2)->select()->toArray();

        $list = [];

        if (!empty($getData)) {
            $tag = [];
            $ids = array_column($getData, 'id');

            $getTags = PoiArticleTag::alias('r')->join([Tags::getTable() => 'a'], 'a.id=r.tag_id')
                ->field('a.`name`,r.article_id')
                ->whereIn('r.article_id', $ids)
                ->order(['a.id'])->select()->toArray();

            if (!empty($getTags)) {
                foreach ($getTags as $v) {
                    $tag[$v['article_id']][] = $v['name'];
                }
            }

            foreach ($getData as $v) {
                $list[] = [
                    'id' => encrypt($v['id'], Status::ENCRYPT_POI),
                    'name' => $v['name'],
                    'intro' => $v['intro'],
                    'cover' => getBucket('poi_article', 'cover', $v['cover']),
                    'tag' => isset($tag[$v['id']]) ? $tag[$v['id']] : []
                ];
            }

        }

        success(['list' => $list]);

    }

    //数字专线列表
    public function area_list($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];
        $area_id = intval($all_param['area_id']);
        if (empty($area_id)) error(40000, 'area_id必传');

        $list = [];

        $countyLists = DigitalLine::alias('l')->join([DigitalArea::getTable() => 'a'], 'a.id=l.area_id')->field('l.name as title,l.id,l.cover')
            ->where('l.channel', $channel)->where('l.shop_id', $shop_id)->where('l.status', self::LINE_STATUS_OK)
            ->where('l.area_id', $area_id)->order(['l.sorts', 'l.id'])->select()->toArray();

        if (!empty($countyLists)) {
            $ids = array_column($countyLists, 'id');

            $sites = [];
            if (!empty($ids)) {

                $getAllSites = DigitalSite::where('channel', $channel)->where('shop_id', $shop_id)->whereIn('line_id', $ids)
                    ->field('line_id,name,sorts')->order(['sorts', 'id'])->select()->toArray();

                if (!empty($getAllSites)) {
                    $getSites = [];

                    foreach ($getAllSites as $v) {
                        $getSites[$v['line_id']][] = $v;
                    }

                    foreach ($getSites as $k => $v) {
                        $first = current($v);
                        $last = end($v);
                        $sites[$k] = ['first' => $first['name'], 'last' => $last['name']];
                    }

                }

            }

            foreach ($countyLists as $v) {
                $list[] = [
                    'id' => encrypt($v['id'], Status::ENCRYPT_DIGITAL),
                    'title' => $v['title'],
                    'cover' => getBucket('digital_line', 'cover', $v['cover']),
                    'start' => isset($sites[$v['id']]['first']) ? $sites[$v['id']]['first'] : '',
                    'end' => isset($sites[$v['id']]['last']) ? $sites[$v['id']]['last'] : '',
                ];
            }

        }

        success(['list' => $list]);
    }

    //数字专线地区列表
    public function area($channels, $all_param)
    {

        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

//        $countyLists = DigitalLine::alias('l')->join([DigitalArea::getTable() => 'a'], 'a.id=l.area_id')->field('a.name,a.id,a.cover')
//            ->where('l.channel', $channel)->where('l.shop_id', $shop_id)->where('l.status', self::LINE_STATUS_OK)
//            ->group('a.id')->order(['l.sorts', 'l.id'])->select()->toArray();

        $countyLists = DigitalArea::field('name,id,cover')->where('status', self::AREA_STATUS_OK)
            ->where('channel', $channel)->order(['sorts', 'id'])->select()->toArray();

        success(['list' => empty($countyLists) ? [] : $countyLists]);

    }

//  数字专线文章详情
    public function article_detail($channels, $all_param)
    {

        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        $id = isset($all_param['article_id']) ? $all_param['article_id'] : (isset($all_param['id']) ? $all_param['id'] : '');
        if (empty($id)) error(40000, 'id必传');

        $article_id = intval(encrypt(trim($id), Status::ENCRYPT_ARTICLE, false));
        if (empty($article_id)) error(40000, 'id不正确');

        $article = InformationArticle::alias('a')->join([InformationArticleExt::getTable() => 'e'], 'a.id=e.article_id')
            ->field('a.title,a.avatar,a.author,a.create_time as add_time,e.content as detail')
            ->where('a.id', $article_id)->where('a.channel', $channel)->where('a.shop_id', $shop_id)->where('a.status', self::ARTICLE_STATUS_OK)->find();

        if (empty($article)) {
            error(50000, '没有找到此文章');
        }

        $info = InformationArticle::get($article_id);
        $info->read_num = ['inc', 1];
        if (!$info->save()) {
            error(50000, '阅读数失败');
        }

        $list = [
            'title' => $article['title'],
            'avatar' => getBucket('information_article', 'avatar', $article['avatar']),
            'author' => $article['author'],
            'add_time' => $article['add_time'],
            'detail' => $article['detail'],
        ];

        success($list);

    }


    //数字单线主页
    public function single_index($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        if (!isset($all_param['id'])) error(40000, 'id必传');
        $id = intval(encrypt(trim($all_param['id']), Status::ENCRYPT_DIGITAL, false));
        if (empty($id)) error(40000, 'id不正确');

        $data = DigitalLine::where('id', $id)->where('channel', $channel)->where('shop_id', $shop_id)->where('status', self::LINE_STATUS_OK)
            ->field('cover,intro,intro_url as url,appid,app_url,app_data as data')->find();

        if (empty($data)) {
            error(50000, '没有找到此单线');
        }

        $data['cover'] = getBucket('digital_line', 'cover', $data['cover']);

        success(empty($data) ? ['cover' => '', 'intro' => '', 'url' => '', 'appid' => '', 'app_url' => '', 'data' => ''] : $data);

    }

    //数字单线线路列表
    public function single_list($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        if (!isset($all_param['id'])) error(40000, 'id必传');
        $id = intval(encrypt(trim($all_param['id']), Status::ENCRYPT_DIGITAL, false));
        if (empty($id)) error(40000, 'id不正确');

        $line = DigitalLine::where('channel', $channel)->where('shop_id', $shop_id)->where('status', self::LINE_STATUS_OK)->field('id')->get($id);

        if ($line->isEmpty()) {
            error(50000, '没有找到此单线');
        }

        $data = DigitalSite::field('id,name')->where('line_id', $id)->where('')->order(['sorts', 'id'])->select()->toArray();

        success(['list' => empty($data) ? [] : $data]);

    }

    //数字专线容量
    public function number($channels, $all_param)
    {

        if (!isset($all_param['scenic_id'])) error(40000, 'id必传');
        $id = intval($all_param['scenic_id']);
        if (empty($id)) error(40000, 'id不正确');

        $scenic = DigitalScenic::where('scenic_id', $id)->field('peak')->find();
        if (empty($scenic)) {
            error(50000, 'id 错误'); //14100
        }

        $peak = abs((int)$scenic['peak']);
        $currentNumber = 0;
        $bestNumber = '舒适';  //最大容量的40%以下为舒适，60%为拥挤，80%为非常拥挤

        if ($peak) {
            $array_key = [ceil($peak * 0.8), ceil($peak * 0.6), ceil($peak * 0.4)];
            $array_val = ['非常拥挤', '拥挤', '舒适'];
            $array = array_combine($array_key, $array_val);

            $data = Brain::getDetail($id);
            if (!empty($data) && is_string($data)) {
                $result = \json_decode($data, true);
                if (isset($result['rc']) && $result['rc'] == 0 && isset($result['obj']['0']['value'])) {
                    $currentNumber = abs((int)$result['obj']['0']['value']);
                    if ($currentNumber > 0) {
                        foreach ($array as $k => $v) {
                            if ($currentNumber >= $k) {
                                $bestNumber = $v;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $list = [
            'maxNumber' => $peak,
            'currentNumber' => $currentNumber,
            'bestNumber' => $bestNumber,
        ];

        success($list);

    }

    //数字单线详情poi
    public function single_detail($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        if (!isset($all_param['id'])) error(40000, 'id必传');
        $id = intval(encrypt(trim($all_param['id']), Status::ENCRYPT_POI, false));
        if (empty($id)) error(40000, 'id不正确');

        $type = isset($all_param['type']) ? intval($all_param['type']) : Status::SHOP_TYPE_ALL;
        $distance = 0;

        $data = PoiArticle::where('channel', $channel)->where('shop_id', $shop_id)->where('status', self::ARTICLE_STATUS_OK)
            ->get($id, ['tags']);

        if ($data->isEmpty()) {
            error(50000, '没有找到此数据');
        }

        $result = $data->toArray();

        $images = [];
        if (!empty($result['images'])) {
            foreach ($result['images'] as $v) {
                $images[] = getBucket('poi_article', 'images', $v);
            }
        }

        if ($type == Status::SHOP_TYPE_ALL) { //需要计算距离酒店的距离

            $getShop = Shop::field('lng,lat')->where('id', $shop_id)->find();
            if (!empty($getShop)) {
                $lat1 = floatval($getShop['lat']);
                $lng1 = floatval($getShop['lng']);
                if (!empty($lat1) && !empty($lng1)) {
                    $distance = S::getDistance($lat1, $lng1, floatval($result['lat']), floatval($result['lng']));
                }
            }

        }

        $list = [
            'name' => $result['name'],
            'tag' => empty($result['tags']) ? [] : array_column($result['tags'], 'name'),
            'address' => $result['address'],
            'tel' => $result['tel'],
            'time' => $result['time'],
            'ticket' => $result['fee'],
            'tips' => $result['tips'],
            'images' => $images,
            'intro' => $result['intro'],
            'website' => $result['website'],
            'traffic' => $result['transportation'],
            'lat' => $result['lat'],
            'lng' => $result['lng'],
            'scenic_id' => (int)$result['scenic_id'],
            'cover' => getBucket('poi_article', 'cover', $result['cover']),
            'distance' => $distance,
        ];

        success($list);

    }

    //数字单线分类
    public function type_list($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        if (!isset($all_param['id']) || !isset($all_param['site_id'])) error(40000, 'id必传');
        $site_id = intval($all_param['site_id']);
        $id = intval(encrypt(trim($all_param['id']), Status::ENCRYPT_DIGITAL, false));
        if (empty($id) || empty($site_id)) error(40000, 'id不正确');

        $category = PoiArticle::alias('p')->leftJoin([PoiCategory::getTable() => 'c'], 'p.category=c.id')
            ->leftJoin([DigitalSitePoi::getTable() => 'sp'], 'sp.poi_id=p.id')->field('c.id,c.name')->where('p.channel', $channel)
            ->where('p.status', self::ARTICLE_STATUS_OK)->where('p.shop_id', $shop_id)->where('sp.line_id', $id)->where('sp.site_id', $site_id)
            ->group('c.id')->order('c.id')->select()->toArray();

        success(['list' => $category]);

    }

    //数字单线首页四美推荐
    public function commend_list($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        if (!isset($all_param['single_id']) || !isset($all_param['id'])) error(40000, 'id必传');
        $id = intval($all_param['id']);
        $single_id = intval(encrypt(trim($all_param['single_id']), Status::ENCRYPT_DIGITAL, false));
        if (empty($id) || empty($single_id)) error(40000, 'id不正确');

        $category = array_column(PoiCategory::all()->toArray(), 'name', 'id'); //获取所有的分类  //一次拉取所有的数据
        $site = DigitalSite::where('id', $id)->where('channel', $channel)->where('shop_id', $shop_id)
            ->where('line_id', $single_id)->field('lng,lat')->find();
        if (empty($site)) error(50000, '没有找到此站点');

        $lat = $site['lat']; //站点的经纬度
        $lng = $site['lng'];

        $poiData = DigitalSitePoi::alias('sp')->leftJoin([PoiArticle::getTable() => 'a'], 'sp.poi_id=a.id')
            ->field('a.id,a.name,a.intro,a.cover,a.lng,a.lat,a.category,sp.sorts')
            ->where('sp.site_id', $id)->where('a.status', self::ARTICLE_STATUS_OK)->order(['sp.sorts', 'sp.id'])->select()->toArray();

        $list = [];
        $getPoi = [];
        if (!empty($poiData)) {

            foreach ($poiData as $v) {
                $getPoi[$v['category']][] = $this->pois($v, $lat, $lng);
            }

            foreach ($getPoi as $k => $v) {
                $poi_count = count($v);
                if ($poi_count <= 1) {
                    $poi_list = $v;
                } else {
                    //需要再次排序
                    foreach ($v as $key => $row) {
                        $volume[$key] = $row['sorts'];
                        $edition[$key] = $row['distance'];
                    }
                    array_multisort($volume, SORT_ASC, $edition, SORT_ASC, $v);
                    $poi_list = array_slice($v, 0, 2);
                    unset($volume, $edition, $v);
                }

                $list[] = [
                    'poi_name' => isset($category[$k]) ? $category[$k] : '',
                    'poi_list' => $poi_list,
                    'id' => $k,
                    'count' => $poi_count,
                ];
            }

            sort_array($list, 'id');

        }

        success(['list' => $list]);

    }

    //数字单线四美列表
    public function poi_list($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        if (!isset($all_param['single_id']) || !isset($all_param['id']) || !isset($all_param['type_id'])) error(40000, 'id必传');
        $id = intval($all_param['id']);
        $type_id = intval($all_param['type_id']);
        $single_id = intval(encrypt(trim($all_param['single_id']), Status::ENCRYPT_DIGITAL, false));
        if (empty($id) || empty($single_id) || empty($type_id)) error(40000, 'id不正确');

        $site = DigitalSite::where('id', $id)->where('channel', $channel)->where('shop_id', $shop_id)
            ->where('line_id', $single_id)->field('lng,lat')->find();
        if (empty($site)) error(50000, '没有找到此站点');

        $lat = $site['lat']; //站点的经纬度
        $lng = $site['lng'];

        $poiData = DigitalSitePoi::alias('sp')->leftJoin([PoiArticle::getTable() => 'a'], 'sp.poi_id=a.id')
            ->field('a.id,a.name,a.intro,a.cover,a.lng,a.lat,a.category,sp.sorts')->where('sp.site_id', $id)
            ->where('a.status', self::ARTICLE_STATUS_OK)->where('a.category', $type_id)->order(['sp.sorts', 'sp.id'])->select()->toArray();

        $ids = array_column($poiData, 'id');//查询tag用

        $tagsArr = PoiArticleTag::alias('p')->leftJoin([Tags::getTable() => 't'], 'p.tag_id=t.id')
            ->field('t.name,p.article_id')->whereIn('p.article_id', $ids)->select()->toArray();

        $tags = [];
        if (!empty($tagsArr)) {
            foreach ($tagsArr as $v) {
                $tags[$v['article_id']][] = $v['name'];
            }
        }

        $list = [];

        if (!empty($poiData)) {

            foreach ($poiData as $k => $v) {
                $id = $v['id'];
                $v = $this->pois($v, $lat, $lng);
                $v['tag'] = isset($tags[$id]) ? $tags[$id] : [];
                $list[] = $v;
            }

            //需要再次排序
            foreach ($list as $key => $row) {
                $volume[$key] = $row['sorts'];
                $edition[$key] = $row['distance'];
            }

            array_multisort($volume, SORT_ASC, $edition, SORT_ASC, $list);

        }

        success(['list' => $list]);

    }

    //处理距离数据
    private function pois($v, $lat, $lng)
    {
        return [
            'id' => encrypt($v['id'], Status::ENCRYPT_POI),
            'name' => $v['name'],
            'intro' => $v['intro'],
            'distance' => abs(S::getDistance($lat, $lng, $v['lat'], $v['lng'])),
            'sorts' => $v['sorts'],
            'cover' => getBucket('poi_article', 'cover', $v['cover']),
        ];
    }

    //数字单线购买方式
    public function buy_type($channels, $all_param)
    {
        $shop = self::shop($all_param);
        $channel = $shop['channel'];
        $shop_id = $shop['shop_id'];
        $appid = $url = $mobile = '';

        if (!isset($all_param['id']) || !isset($all_param['type'])) error(40000, 'id必传');
        $type = intval($all_param['type']);
        $id = intval(encrypt($all_param['id'], Status::ENCRYPT_PRODUCT, false));
        if (empty($id) || empty($type)) error(40000, 'id不正确');

        if ($type == Status::SHOP_TYPE_DIGITAL) {

            $data = DigitalProductRelation::where('channel', $channel)->where('pid', $id)->find();
            if (empty($data)) {
                $tels = Tels::field('citycode,tel')->where(['channel' => $channel, 'objid' => $shop_id, 'type' => 1])->find();
                if (!empty($tels)) {
                    $mobile = isMobile($tels['tel']) ? $tels['tel'] : ($tels['citycode'] . '-' . $tels['tel']);
                }
            } else {
                $appid = $data->appid;
                $url = $data->url;
            }

        }

        $list = [
            'mobile' => $mobile,
            'appid' => $appid,
            'url' => $url,
        ];

        success($list);

    }

//数字专线列表新结构
    public function area_lists($channels, $all_param)
    {

        $shop = self::shop($all_param);
        $shop_id = $shop['shop_id'];
        $channel = $shop['channel'];

        //获取所有地区
        $areaLists = DigitalArea::field('name,id')->where('status', self::AREA_STATUS_OK)
            ->where('channel', $channel)->order(['sorts', 'id'])->select()->toArray();

        $countyLists = $list = $getSites = $sites = [];

        //获取所有的专线
        $countyAllLists = DigitalLine::alias('l')->join([DigitalArea::getTable() => 'a'], 'a.id=l.area_id')->field('l.name as title,l.id,l.cover,l.area_id')
            ->where('l.channel', $channel)->where('l.shop_id', $shop_id)->where('l.status', self::LINE_STATUS_OK)->where('a.status', self::AREA_STATUS_OK)
            ->order(['l.sorts', 'l.id'])->select()->toArray();

        if (!empty($countyAllLists)) {

            $areas = array_column($areaLists, 'name', 'id');

            foreach ($countyAllLists as $v) {
                $countyLists[$v['area_id']][] = $v;
            }

            if (!empty($countyLists)) {

                //获取所有的站点
                $getAllSites = DigitalSite::where('channel', $channel)->where('shop_id', $shop_id)
                    ->field('line_id,name,sorts')->order(['line_id', 'sorts', 'id'])->select()->toArray(); //拉出所有站点

                if (!empty($getAllSites)) {
                    //获取所有站点，并得到第一站和最后一站
                    foreach ($getAllSites as $v) {
                        $getSites[$v['line_id']][] = $v;
                    }

                    foreach ($getSites as $k => $v) {
                        $first = current($v);
                        $last = end($v);
                        $sites[$k] = ['first' => $first['name'], 'last' => $last['name']];
                    }

                }

            }

            //格式化数据
            foreach ($countyLists as $k => $item) {
                $area_data = [];
                foreach ($item as $v) {
                    $area_data[] = [
                        'id' => encrypt($v['id'], Status::ENCRYPT_DIGITAL),
                        'title' => $v['title'],
                        'cover' => getBucket('digital_line', 'cover', $v['cover']),
                        'start' => isset($sites[$v['id']]['first']) ? $sites[$v['id']]['first'] : '',
                        'end' => isset($sites[$v['id']]['last']) ? $sites[$v['id']]['last'] : '',
                    ];
                }
                $list[] = [
                    'id' => $k,
                    'name' => $areas[$k],
                    'area_data' => $area_data,
                ];
            }

        }

        success(['list' => $list]);
    }

}