<?php
namespace app\api\controller\farm;

use app\common\controller\Api;
use think\Log;
use think\Db;

/**
 * 蛋合成接口
 * @ApiWeigh   (27)
 */
class Synthesis extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';


    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 合成蛋
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="kind_id", type="integer", description="合成蛋分类Id")
     * @ApiParams   (name="number", type="integer", description="合成数量")
     * 
     */
    public function operate()
    {
        $kind_id       = $this->request->post("kind_id",0);
        $number        = $this->request->post("number/d",0);

        if(!in_array($kind_id,[2,3,4]) || $number<=0 || $number>20){    
            $this->error("参数错误");
        }
        
        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        $config = Db::name("egg_synthesis_config")->where("kind_id",$kind_id)->select();     
        $egg_list = Db::name("egg")
                    ->where("user_id",$this->auth->id)
                    ->column("number","kind_id");    

        $flag = true;
        foreach ($config as $key => $value) {
            if((intval($value['number'])*intval($number)) > $egg_list[$value['ch_kind_id']]){
                $flag = false;
                break;
            }
        }

        if(!$flag){
            $this->error("合成失败,数量不够,请检查.");
        }
        
        Db::startTrans();
        $dec_rs = true;
        $dec_log = true;
        try {
            foreach ($config as $key => $value) {       
                $dec_num = $value['number']*$number;     
                $wh = [];
                $wh['user_id'] = $this->auth->id;
                $wh['kind_id'] = $value['ch_kind_id'];
                $wh['number']  = ['>=',$dec_num];
                $dec_rs = Db::name("egg")->where($wh)->setDec('number',$dec_num);
                if(!$dec_rs){
                    break;
                }
                $before = $egg_list[$value['ch_kind_id']];
                $dec_log = Db::name("egg_log")->insert(['user_id'=>$this->auth->id,'kind_id'=>$value['ch_kind_id'],'type'=>3,'number'=>-$dec_num,'before'=>$before,'after'=>($before-$dec_num),'note'=>"合成扣减",'createtime'=>time()]);
            }

            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $kind_id;
            $before = Db::name("egg")->where($wh)->value('hatchable');
            $inc_rs = Db::name("egg")->where($wh)->setInc('hatchable',$number); 
            $inc_log = Db::name("egg_log")->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>3,'number'=>$number,'before'=>$before,'after'=>($before+$number),'note'=>"合成获得可孵化的蛋",'createtime'=>time()]);
            if($dec_rs && $dec_log && $inc_rs && $inc_log){
                $this->reward($this->auth->id,$kind_id,$number,$config);
                Db::commit();
            }else{
                Db::rollback();
                $this->error('合成失败!'); 
            }            
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }   
        $this->success('合成成功!'); 
    }

    /**
     * 发放合成奖励
     * @ApiInternal
     */
    public function reward($user_id='',$kind_id='',$num=1,$config=[])
    {
        $pInfo = Db::name("user")->field("pid,serial_number")->where('id',$user_id)->find();
        if(!empty($pInfo['pid'])){
            $pid = $pInfo['pid'];      
            $wh = [];
            $wh['user_id']   = $pid;
            $wh['kind_id']   = $kind_id;
            $wh['status']    = 0;
            $result = Db::name("egg_hatch")->field("id,position")->where($wh)->find();
            if(!empty($result)){                
                foreach ($config as $key => $value) {
                    if($value['per_reward']>0){
                        Db::startTrans();
                        try {
                            $note = "会员编号：".$pInfo['serial_number']."合成奖励";
                            $add_num = $value['number']*$num*$value['per_reward']/100;
                            $wh = [];
                            $wh['user_id'] = $pid;
                            $wh['kind_id'] = $value['ch_kind_id'];
                            $before = Db::name("egg")->where($wh)->value('number');
                            $inc_rs = Db::name("egg")->where($wh)->setInc('number',$add_num); 
                            $inc_log = Db::name("egg_log")->insert(['user_id'=>$pid,'kind_id'=>$value['ch_kind_id'],'type'=>3,'number'=>$add_num,'before'=>$before,'after'=>($before+$add_num),'note'=>$note,'createtime'=>time()]);
                            if($inc_rs && $inc_log){
                                Db::commit();
                            }else{
                                Db::rollback();
                                Log::record('奖励发放失败。'.$note,'reward');
                            }
                        } catch (\Exception $e) {
                            Db::rollback();
                            Log::record($e->getMessage(),'reward');
                        }  
                        Log::record('奖励发放成功。'.$note,'reward');
                    }   
                }            
            }
        }
    }
}