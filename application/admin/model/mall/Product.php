<?php

namespace app\admin\model\mall;

use think\Model;


class Product extends Model
{

    

    

    // 表名
    protected $name = 'mall_product';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'add_time_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    



    public function getAddTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['add_time']) ? $data['add_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setAddTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function cate()
    {
        return $this->belongsTo('app\admin\model\mall\ProductCate', 'cate_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    public function eggkind()
    {
        return $this->belongsTo('app\admin\model\egg\Kind', 'kind_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
