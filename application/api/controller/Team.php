<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;
use think\config;
use think\Cache;
use think\Db;
/**
 * 团队分红接口
 */
class Team extends Api
{
    protected $noNeedLogin = ['share_bonus'];
    protected $noNeedRight = '*';

    /**
     * 农场主固定分红发放
     */
    public function share_bonus()
    {
        $flag = input("flag",1);
        $start_time = strtotime(date("Y-m-d")." 00:30:00");
        $end_time = strtotime(date("Y-m-d")." 04:00:00");
        if($flag){            
            if($start_time > time() || $end_time < time()){
                $this->error("不在有效时间内");
            }
        }

        $wh = [];
        $wh['type']          = 1;
        $wh['createtime']    = ['>',strtotime(date("Y-m-d"))];
        $sinfo = Db::name("egg_score_log")->where($wh)->order("user_id","desc")->find();
        if(!empty($sinfo)){
            $user_id = Cache::get("bonus_user_id")??0;
            $user_id = $user_id>$sinfo['user_id']?$user_id:$sinfo['user_id'];
        }else{
            $user_id = 0;
        }         
        
        $wh = [];
        $wh['id']                = ['>',$user_id];
        $wh['status']            = 'normal';
        $wh['is_attestation']    = 1;
        $wh['level']             = ['>',0];
        $list = Db::name("user")->where($wh)->order("id","asc")->limit(10)->select();

        if(!empty($list)){            
            $config_list = Db::name("bonus_config")->select();
            $egg_name = Db::name("egg_kind")->column('name','id');
            $lv_title = [1=>"一级",2=>"二级",3=>"三级",4=>"四级"];
            $msg = "";
            foreach ($list as $k => $v) 
            {               
                foreach ($config_list as $key => $value) 
                {
                    if($value['level'] == $v['level'])
                    {
                        $is_give = true;
                        $wh = [];
                        $wh['user_id']  = $v['id'];
                        $wh['kind_id']  = $value['kind_id'];
                        $wh['is_close'] = 0;
                        $wh['status']   = 0;
                        $info = Db::name("egg_hatch")->where($wh)->order("is_give","asc")->find();
                        if($info['is_give'] == 1){
                            $wh = [];
                            $wh['user_id'] = $v['id'];
                            $wh['type']    = ['in',[10,11]];
                            $log_type = Db::name("egg_log")->where($wh)->order("createtime desc")->value("type");
                            if($log_type == 11){
                                $flag = false;
                            }
                        }
                        if($info && $is_give){
                            //添加积分发放日志
                            DB::startTrans();
                            $log = [];
                            $log['type']    = 1;
                            $log['user_id'] = $v['id'];
                            $log['kind_id'] = $value['kind_id'];
                            $log['score']   = $value['point'];
                            $log['memo']    = '【'.date("Y-m-d").'】获得'.$egg_name[$value['kind_id']].'分红,等级' . $lv_title[$value['level']] . $value['point'] . '积分';
                            $log['createtime'] = time();
                            $log_rs = Db::name("egg_score_log")->insert($log);

                            $where = [];
                            $where['user_id'] = $v['id'];
                            $where['kind_id'] = $value['kind_id'];
                            $res = Db::name("egg")->where($where)->setInc('point', $value['point']);

                            if($log_rs && $res){
                                echo $v['id']." >> ".$egg_name[$value['kind_id']]." 成功\n";
                                DB::commit();
                            }else{                                
                                echo $v['id']." >> ".$egg_name[$value['kind_id']]." 失败\n";
                                DB::rollback();
                            }            
                        }else{
                            $msg .= $v['id']." >> ".$egg_name[$value['kind_id']]." 窝没孵化\n";
                        }
                    }
                }/*---foreach---*/
                Cache::set("bonus_user_id",$v['id']);
            }/*---foreach---*/
            echo $msg;
        }
        $this->success("分红成功");
    }

    /**
     * 农场主等级更新
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="user_id", type="integer", description="用户id")
     */
//    public function vip(){
//        $user_id  = $this->request->post("user_id",0);
//        //更新农场主等级，$user_id用户id，注意要在积分更新之后调用
//        $userLevelConfig = new \app\common\model\UserLevelConfig();
//        $res = $userLevelConfig ->update_vip($user_id);
//
//        if ($res == true){
//            $this->success("更新成功");
//        }else{
//            $this->error('更新失败');
//        }
//    }

