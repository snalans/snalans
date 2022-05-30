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
     * 直推奖励 flag true:自己  false:上级
     */
    public static function getAward($user_id=0,$flag=false)
    {
        if($flag){
            $pid = $user_id;
            $note = "有效值达标";
        }else{            
            $result = Db::name("user")->field("pid,serial_number")->where("id",$user_id)->find();
            $pid = $result['pid'];
            $serial_number = $result['serial_number'];
            $note = "会员编号：".$serial_number.'升级,获得直推奖励';
        }
        
        if($pid){
            $wh = [];
            $wh['user_id']          = $pid;
            $wh['type']             = 2;
            $ids = Db::name("egg_nest_log")->where($wh)->value("group_concat(reward_config_id)");

            $valid_number = Db::name("user")->where("id",$pid)->value("valid_number");
            $wh = [];
            $wh['pid'] = $pid;
            $wh['status'] = 'normal';
            $wh['is_attestation'] = 1;
            $number = Db::name("user")->where($wh)->count();

            $wh = [];
            $wh['user_id']      = $pid;
            $wh['is_close']     = 1;
            $close_num = Db::name("egg_hatch")->where($wh)->count();
            if(!empty($close_num)){
                $wh = [];
                $wh['number']       = ['<=',$number];
                $wh['valid_number'] = ['<=',$valid_number];
                $info = Db::name("egg_reward_config")->where($wh)->order("nest_kind_id","DESC")->find();
                if(!empty($info)){
                    $wh = [];
                    $wh['user_id']        = $pid;
                    $wh['nest_kind_id']   = $info['nest_kind_id'];
                    $wh['is_close']       = 1;
                    $hinfo = Db::name("egg_hatch")->where($wh)->order("position asc")->find();
                    if(!empty($hinfo)){
                        $rs = Db::name("egg_hatch")->where("id",$hinfo['id'])->update(['is_close'=>0]);   
                    }
                }
            }else{
                $wh = [];
                if(!empty($ids)){
                    $wh['id']       = ['not in',$ids];
                }
                $wh['number']       = ['<=',$number];
                $wh['valid_number'] = ['<=',$valid_number];
                $info = Db::name("egg_reward_config")->where($wh)->order("nest_kind_id","DESC")->find();
                if(!empty($info)){
                    $total = Db::name("egg_nest_kind")->where("kind_id",$info['nest_kind_id'])->value("total");
                    $wh = [];
                    $wh['user_id']      = $pid;
                    $wh['nest_kind_id'] = $info['nest_kind_id'];
                    $wh['is_close']     = 0;
                    $num = Db::name("egg_hatch")->where($wh)->count();
                    if($num >= $total){
                        return false;
                    }          
                    Db::startTrans();
                    $wh = [];
                    $wh['user_id']        = $pid;
                    $wh['nest_kind_id']   = $info['nest_kind_id'];
                    $wh['kind_id']        = $info['nest_kind_id'];
                    $position = Db::name("egg_hatch")->where($wh)->max("position");
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
                    $log['note']             = $note;
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

    /*
     * 未达标直推奖励回收
     */
    public static function decAward($hatch_id=0)
    {
        $wh = [];
        $wh['id']       = $hatch_id;
        $wh['is_buy']   = 0;
        $hinfo = Db::name("egg_hatch")->where($wh)->find();   
        if($hinfo['kind_id'] == 1 && $hinfo['position'] <= 3){            
            return true;
        }else if($hinfo['kind_id'] == 2 && $hinfo['position'] <= 2){
            return true;
        }else if($hinfo['kind_id'] == 3 && $hinfo['position'] <= 1){
            return true;
        }
        $valid_number = Db::name("user")->where("id",$hinfo['user_id'])->value("valid_number");
        $wh = [];
        $wh['pid']              = $hinfo['user_id'];
        $wh['status']           = 'normal';
        $wh['is_attestation']   = 1;
        $number = Db::name("user")->where($wh)->count();

        if($hinfo['kind_id'] == 1){
            $orderby = $hinfo['position']==4?"number asc":"number desc";
        }else if($hinfo['kind_id'] == 2){
            $orderby = $hinfo['position']==3?"number asc":"number desc";
        }else if($hinfo['kind_id'] == 3){
            $orderby = $hinfo['position']==2?"number asc":"number desc";            
        }

        $info = Db::name("egg_reward_config")->where("nest_kind_id",$hinfo['kind_id'])->order($orderby)->find();
        if(!empty($info)){  
            if($info['number'] > $number || $info['valid_number'] > $valid_number){                
                Db::startTrans();
                $rs = Db::name("egg_hatch")->where("id",$hatch_id)->update(['is_close'=>1]);   
                $log = [];
                $log['user_id']          = $hinfo['kind_id'];
                $log['nest_kind_id']     = $info['nest_kind_id'];
                $log['reward_config_id'] = $info['id'];
                $log['type']             = 2;
                $log['number']           = -1;
                $log['note']             = "未达到条件回收";
                $log['createtime']       = time();
                $log_rs = Db::name("egg_nest_log")->insertGetId($log);   
                if($rs && $log_rs){
                    Db::commit();
                    return false;
                }else{
                    Db::rollback();
                }
            }
        }
        return true;
    }


    public function eggnestkind()
    {
        return $this->belongsTo('app\admin\model\EggNestKind', 'nest_kind_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
