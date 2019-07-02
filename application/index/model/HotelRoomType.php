<?php
/**
 *
 * User: yanghaoliang
 * Date: 2019-05-07
 * Email: <haoliang.yang@gmail.com>
 */

namespace app\index\model;


use think\Model;

class HotelRoomType extends Model
{
    const STATUS_OK = 1;//上线  房型状态
    const STATUS_NO = 0;//下线
    const STATUS_DEL = 3;//删除

    const STATUS_SALE_OK = 1;//房态开
    const STATUS_SALE_NO = 0;//房态关

}