<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;
use think\config;

class Team extends Api
{
    /*
     * 变更链农场主等级,农场主等级只升不降
     * $user_id 用户id
     * $valid_number 有效值
     * $level 农场主等级
     */
    public function update_vip($user_id){
        $user_info = Db::name("user")
            ->where(['id'=>$user_id])
            ->find('id,valid_number,level');
        if($user_info){
            //团队数量
            $team_num = Db::name("membership_chain")->where('ancestral_id', $user_id)->count();

            //直推数量
            $p_num = Db::name("user")->where('pid', $user_id)->count();

            //农场主等级
            $level = $this->vip($user_id,$user_info['level'],$p_num,$team_num,$user_info['valid_number']);

            if($level!=$user_info['level'] && ($level > $user_info['level'])){
                $re = Db::name("user")
                    ->where(['id'=>$user_id])
                    ->data(array('level'=>$level))
                    ->update();
                if($re==true){
                    //赠送蛋
                    $egg_give = Db::name("egg_give")
                        ->field('*')
                        ->where(['user_level_config_id'=>$level])
                        ->select();
                    if(count($egg_give)>0){
                        foreach ($egg_give as $k=>$v){
                            //增加蛋
                            $wh = [];
                            $wh[] = ['user_id', 'eq', $user_id];
                            $wh[] = ['kind_id', 'eq', $v['kind_id']];
                            $add_rs = Db::name("egg")->where($wh)->inc('number',$v['number'])->update();;

                            //蛋日志
                            $log_add = \app\admin\model\egg\Log::saveLog($user_id,$v['kind_id'],10,1,"农场主等级升级到".$level."级赠送");
                        }
                    }

                    //赠送窝
                    $egg_nest_give = Db::name("egg_nest_give")
                        ->field('*')
                        ->where(['user_level_config_id'=>$level])
                        ->select();
                    if(count($egg_nest_give)>0){
                        foreach ($egg_nest_give as $kk=>$vv) {
                            //增加窝
                            $nest_where = [];
                            $nest_where[] = ['user_id', 'eq', $user_id];
                            $nest_where[] = ['nest_kind_id', 'eq', $vv['nest_kind_id']];
                            $add_rs = Db::name("egg_nest")->where($nest_where)->inc('number', $vv['number']);

                            //窝日志
                            $data = [];
                            $data['user_id'] = $user_id;
                            $data['nest_kind_id'] = $vv['nest_kind_id'];
                            $data['type'] = 0;
                            $data['number'] = $vv['number'];
                            $data['note'] = "农场主等级升级到".$level."级赠送";
                            $data['createtime'] = time();
                            $rs = Db::name("egg_log")->insert($data);
                        }
                    }
                }
            }else{
                return true;
            }
        }else{
            return true;
        }

    }

    /*
     * 输出用户农场主等级
     * $user_id 用户id
     * $level 用户目前等级
     * $p_num 直推数量
     * $team_num 团队数量
     * $valid_number 有效值
     */
    public function vip($user_id,$level,$p_num = 0,$team_num = 0,$valid_number){
        $where = [];
        $where[] = ['level','gt',$level];
        $config_bonus = Db::name("user_level_config")
            ->order('level desc')
            ->select();
        if(count($config_bonus)>0){
            foreach ($config_bonus as $ke => $val) {
                if($p_num>=$val['number'] && $team_num>=$val['team_number'] && $valid_number>=$val['valid_number']){
                    //直推农场主数量
                    $u_where = [];
                    $u_where[] = ['level', 'egt', $val['level']];
                    $u_where[] = ['pid', 'eq', $user_id];
                    $u_where[] = ['status','eq','normal'];
                    $u_where[] = ['is_attestation','eq',1];
                    $user_number = Db::name("user")->where($u_where)->count();
                    if ($val['user_number']>=$user_number){
                        //会员等级直推蛋购买
                        $user_level_buy = Db::name("user_level_buy")->where(['user_level_config_id'=>$val['id']])->select();
                        $user_level_buy_number = Db::name("user_level_buy")->where(['user_level_config_id'=>$val['id']])->count();
                        if(count($user_level_buy)>0){
                            $i = 0;
                            foreach ($user_level_buy as $k => $v) {
                                //直推有多少人购买该种类的蛋
                                $id_array = Db::name('user')->field('id')->where(array('pid'=>$user_id))->select();
                                $ids = array_column($id_array, 'id');
                                $order_where = [];
                                $order_where[] = ['buy_user_id', 'in', $ids];
                                $order_where[] = ['kind_id', 'eq', $v['kind_id']];
                                $order_where[] = ['status', 'eq', 1];
                                $people = Db::name('egg_order')->where($order_where)->count('DISTINCT buy_user_id');

                                if($people < $v['user_number'] ){
                                    break;
                                }else{
                                    $i++;
                                }
                            }
                            if($i==$user_level_buy_number){
                                $level = $val['level'];
                                break;                            }
                        }else{
                            $level = $val['level'];
                            break;
                        }
                    }
                }
            }
        }
        return $level;
    }

