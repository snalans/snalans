<?php

namespace app\admin\model\egg;

use think\Model;


class HatchConfig extends Model
{

    

    

    // 表名
    protected $name = 'egg_hatch_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function eggkind()
    {
        return $this->belongsTo('app\admin\model\egg\Kind', 'kind_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
