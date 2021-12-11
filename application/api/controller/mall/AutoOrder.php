<?php
namespace app\api\controller\mall;

use app\common\controller\Api;
use think\Validate;
use think\Config;
use think\Db;

/**
 * 商城订单接口
 * @ApiWeigh   (38)
 */
class AutoOrder extends Api
{
    protected $noNeedLogin = "*";
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 自动退款-超时未发货
     *
     */
    public function autoRefund()
    {
        $send_hours = Config::get("site.send_hours");
        if($send_hours > 0){
            $wh = [];
            $wh['status']   = 2;
            $wh['add_time'] = ['<',time()-$send_hours*3600];
            $list = Db::name("mall_order")->where($wh)->order("add_time asc")->limit(300)->select();
            if(!empty($list)){
                foreach ($list as $key => $value) {
                    Db::startTrans();
                    try {
                        $total_amount = $value['total_price']+$value['rate'];
                        $wh = [];
                        $wh['user_id'] = $value['buy_user_id'];
                        $wh['kind_id'] = $value['kind_id'];
                        $before = Db::name("egg")->where($wh)->value('number');
                        $inc_rs = Db::name("egg")->where($wh)->setInc('number',$total_amount);
                        //写入日志
                        $log_rs = Db::name("egg_log")->insert(['user_id'=>$value['buy_user_id'],'kind_id'=>$value['kind_id'],'type'=>1,'order_sn'=>$value['order_sn'],'number'=>$value['total_price'],'before'=>$before,'after'=>($before+$value['total_price']),'note'=>"卖家超时未发货退款",'createtime'=>time()]);

                        $log_re = true;
                        if($value['rate']>0){         
                            //手续费写入日志
                            $log_re = Db::name("egg_log")->insert(['user_id'=>$value['buy_user_id'],'kind_id'=>$value['kind_id'],'type'=>9,'order_sn'=>$value['order_sn'],'number'=>$value['rate'],'before'=>($before+$value['total_price']),'after'=>($before+$total_amount),'note'=>"卖家超时未发货退款,返还手续费",'createtime'=>time()]);
                        }
                        $rs = Db::name("mall_order")->where("id",$value['id'])->update(["status"=>6]);
                        if($rs && $inc_rs && $log_rs && $log_re){
                            Db::commit();
                        }else{
                            Db::rollback();
                            $this->error("超时未付款-退款失败",$value['order_sn']);
                        }
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success("超时未付款-退款成功",$value['order_sn']);
                }
            }
        }        
    }

    /**
     * 自动确认-超时未签收
     *
     */
    public function autoSure()
    {
        $voer_hours = Config::get("site.voer_hours");
        if($voer_hours > 0){
            $wh = [];
            $wh['status']    = 3;
            $wh['send_time'] = ['<',time()-$voer_hours*3600];
            $list = Db::name("mall_order")->where($wh)->order("send_time asc")->limit(300)->select();
            if(!empty($list)){
                foreach ($list as $key => $value) {
                    Db::startTrans();
                    try {
                        $wh = [];
                        $wh['user_id'] = $value['sell_user_id'];
                        $wh['kind_id'] = $value['kind_id'];
                        $before = Db::name("egg")->where($wh)->value('number');
                        $inc_rs = Db::name("egg")->where($wh)->setInc('number',$value['total_price']);
                        //写入日志
                        $log_rs = Db::name("egg_log")->insert(['user_id'=>$value['sell_user_id'],'kind_id'=>$value['kind_id'],'type'=>1,'order_sn'=>$value['order_sn'],'number'=>$value['total_price'],'before'=>$before,'after'=>($before+$value['total_price']),'note'=>"商城订单交易完成",'createtime'=>time()]);

                        $rs = Db::name("mall_order")->where("id",$value['id'])->update(["status"=>1,"received_time"=>time()]);
                        if($inc_rs && $log_rs && $rs){
                            Db::commit();
                        }else{
                            Db::rollback();
                            $this->error("超时未确认收获-确认收获失败",$value['order_sn']);
                        }
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success("超时未确认收获-确认收获成功",$value['order_sn']);
                }
            }
        }        
    }
}