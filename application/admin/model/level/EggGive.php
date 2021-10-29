<?php

namespace app\admin\model\level;

use think\Model;


class EggGive extends Model
{

    

    

    // 表名
    protected $name = 'egg_give';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    





    public function level()
    {
        return $this->belongsTo('app\admin\model\level\UserLevelConfig', 'level', 'level', [], 'LEFT')->setEagerlyType(0);
    }



    public function eggkind()
    {
        return $this->belongsTo('app\admin\model\EggKind', 'kind_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
