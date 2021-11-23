<?php

namespace app\admin\model\egg;

use think\Model;
use think\Db;


class RewardConfig extends Model
{

    

    

    // 表名
    protected $name = 'egg_reward_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    

    /*
     * 直推奖励
     */
    public static function getAward($user_id=0)
    {
        $result = Db::name("user")->field("pid,serial_number")->where("id",$user_id)->find();
        $pid = $result['pid'];
        $serial_number = $result['serial_number'];

        if($pid){
            $valid_number = Db::name("user")->where("id",$pid)->value("valid_number");
            $wh = [];
            $wh['pid'] = $pid;
            $wh['status'] = 'normal';
            $wh['is_attestation'] = 1;
            $number = Db::name("user")->where("pid",$pid)->count();

            $wh = [];
            $wh['number'] = ['<=',$number];
            $wh['valid_number'] = ['<=',$valid_number];
            $info = Db::name("egg_reward_config")->where($wh)->order("number DESC,id DESC")->find();

            if(!empty($info)){
                if($info['number']>0){                    
                    $wh = [];
                    $wh['user_id']          = $pid;
                    $wh['type']             = 2;
                    $wh['reward_config_id'] = $info['id'];
                    $result = Db::name("egg_nest_log")->where($wh)->find();
                    if(empty($result)){
                        Db::startTrans();
                        $wh = [];
                        $wh['user_id']        = $pid;
                        $wh['nest_kind_id']   = $info['nest_kind_id'];
                        $wh['kind_id']        = $info['nest_kind_id'];
                        $position = Db::name("egg_hatch")->where($wh)->order("position","DESC")->value("position");
                        $data = [];
                        $data['user_id']        = $pid;
                        $data['nest_kind_id']   = $info['nest_kind_id'];
                        $data['kind_id']        = $info['nest_kind_id'];
                        $data['status']         = 1;
                        $data['hatch_num']      = 0;
                        $data['shape']          = 0;
                        $data['is_reap']        = 0;
                        $data['position']       = $position+1;
                        $data['createtime']     = time();
                        $hatch_id = Db::name("egg_hatch")->insertGetId($data);   

                        $log = [];
                        $log['user_id']          = $pid;
                        $log['nest_kind_id']     = $info['nest_kind_id'];
                        $log['reward_config_id'] = $info['id'];
                        $log['type']             = 2;
                        $log['number']           = 1;
                        $log['note']             = "会员编号：".$serial_number.'升级,获得直推奖励';
                        $log['createtime']       = time();
                        $log_rs = Db::name("egg_nest_log")->insertGetId($log);
                        if($hatch_id && $log_rs){
                            Db::commit();
                        }else{
                            Db::rollback();
                        }
                    }
                }
            }
        }
    }




    public function eggnestkind()
    {
        return $this->belongsTo('app\admin\model\EggNestKind', 'nest_kind_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