    /**
     * 农场主分红数据
     */
    public function bonus_commission(){

        $statistics_count = Db::name("team_statistics")
            ->where('add_time',date("Y-m-d"))
            ->count();
        if($statistics_count>0){
            exit;
        }

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

        //统计昨天发放的分红奖励总积分
        $fee_rate  = Config::get('site.fee_rate')/100;

        $total_score = 0; //总的手续费积分

        $kind_where = array(
            'id'=>array('lt',4)
        );
        $egg_kind = Db::name("egg_kind")
            ->where($kind_where)
            ->order('id asc')
            ->select();
        if(count($egg_kind)>0){
            DB::startTrans();
            try {
                $statistics_bonus = array();
                $trade_vip = array();
                foreach ($egg_kind as $ki=>$vi ){
                    //蛋数量
                    $fee_where = array(
                        'createtime'=>array('between',[strtotime(date("Y-m-d",strtotime("-1 day"))),strtotime(date("Y-m-d"))]),
                        'type'=>array('eq',9),
                        'kind_id'=>array('eq',$vi['id'])
                    );
                    //$total_number = Db::name("egg_log_".date("Y_m",strtotime("-1 day")))
                    $total_number = Db::name("egg_log")
                        ->where($fee_where)
                        ->sum('number');

                    //蛋总积分
                    $total_score = abs($total_number) * 1;
                    $total_score = bcdiv($total_score, 1, 4);

                    $bonus_score = $total_score * $fee_rate;
                    $bonus_score = bcdiv($bonus_score, 1, 4);//分红奖励总积分

                    if(count($config_bonus)>0 && $bonus_score>0 && $total_user_count>0){
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
                                $total_rate_score = bcdiv($bonus_score * $v['rate'],1,4);//当前农场主等级总奖励
                                $score = bcdiv($total_rate_score/$user_count,1,4);//当前农场主等级人均奖励

                                $statistics = array();
                                $statistics['kind_id'] = $vi['id'];
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
                                $log_vip['kind_id'] = $vi['id'];
                                $log_vip['title'] = $v['title'];
                                $log_vip['lv'] = $v['level'];
                                $log_vip['bonus'] = json_encode($bonus);
                                $log_vip['add_time'] = date("Y-m-d");
                                $log_vip['is_update'] = 0;
                                $log_vip['total_num'] = $user_count;
                                $trade_vip[] = $log_vip;

                            }
                        }
                    }

                }

                $re = Db::name("team_statistics")->insertAll($statistics_bonus);
                $res = Db::name("team_vip")->insertAll($trade_vip);

                if($re==false || $res==false){
                    DB::rollback();
                } else{
                    DB::commit();
                    $this->success("更新成功");
                }
            } catch (\Exception $e) {
                DB::rollback();
                $this->error("bonus_commission:".$e->getMessage());
            }

        }

        $this->success("更新成功");
    }

    /**
     * 农场主分红数据插入
     */
    public function bonus_commission_data(){

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
        if($vip_info){
            $commission_list = json_decode($vip_info['bonus'],true);
            if (count($commission_list)>0) {
                DB::startTrans();
                try {
                    $is_rollback = true;
                    $page_num = $vip_info['num']+20;
                    $i=0;
                    $log_bonus = array();
                    foreach ($commission_list as $k => $v) {
                        $key = $k+1;
                        if($key>$vip_info['num'] && ($key <= $page_num)){
                            $data = array();
                            $where = array(
                                'level'=>array('eq',$vip_info['lv']),
                                'kind_id'=>array('eq',$vip_info['kind_id']),
                                'add_time'=>array('eq',date("Y-m-d"))
                            );
                            $statistics_info = Db::name("team_statistics")
                                ->field('*')
                                ->where($where)
                                ->find();

                            $data['kind_id'] = $statistics_info['kind_id'];
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


                    if($res==false || $re==false){
                        DB::rollback();
                    }else{
                        DB::commit();
                        $this->success("更新成功");
                    }
                } catch (\Exception $e) {
                    DB::rollback();
                    $this->error("bonus_commission:".$e->getMessage());
                }
            }
        }

        $this->success("更新成功");


    }

    /**
     * 农场主分红发放
     */
    public function bonus_commission_issue(){

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
                        $asset_where['kind_id'] = $v['kind_id'];
                        $res = Db::name("egg")->where($asset_where)->inc('point', $v['score'])->update();
                        $re = Db::name("team_bonus")->where(array('id' => $v['id']))->data(array('is_issue' => 1, 'pay_time' => time()))->update();
                        //添加积分发放日志

                        $egg_name = Db::name("egg_kind")
                            ->where('id',$v['kind_id'])
                            ->value('name');

                        $log = [];
                        $log['type'] = 1;
                        $log['user_id'] = $v['user_id'];
                        $log['kind_id'] = $v['kind_id'];
                        $log['score'] = $v['score'];
                        $log['memo'] = '【'.$v['add_time'].'】获得'.$egg_name.'分红,等级'.$v['title'].$v['score'] . '积分';
                        $log['createtime'] = time();
                        $re1 = Db::name("egg_score_log")->insert($log);

                        if($res==false || $re==false || $re1==false){
                            $is_rollback = false;
                        }
                    }
                }
                if( $is_rollback ==false){
                    DB::rollback();
                }else{
                    DB::commit();
                    $this->success("更新成功");
                }
            } catch (\Exception $e) {
                DB::rollback();
                $this->error("bonus_commission:".$e->getMessage());
            }
        }

        $this->success("更新成功");
    }

}
