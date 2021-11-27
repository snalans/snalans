<?php

namespace app\admin\model\egg;

use think\Model;
use think\Db;


class Log extends Model
{

    

    

    // 表名
    protected $name = 'egg_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    /*
     * 蛋数量变动写入日志
     * user_id :用户id  kind_id ：蛋类型 类型 type : 0=农场 1=订单 2=互转 3=合成 4=管理员操作 9=手续费
     * order_sn : 订单号 number : 数量 note : 备注
     */
    public static function saveLog($user_id,$kind_id,$type=0,$order_sn='',$number=0,$before=0,$after=0,$note='')
    {
        $data = [];
        $data['user_id']    = $user_id;
        $data['kind_id']    = $kind_id;
        $data['type']       = $type;
        $data['order_sn']   = $order_sn;
        $data['number']     = $number;
        $data['before']     = $before;
        $data['after']      = $after;
        $data['note']       = $note;
        $data['createtime'] = time();
        $rs = Db::name("egg_log_".date("Y_m"))->insert($data);
        return $rs;
    }





    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function eggkind()
    {
        return $this->belongsTo('app\admin\model\egg\Kind', 'kind_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