    //农场主分红数据
    public function bonus_commission(){

        $where = [];
        $where[] = ['add_time','eq',date("Y-m-d")];
        $statistics_count = Db::name("team_statistics")
            ->where($where)
            ->count();
        if($statistics_count>0){
            return true;
        }

        //统计昨天发放的分红奖励总积分
        $fee_rate  = Config::get('site_rate_config');

        $total_score = 0; //总的手续费积分
        $total_price = 0; //蛋总价
        $white_price = 0; //白蛋收盘价

        $kind_where = [];
        $kind_where[] = ['id', 'lt', 5];
        $egg_kind = Db::name("egg_kind")
            ->where($kind_where)
            ->order('id asc')
            ->select();

        if(count($egg_kind)>0){
            foreach ($egg_kind as $ki=>$vi ){
                //蛋数量
                $fee_where = [];
                $fee_where[] = ['createtime', 'egt', strtotime(date("Y-m-d",strtotime("-1 day")))];
                $fee_where[] = ['createtime', 'lt', strtotime(date("Y-m-d"))];
                $fee_where[] = ['type', 'eq', 9];
                $fee_where[] = ['kind_id', 'eq', $vi['id']];
                $total_number = Db::name("egg_log")
                    ->where($fee_where)
                    ->sum('number');

                //蛋收盘价
                $hours_where = [];
                $hours_where[] = ['kind_id', 'eq', $vi['id']];
                $hours_where[] = ['day', 'eq', date("Y-m-d")];
                $hours_where[] = ['hours', 'eq', '00-00'];
                $price = Db::name("egg_hours_price")
                    ->where($hours_where)
                    ->value('price');

                //白蛋收盘价
                if($vi==1){
                    $white_price = $price;
                }
               $total_price = $total_price + $total_number * $price;
            }
            $total_score = intval($total_price/$white_price * 10) ; //总的手续费积分
        }

        $bonus_score = $total_score * $fee_rate;//分红奖励总积分

        //农场主等级分红配置
        $bonus_where = [];
        $bonus_where[] = ['level', 'gt', 0];
        $config_bonus = Db::name("team_config")
            ->where($bonus_where)
            ->select();
        //农场主等级列表
        $where = [];
        $where[] = ['level','egt',1];
        $where[] = ['status','eq','normal'];
        $where[] = ['is_attestation','eq',1];
        $total_user_count =Db::name("user")
            ->field('id,level')
            ->where($where)
            ->count();

        $lock = Cache::get("bonus_commission");
        if($lock){
            $result['msg'] = "locked";
            return $result;
        }
        Cache::set("bonus_commission",'locked',1);

        if(count($config_bonus)>0 && $bonus_score>0 && $total_user_count>0){
            DB::startTrans();
            try {
                $statistics_bonus = array();
                $trade_vip = array();
                foreach($config_bonus as $k=>$v) {
                    $where = [];
                    $where[] = ['level','eq',$v['level']];
                    $where[] = ['status','eq','normal'];
                    $where[] = ['is_attestation','eq',1];
                    $user_list = Db::name("user")
                        ->field('id,level')
                        ->where($where)
                        ->select();
                    $user_count = Db::name("user")
                        ->where($where)
                        ->count();

                    if($user_count>0){
                        $bonus = array();
                        $total_rate_score = $bonus_score * $v['rate'];//当前农场主等级总奖励
                        $score = $total_rate_score/$user_count;//当前农场主等级人均奖励

                        $statistics = array();
                        $statistics['fee_rate'] = $fee_rate;
                        $statistics['total_score'] = $total_score;
                        $statistics['last_time'] = date("Y-m-d",strtotime("-1 day"));
                        $statistics['add_time'] = date("Y-m-d",time());
                        $statistics['title'] = $v['title'];
                        $statistics['level'] = $v['level'];
                        $statistics['bonus_score'] = $bonus_score;
                        $statistics['total_number'] = $user_count;
                        $statistics['score'] = $score;
                        $statistics['createtime'] = time();
                        $statistics['team_rate'] = $v['rate'];
                        $statistics['team_score'] = $total_rate_score;

                        $statistics_bonus[] = $statistics;

                        $data = array();
                        foreach($user_list as $kk=>$vv) {
                            //插入数组
                            $data['user_id'] = $vv['id'];
                            $bonus[] = $data;
                        }
                        $log_vip = array();
                        $log_vip['title'] = $v['title'];
                        $log_vip['lv'] = $v['lv'];
                        $log_vip['bonus'] = json_encode($bonus);
                        $log_vip['add_time'] = date("Y-m-d");
                        $log_vip['is_update'] = 0;
                        $log_vip['total_num'] = $user_count;
                        $trade_vip[] = $log_vip;
                    }
                }
                $re = Db::name("team_statistics")->insertAll($statistics_bonus);
                $res = Db::name("team_vip")->insertAll($trade_vip);
//                if($re==false){
//                    echo 1;
//                }
//                if($res==false){
//                    echo 2;
//                }
                if($re==false || $res==false){
                    DB::rollback();
                } else{
                    DB::commit();
                    $result['status'] = true;
                }
                Cache::rm("bonus_commission");
                return $result;
            } catch (\Exception $e) {
                DB::rollback();
                Cache::rm("bonus_commission");
                $result['msg'] = "bonus_commission:".$e->getMessage();
                return $result;
            }
        }else{
            Cache::rm("bonus_commission");
        }
    }

