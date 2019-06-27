<?php
/**
 * 数字专线对接城市大脑数据
 * User: 总裁
 * Date: 2019/6/17
 * Time: 13:17
 */

namespace lib;


class Brain
{
    const CDLAPI = 'http://120.27.218.187:38080/cdl_api/partner/';
    const PARTNER = 'feekr';
    const SECRETKEY = 'hekeLPGDcNrT3zpzdHaREjno4rpympKj';

    static function makeSign($url,$ts=0){
        !$ts && $ts = time();
        $func = Base32::encode($url);
        $snStr = self::PARTNER.$func.self::SECRETKEY.$ts;
        $sn = md5($snStr);
        $encode = 'base32';
        return self::CDLAPI.self::PARTNER.'/func/'.$func.'?ts='.$ts.'&sn='.$sn.'&encode='.$encode;
    }
    //获取列表
    static function getList(){
        $url = '/cdl/presql/25';
        $getUrl=self::makeSign($url);
        die(curl_file_get_contents($getUrl));
    }
    //获取详情 主要是这个
    static function getDetail($id){
        $url = "/cdl/object/tr_scene?fields=t.scene_id,t.name,t.value,t.source,t.ts&join=inner-join-(select+tr.name%2Cm.scene_id%2Cts%2Cm.value%2C%27czty%27+as+source%2CROW_NUMBER%28%29+OVER+%28partition+by+scene_id+order+by+ts+desc%29+as+rnum+from+tr_scene_population_real+m+inner+join+tr_scene+tr+on+m.scene_id+%3D+tr.id+where+m.source+%3D+%27CZTY%27+and+ts+%3E%3D+CURRENT_DATE+and+tag+%3D+%27GST%27+UNION+select+tr.name%2Cm.scene_id%2Cts%2Cm.value%2C%27getui%27+as+source%2CROW_NUMBER%28%29+OVER+%28partition+by+scene_id+order+by+ts+desc%29+as+rnum+from+tr_scene_population_real_getui+m+inner+join+tr_scene+tr+on+m.scene_id+%3D+tr.id+where+m.source+%3D+%27GETUI%27+and+ts+%3E%3D+CURRENT_DATE+and+tag+%3D+%27GST%27)-t-on-main.id=t.scene_id&where=rnum<=1&scene_id={$id}&order=scene_id-asc";
        $getUrl=self::makeSign($url);
        return curl_file_get_contents($getUrl);
    }

}