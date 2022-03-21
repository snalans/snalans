<?php
namespace app\common\model;

use think\Db;
use think\Model;

class UserLevelConfig extends Model
{
    /**
     * 变更链农场主等级(农场主等级只升不降)
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="user_id", type="integer", description="用户id")
     */
    public function update_vip($user_id){
        $user_info = Db::name("user")
            ->field('id,valid_number,level')
            ->where(['id'=>$user_id])
            ->find();
        if($user_info){
            //团队数量
            $team_where = [];
            $team_where['c.ancestral_id'] = $user_id;
            $team_where['c.level']        = ['<',4];
            $team_where['u.status']        = ['=','normal'];
            $team_where['u.is_attestation']        = ['=',1];
            $team_num = Db::name("membership_chain")->alias("c")
                ->join("user u","u.id=c.user_id","LEFT")
                ->where($team_where)
                ->count();

            //直推数量
            $p_where = array(
                'pid'=>array('eq',$user_id),
                'status'=>array('eq','normal'),
                'is_attestation'=>array('eq',1)
            );
            $p_num = Db::name("user")->where($p_where)->count();

            //团队有效值
            $wh = [];
            $wh['c.ancestral_id'] = $user_id;
            $wh['c.level']        = ['<',4];
            $wh['u.status']        = ['=','normal'];
            $wh['u.is_attestation']        = ['=',1];
            $valid_number = Db::name("membership_chain")->alias("c")
                ->join("user u","u.id=c.user_id","LEFT")
                ->where($wh)
                ->sum("u.valid_number");

            // $total_valid_number = $valid_number + $user_info['valid_number'];
            $total_valid_number = $valid_number;


            $config_bonus_info = Db::name("user_level_config")
                ->where('level',1)
                ->find();
            if(empty($config_bonus_info)){
                return true;
            }

            if($p_num<$config_bonus_info['number'] || $team_num<$config_bonus_info['team_number'] || $total_valid_number<$config_bonus_info['valid_number']){
                if($user_info['level'] > 0){
                    Db::name("user")->where(['id'=>$user_id])->data(['level'=>0])->update();
                }
                return true;
            }

            //农场主等级
            $level = $this->vip($user_id,$user_info['level'],$p_num,$team_num,$total_valid_number);
            if($level < $user_info['level']){
                Db::name("user")->where(['id'=>$user_id])->data(['level'=>$level])->update();
            }else if($level!=$user_info['level'] && ($level > $user_info['level'])){
                $re = Db::name("user")
                    ->where(['id'=>$user_id])
                    ->data(array('level'=>$level))
                    ->update();
                $wh = [];
                $wh['user_id']  =  $user_id;
                $wh['type']     =  10;
                $wh['note']     = "农场主等级升级到".$level."级赠送";
                $info = Db::name("egg_log")->where($wh)->find();
                if($re==true && empty($info)){
                    //赠送蛋
                    $egg_give = Db::name("egg_give")
                        ->field('*')
                        ->where(['level'=>$level])
                        ->select();
                    if(count($egg_give)>0){
                        foreach ($egg_give as $k=>$v){
                            //增加蛋
                            $wh = [];
                            $wh['user_id'] =  $user_id;
                            $wh['kind_id'] = $v['kind_id'];
                            $before = Db::name("egg")->where($wh)->value('hatchable');
                            $add_rs = Db::name("egg")->where($wh)->inc('hatchable',$v['number'])->inc('frozen',$v['number'])->update();;

                            //蛋日志
                            $log_add = \app\admin\model\egg\Log::saveLog($user_id,$v['kind_id'],10,"",$v['number'],$before,($before+$v['number']),"农场主等级升级到".$level."级赠送");
                        }
                    }

                    //赠送窝
                    $egg_nest_give = Db::name("egg_nest_give")
                        ->field('*')
                        ->where(['level'=>$level])
                        ->select();
                    if(count($egg_nest_give)>0){
                        $datas = [];
                        $nests_log = [];
                        foreach ($egg_nest_give as $kk=>$vv) {
                            if($vv['number']>0){
                                for ($i=1; $i <= $vv['number']; $i++) {
                                    $data = [];
                                    $data['user_id']        = $user_id;
                                    $data['kind_id']        = $vv['nest_kind_id'];
                                    $data['nest_kind_id']   = $vv['nest_kind_id'];
                                    $data['position']       = $i;
                                    $datas[] = $data;
                                }

                                $nest_log = [];
                                $nest_log['user_id']      = $user_id;
                                $nest_log['nest_kind_id'] = $vv['nest_kind_id'];
                                $nest_log['type']       = 0;
                                $nest_log['number']     = $vv['number'];
                                $nest_log['note']       = "农场主等级升级到".$level."级赠送";
                                $nest_log['createtime']       = time();
                                $nests_log[] = $nest_log;
                            }
                        }
                        //增加窝
                        Db::name("egg_hatch")->insertAll($datas);
                        //窝日志
                        Db::name("egg_nest_log")->insertAll($nests_log);
                    }
                }
            }else{
                return true;
            }
        }else{
            return true;
        }

    }