    //农场主分红数据插入
    public function bonus_commission_data(){
        $lock = Cache::get("commission_data");
        if($lock){
            $result['msg'] = "locked";
            return $result;
        }
        Cache::set("commission_data",'locked',1);

        $where = [];
        $where[] = ['is_update', 'eq', 0];
        $where[] = ['total_num', 'gt', 0];
        $where[] = ['add_time', 'eq', date("Y-m-d")];
        $vip_info = Db::name("team_vip")
            ->field('*')
            ->where($where)
            ->order('lv asc')
            ->find();

        $commission_list = json_decode($vip_info['bonus'],true);
        if (count($commission_list)>0) {
            DB::startTrans();
            try {
                $is_rollback = true;
                $page_num = $vip_info['num']+2;
                $i=0;
                $log_bonus = array();
                foreach ($commission_list as $k => $v) {
                    $key = $k+1;
                    if($key>$vip_info['num'] && ($key <= $page_num)){
                        $data = array();
                        $where = [];
                        $where[] = ['level','eq',$vip_info['lv']];
                        $where[] = ['add_time','eq',date("Y-m-d")];
                        $statistics_info = Db::name("team_statistics")
                            ->field('*')
                            ->where($where)
                            ->find();

                        $data['user_id'] = $v['user_id'];
                        $data['title'] = $statistics_info['title'];
                        $data['level'] = $statistics_info['level'];
                        $data['bonus_score'] = $statistics_info['bonus_score'];
                        $data['total_number'] = $statistics_info['total_number'];
                        $data['score'] = $statistics_info['score'];
                        $data['last_time'] = $statistics_info['last_time'];
                        $data['add_time'] = $statistics_info['add_time'];
                        $data['createtime'] = time();
                        $data['team_rate'] = $statistics_info['team_rate'];
                        $data['team_score'] = $statistics_info['team_score'];
                        $data['is_issue'] = 0;
                        $log_bonus[]=$data;

                        $i = $key;
                    }
                    if($key>$page_num){
                        break;
                    }
                }
                $res = Db::name("team_bonus")->insertAll($log_bonus);
                $re = false;
                if($res==true){
                    if($i>=$vip_info['total_num']){
                        //全部执行完更新状态
                        $re = Db::name("team_vip")->where(array('id'=>$vip_info['id']))->inc('add_num',count($log_bonus))->data(array('is_update'=>1,'num'=>$i))->update();
                    }else{
                        $re = Db::name("team_vip")->where(array('id'=>$vip_info['id']))->inc('add_num',count($log_bonus))->data(array('num'=>$i))->update();
                    }
                }

//                if($res==false){
//                    echo 1;
//                }
//                if($re==false){
//                    echo 2;
//                }

                if($res==false || $re==false){
                    DB::rollback();
                }else{
                    DB::commit();
                    $result['status'] = true;
                }
                Cache::rm("commission_data");
                return $result;
            } catch (\Exception $e) {
                DB::rollback();
                Cache::rm("commission_data");
                $result['status'] = false;
                $result['msg'] = $e->getMessage();
                return $result;
            }
        }else{
            Cache::rm("commission_data");
        }

    }

