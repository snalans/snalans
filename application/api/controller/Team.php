<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;
use think\config;
/**
 * 团队分红接口
 */
class Team extends Api
{
    protected $noNeedLogin = ['bonus_commission', 'bonus_commission_data','bonus_commission_issue'];
    protected $noNeedRight = '*';


    /**
     * 农场主分红数据
     */
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
        $fee_rate  = Config::get('site.fee_rate')/100;

        $total_score = 0; //总的手续费积分
        $total_price = 0; //蛋总价
        $white_price = 0; //白蛋收盘价

        $kind_where = array(
            'id'=>array('lt',5)
        );
        $egg_kind = Db::name("egg_kind")
            ->where($kind_where)
            ->order('id asc')
            ->select();

        if(count($egg_kind)>0){
            foreach ($egg_kind as $ki=>$vi ){
                //蛋数量
                $fee_where = array(
                    'createtime'=>array('egt',strtotime(date("Y-m-d",strtotime("-1 day")))),
                    'createtime'=>array('lt',strtotime(date("Y-m-d"))),
                    'type'=>array('eq',9),
                    'kind_id'=>array('eq',$vi['id'])
                );
                $last_day = date("Y-m",strtotime("-1 day"));
                $total_number = Db::name("egg_log_".$last_day)
                    ->where($fee_where)
                    ->sum('number');

                //蛋收盘价
                $hours_where = array(
                    'day'=>array('eq',date("Y-m-d")),
                    'hours'=>array('eq','00-00'),
                    'kind_id'=>array('eq',$vi['id'])
                );
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
        $bonus_where = array(
            'level'=>array('gt',0)
        );
        $config_bonus = Db::name("team_config")
            ->where($bonus_where)
            ->select();
        //农场主等级列表
        $where = array(
            'level'=>array('egt',1),
            'status'=>array('eq','normal'),
            'is_attestation'=>array('eq',1)
        );

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
                    $where = array(
                        'level'=>array('eq',$v['level']),
                        'status'=>array('eq','normal'),
                        'is_attestation'=>array('eq',1)
                    );

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

    /**
     * 农场主分红数据插入
     */
    public function bonus_commission_data(){
        $lock = Cache::get("commission_data");
        if($lock){
            $result['msg'] = "locked";
            return $result;
        }
        Cache::set("commission_data",'locked',1);

        $where = array(
            'is_update'=>array('eq',0),
            'total_num'=>array('gt',0),
            'add_time'=>array('eq',date("Y-m-d"))
        );
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
                        $where = array(
                            'level'=>array('eq',$vip_info['lv']),
                            'add_time'=>array('eq',date("Y-m-d"))
                        );
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

    /**
     * 农场主分红发放
     */
    public function bonus_commission_issue(){
        $lock = Cache::get("commission_issue");
        if($lock){
            $result['msg'] = "locked";
            return $result;
        }
        Cache::set("commission_issue",'locked',1);

        $where = [];
        $where['is_issue'] =  0;
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
                        $asset_where['user_id'] = $v['user_id'];
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