    /**
     * 用户农场主等级
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="user_id", type="integer", description="用户id")
     * @ApiParams   (name="level", type="integer", description="用户目前等级")
     * @ApiParams   (name="p_num", type="integer", description="直推数量")
     * @ApiParams   (name="team_num", type="integer", description="团队数量")
     * @ApiParams   (name="valid_number", type="integer", description="有效值")
     *
     * @ApiReturnParams   (name="level", type="integer", description="用户最终等级")
     */
    public function vip($user_id,$level,$p_num = 0,$team_num = 0,$valid_number){
        $where = [];
        $where['level'] = ['>',$level];
        $config_bonus = Db::name("user_level_config")
            ->where($where)
            ->order('level desc')
            ->select();
        if(count($config_bonus)>0){
            foreach ($config_bonus as $ke => $val) {
                if($p_num>=$val['number'] && $team_num>=$val['team_number'] && $valid_number>=$val['valid_number']){
                    //直推农场主数量
                    $u_where = array(
                        'level'=>array('egt',$val['user_level']),
                        'pid'=>array('eq',$user_id),
                        'status'=>array('eq','normal'),
                        'is_attestation'=>array('eq',1)
                    );
                    $user_number = Db::name("user")->where($u_where)->count();
                    if ($val['user_number']<=$user_number){
                        //直推有效值人数
                        $v_where = array(
                            'pid'=>array('eq',$user_id),
                            'valid_number'=>array('egt',$val['person_valid_number']),
                            'status'=>array('eq','normal'),
                            'is_attestation'=>array('eq',1)
                        );
                        $total_people_number = Db::name('user')->where($v_where)->count();
                        if($total_people_number>= $val['person_number']){
                            $level = $val['level'];
                            break;
                        }

                        //会员等级直推蛋购买
//                        $user_level_buy = Db::name("user_level_buy")->where(['level'=>$val['level']])->select();
//                        $user_level_buy_number = Db::name("user_level_buy")->where(['level'=>$val['level']])->count();
//                        if($user_level_buy_number>0){
//                            $i = 0;
//                            foreach ($user_level_buy as $k => $v) {
//                                //直推有多少人购买该种类的蛋
//                                $id_array = Db::name('user')->field('id')->where(array('pid'=>$user_id))->select();
//                                $ids = array_column($id_array, 'id');
//                                $order_where = array(
//                                    'buy_user_id'=>array('in',$ids),
//                                    'kind_id'=>array('egt',$v['kind_id']),
//                                    'status'=>array('eq',1)
//                                );
//                                $people = Db::name('egg_order')->where($order_where)->count('DISTINCT buy_user_id');
//
//                                if($people < $v['user_number'] ){
//                                    break;
//                                }else{
//                                    $i++;
//                                }
//                            }
//
//                            if($i==$user_level_buy_number){
//                                $level = $val['level'];
//                                break;
//                            }
//                        }else{
//                            $level = $val['level'];
//                            break;
//                        }
                    }
                }
            }
        }
        return $level;
    }

}