    //农场主分红发放
    public function bonus_commission_issue(){
        $lock = Cache::get("commission_issue");
        if($lock){
            $result['msg'] = "locked";
            return $result;
        }
        Cache::set("commission_issue",'locked',1);
        $where = [];
        $where[] = ['is_issue', 'eq', 0];
        //$where[] = ['money', 'gt', 0];
        $commission_list = Db::name("team_bonus")
            ->field('*')
            ->where($where)
            ->order('id asc')
            ->limit(50)
            ->select();
        if (count($commission_list)>0) {
            DB::startTrans();
            try {
                $is_rollback = true;
                foreach ($commission_list as $k => $v) {
                    if($v['score']>0){
                        $asset_where = [];
                        $asset_where[] = ['user_id', 'eq', $v['user_id']];
                        $before_score = Db::name("user")->where($asset_where)->value('score'); //变更前积分
                        $res = Db::name("user")->where($asset_where)->inc('score', $v['score'])->update();
                        $re = Db::name("team_bonus")->where(array('id' => $v['id']))->data(array('is_issue' => 1, 'pay_time' => time()))->update();
                        $after_score = Db::name("user")->where($asset_where)->value('score');//变更后积分
                        //添加积分发放日志
                        $log = [];
                        $log['user_id'] = $v['user_id'];
                        $log['score'] = $v['score'];
                        $log['memo'] = '会员'.$v['user_id'].'【'.$v['add_time'].'】获得'.$v['title'].'分红' . $v['score'] . '积分';
                        $log['createtime'] = time();
                        $log['before'] = $before_score;
                        $log['after'] = $after_score;
                        $re1 = Db::name("user_score_log")->insert($log);

                        if($res==false || $re==false || $re1==false){
                            $is_rollback = false;
                        }
                    }
                }
                if( $is_rollback ==false){
                    DB::rollback();
                }else{
                    DB::commit();
                    $result['status'] = true;
                }
                Cache::rm("commission_issue");
                return $result;
            } catch (\Exception $e) {
                DB::rollback();
                Cache::rm("commission_issue");
                $result['status'] = false;
                $result['msg'] = $e->getMessage();
                return $result;
            }
        }else{
            Cache::rm("commission_issue");
        }
    }

}
