<?php

namespace app\common\model;

use think\Model;

/**
 * 订单管理
 */
class Order Extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    // 追加属性
    protected $append = [
    ];

    // 生成订单号
    public static function getOrderSn($prefix="M")
    {
        $order_sn = $prefix.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        return $order_sn;
    }
}
