<?php
namespace app\common\model;

use think\Db;
use think\Model;

class MembershipChain extends Common
{
    /**
     *新增会员关系链
     */
    function syn_user_chain($user_id, $pid){

        $ancestral_arr = array();
        if($pid){
            $pid_arr[0] = array(
                'user_id'       => $user_id,
                'ancestral_id'  => $pid,
                'level'         => 1,
            );
            $pid_above_arr = $this->query("select user_id, ancestral_id, `level` from ".config('database.prefix')."membership_chain WHERE ancestral_id != 0 AND user_id = ".$pid);
            foreach ($pid_above_arr as $key => $value){
                $pid_above_arr[$key]['user_id'] = $user_id;
                $pid_above_arr[$key]['level'] = $value['level']+1;
            }
            $ancestral_arr = array_merge($pid_arr, $pid_above_arr);

        }else{
            $ancestral_arr[] = array(
                'user_id'       => $user_id,
                'ancestral_id'  => 0,
                'level'         => 0,
            );

        }

        $this->inserts($ancestral_arr);
//        if(!$GLOBALS['db']->insert_id()){
//            logger::write('用户'.$user_id.'关系链更新失败');
//        }
        $this->query("update ".config('database.prefix')."user set is_pid = 1 where id = ".$user_id);
        return true;
    }


    /**
     *会员注册的时候调用更新会员关系链
     */
    function update_user_chain($user_id, $pid){
        
        $above['user_id'] = $user_id;
        $above['level'] = 0;
        $above['ancestral_id'] = 0;
        $this->insert($above);

        $userid_arr = $this->query("select user_id, ancestral_id, `level` from ".config('database.prefix')."membership_chain WHERE ancestral_id =".$user_id."  order by level asc ");
        $where = [];
        $where[] = ['user_id', 'eq', $user_id];
        $where[] = ['ancestral_id', 'eq', 0];
        $have_ancestral = $this->field('id')->where($where)->find();

        if($have_ancestral['id'] >0){
            $re =$this->syn_user_chain($user_id, $pid);
            if($re){
                if(count($userid_arr)>0){
                    foreach ($userid_arr as $key => $value){
                        $this->syn_user_chain_p($value['user_id'], $value['ancestral_id']);
                    }
                }
            }
            $this->query("delete from " . config('database.prefix') . "membership_chain WHERE ancestral_id = 0 AND user_id =".$user_id);
        }
    }

    /**
     *更新有下级的会员关系链
     */
    function syn_user_chain_p($user_id, $pid){

        $pid_above = array();
        $pid_above_arr = $this->query("select user_id, ancestral_id, `level` from ".config('database.prefix')."membership_chain WHERE ancestral_id != 0 AND user_id = ".$pid);
        foreach ($pid_above_arr as $key => $value){
            $pid_above['user_id'] = $user_id;
            $pid_above['level'] = $value['level']+1;
            $pid_above['ancestral_id'] = $value['ancestral_id'];
            $this->insert($pid_above);
        }
        return true;
    }
    /**
     *统计直推关系链所有会员总数
     */
    function total_num($user_id){
        $total_count = $this->field('user_id')->where('ancestral_id', $user_id)->count();
        return $total_count;
    }
}
