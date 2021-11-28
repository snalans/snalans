<?php
namespace app\api\controller\farm;

use app\common\controller\Api;
use think\Config;
use think\Db;

/**
 * 农场接口
 * @ApiWeigh   (29)
 */
class Index extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    public    $alldate = 3600*24;   //签到周期

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 农场数据
     *
     * @ApiMethod (GET)
     * @ApiReturnParams   (name="kind_id", type="integer", description="名称id")
     * @ApiReturnParams   (name="name", type="string", description="名称")
     * @ApiReturnParams   (name="number", type="integer", description="蛋数量")
     * @ApiReturnParams   (name="hatchable", type="integer", description="可孵化数量")
     * @ApiReturnParams   (name="point", type="integer", description="蛋积分")
     * 
     * @ApiReturnParams   (name="id", type="string", description="窝ID")
     * @ApiReturnParams   (name="name", type="string", description="窝名称")
     * @ApiReturnParams   (name="status", type="integer", description="状态 1=空窝 0=孵化中")
     * @ApiReturnParams   (name="hatch_num", type="integer", description="孵化进度-天")
     * @ApiReturnParams   (name="surplus", type="integer", description="剩余时间")
     * @ApiReturnParams   (name="position", type="string", description="窝位置")
     * @ApiReturnParams   (name="shape", type="integer", description="形态 0=蛋 2=鸡")
     * @ApiReturnParams   (name="is_reap", type="integer", description="是否获得 0=否 1=收获")
     * @ApiReturnParams   (name="seconds", type="integer", description="剩余时间（秒）")
     */
    public function index()
    {
        $egg_list = Db::name("egg")->alias("e")
                    ->field("e.kind_id,ek.name,e.number,e.hatchable,e.point")
                    ->join("egg_kind ek","ek.id=e.kind_id","LEFT")
                    ->where("e.user_id",$this->auth->id)
                    ->order("ek.weigh","DESC")
                    ->select();           
        if(!empty($egg_list)){
            foreach ($egg_list as $k => $val) {
                if($val['kind_id']==1){
                    $egg_list[$k]['hatchable'] = intval($val['number']);
                }
            }
        }
        $data['egg_list'] = $egg_list;

        $nest_list = Db::name("egg_hatch")->alias("eh")
                    ->join("egg_nest_kind en","en.id=eh.nest_kind_id","LEFT")
                    ->field("eh.id,en.name,eh.kind_id,eh.status,eh.hatch_num,eh.position,eh.shape,eh.is_reap,eh.uptime")
                    ->where("eh.user_id",$this->auth->id)
                    ->order("en.kind_id","ASC")
                    ->order("eh.position","ASC")
                    ->select();
        $config = Db::name("egg_hatch_config")->column("grow_cycle","kind_id");
        if(!empty($nest_list)){            
            foreach ($nest_list as $key => $value) {
                $surplus = "";
                if($value['status']==0){
                    $date = $config[$value['kind_id']] - $value['hatch_num'];
                    $hours = 24-intval((time()-$value['uptime'])/3600);
                    $surplus = $date."天".($hours>0?$hours:0)."小时";
                }
                $nest_list[$key]['surplus'] = $surplus;
                if($value['is_reap'] == 1){
                    $nest_list[$key]['is_reap'] = time() > ($value['uptime']+$this->alldate)?1:0;
                }
                if($value['shape'] == 1){
                    $nest_list[$key]['shape'] = time() > ($value['uptime']+$this->alldate)?0:2;
                }
                $seconds = $value['uptime'] + $this->alldate - time();
                $nest_list[$key]['seconds'] = $seconds<=0 ? 0 : $seconds;
            }
        }
        $data['nest_list'] = $nest_list;
        $this->success('',$data);
    }

    /**
     * 孵化与喂养
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="egg_hatch_id", type="string", description="窝ID")
     * 
     */
    public function hatchFeed()
    {
        $egg_hatch_id  = $this->request->post('egg_hatch_id');

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        $wh = [];
        $wh['id']       = $egg_hatch_id;
        $wh['user_id']  = $this->auth->id;
        $result = Db::name("egg_hatch")->where($wh)->find();
        if(empty($result)){
            $this->error(__('The nest does not exist, please try again'));
        }

        // 执行加蛋孵化
        if($result['status']==1){
            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $result['kind_id'];
            $total = Db::name("egg")->field("kind_id,number,frozen,hatchable")->where($wh)->find();
            if(($total['kind_id'] == 1 && $total['number'] > 0) || (in_array($total['kind_id'],[2,3,4]) && $total['hatchable'] > 0)){
                $this->hatchEgg($egg_hatch_id,$total);
            }else{
                $this->error(__('Insufficient quantity'));
            }
        }else{
            $this->checkCycle($result);
        }
    }

    /**
     * 判断周期,进行孵化与喂养
     * @ApiInternal
     */
    public function checkCycle($egg=[])
    {        
        $result = Db::name("egg_hatch_config")->where("kind_id",$egg['kind_id'])->find();
        if(time() >= ($egg['uptime'] + $this->alldate)){            
            $data = [];
            $data['hatch_num']  = $egg['hatch_num']+1;
            $data['uptime']     = time();
            if($egg['shape'] == 0){
                if($egg['hatch_num'] >= $result['hatch_cycle']){
                    $data['shape']      = 1;
                    $data['is_reap']    = 1;
                }
                $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
                if($rs){
                    $this->success(__('Successful incubation'),['seconds'=>$this->alldate]); 
                }else{
                    $this->error(__('Incubation failed, please try again')); 
                }
            }else {                
                if($egg['shape'] == 1){
                    $data['shape']  = 2;
                }

                if($egg['is_reap'] == 1 || true){
                    $add_number = 1/$result['raw_cycle'];
                    // ========5天后去除============
                    $wh = [];
                    $wh['user_id']  = $this->auth->id;
                    $wh['type']     = 0;
                    $wh['hatch_id'] = $egg['id'];
                    $wh['number']   = ['<>',1];
                    $wh['note']     = '喂养获得';
                    $info = Db::name("egg_log_".date("Y_m"))->where($wh)->find();
                    if(empty($info)){
                        $cy = ($egg['hatch_num']-$result['hatch_cycle'])%$result['raw_cycle']*$add_number;
                        $add_number = $cy==0?1:$cy;
                    }
                    // ========5天后去除============
                    Db::startTrans();    
                    $flag = false;
                    $kind_id = $egg['kind_id'] == 4?5:$egg['kind_id'];
                    $wh = [];
                    $wh['user_id'] = $this->auth->id;
                    $wh['kind_id'] = $kind_id;
                    $before = Db::name("egg")->where($wh)->value('number');
                    $inc_number = Db::name("egg")->where($wh)->setInc("number",$add_number);
                    $add_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'hatch_id'=>$egg['id'],'type'=>0,'number'=>$add_number,'before'=>$before,'after'=>($before+$add_number),'note'=>"喂养获得",'createtime'=>time()]);

                    if($data['hatch_num'] > $result['grow_cycle']){
                        $data['hatch_num']  = 0;
                        $data['shape']      = 5;
                        $data['is_reap']    = 0;
                        $data['status']     = 1;
                        $flag = true;
                    }
                    $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
                    if($inc_number && $add_log && $rs){
                        Db::commit();
                        $this->success($flag?__('Feeding finished'):__('Harvest success'),['seconds'=>$this->alldate]); 
                    }else{
                        Db::rollback();
                        $this->error(__('Harvest and feeding failed, please try again')); 
                    }                    
                }else{                    
                    $reap = $data['hatch_num']-$result['hatch_cycle'];
                    if($reap >= 0){
                        $data['is_reap'] = 1;
                    }
                    $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
                    if($rs){
                        $this->success(__('Feeding success'),['seconds'=>$this->alldate]); 
                    }else{
                        $this->error(__('Feeding failed, please try again')); 
                    }
                }
            }
        }else{
            $seconds = $egg['uptime'] + $this->alldate - time();
            $this->error($egg['shape']==0?__('Already in incubation'):__('Already fed'),['seconds'=>$seconds<=0?0:$seconds]); 
        }
    }

    /**
     * 添蛋孵化
     * @ApiInternal
     */
    public function hatchEgg($egg_hatch_id=0,$result=[])
    {        
        Db::startTrans();
        $wh = [];
        $wh['user_id']      = $this->auth->id;
        $wh['kind_id']      = $result['kind_id'];
        $assets = Db::name("egg")->where($wh)->find();
        if($result['kind_id'] == 1){
            $wh['number']    = ['>',0];
            $reduce_rs = Db::name("egg")->where($wh)->setDec('number');
            $before = $assets['number'];
            $after = $assets['number']-1;
        }else{            
            $wh['hatchable']    = ['>',0];
            $reduce_rs = Db::name("egg")->where($wh)->setDec('hatchable');
            $before = $assets['hatchable'];
            $after = $assets['hatchable']-1;
        }
        if($result['frozen'] > 0){
            $wh = [];
            $wh['user_id']      = $this->auth->id;
            $wh['kind_id']      = $result['kind_id'];
            $wh['frozen']       = ['>',0];
            Db::name("egg")->where($wh)->setDec('frozen');
        }
        //写入日志
        $reduce_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$result['kind_id'],'hatch_id'=>$egg_hatch_id,'type'=>0,'number'=>-1,'before'=>$before,'after'=>$after,'note'=>"农场进行孵化",'createtime'=>time()]);
        $data = [];
        $data['status']     = 0;
        $data['hatch_num']  = 1;
        $data['shape']      = 0;
        $data['is_reap']    = 0;
        $data['uptime']     = time();
        $data['createtime'] = time();
        $hatch_rs = Db::name("egg_hatch")->where("id",$egg_hatch_id)->update($data);
        if($result['frozen'] <= 0)
        {
            $valid_number = Db::name("egg_kind")->where("id",$result['kind_id'])->value("valid_number");
            if($valid_number>0){
                //更新农场主等级，$user_id用户id，注意要在积分更新之后调用
                $v_rs = Db::name("user")->where("id",$this->auth->id)->setInc('valid_number',$valid_number);
                $v_log = Db::name("egg_valid_number_log")->insert(['user_id'=>$this->auth->id,'origin_user_id'=>$this->auth->id,'number'=>$valid_number,'add_time'=>time()]);
                if($v_rs){                    
                    $userLevelConfig = new \app\common\model\UserLevelConfig();
                    $userLevelConfig->update_vip($this->auth->id);
                    //上级发放有效值
                    $wh = [];
                    $wh['user_id'] = $this->auth->id;
                    $wh['level']   = ['<=',3];
                    $plist = Db::name("membership_chain")->where($wh)->order("level","ASC")->select();
                    if(!empty($plist)){
                        foreach ($plist as $key => $value) {                         
                            $userLevelConfig->update_vip($value['ancestral_id']);
                        }
                    }
                }
            }
        }
        if($reduce_rs && $reduce_log && $hatch_rs){
            Db::commit();
            $this->success(__('Eggs were added to hatch successfully'),['seconds'=>$this->alldate]);
        }else{
            Db::rollback();
            $this->error(__('Failed to add eggs for hatching, please try again'));
        }  
    } 

    /**
     * 转账
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="serial_number", type="string", description="会员编号")
     * @ApiParams   (name="kind_id", type="string", description="蛋分类id")
     * @ApiParams   (name="number", type="string", description="数量")
     * @ApiParams   (name="paypwd", type="string", description="支付密码")
     * 
     */
    public function giveEgg()
    {
        $serial_number  = $this->request->post('serial_number');
        $kind_id        = $this->request->post('kind_id');
        $number         = $this->request->post('number/d',0);
        $paypwd         = $this->request->post('paypwd');

        if(!in_array($kind_id,[1,2,3,4]) || $number<=0){
            $this->error("参数错误");
        }

        $wh = [];
        $wh['serial_number']    = $serial_number;
        $wh['status']           = 'normal';
        $wh['is_attestation']   = 1;
        $user_id = Db::name("user")->where($wh)->value("id");
        if(empty($user_id)){
            $this->error("账号无效或者未认证");
        }
        if($user_id == $this->auth->id){
            $this->error("不允许自己给自己转账");
        }

        $auth = new \app\common\library\Auth();
        if ($this->auth->paypwd != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error(__('Paypwd is incorrect'));
        }

        $rate_config = Db::name("egg_kind")->where("id",$kind_id)->value("rate_config");
        $rate = ceil($number/10)*$rate_config;

        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['kind_id'] = $kind_id;
        $total = Db::name("egg")->where($wh)->value('number');
        if($total < ($number + $rate)){
            $this->error('数量不够,转账失败!');
        }

        Db::startTrans();
        try {
            $wh['number']  = ['>=',($number+$rate)];
            $dec_rs = Db::name("egg")->where($wh)->setDec('number',($number+$rate));
            //写入日志
            $after = $total-$number;
            $dec_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>2,'order_sn'=>'','number'=>-$number,'before'=>$total,'after'=>$after,'note'=>"转账给用户编号：".$serial_number." 减少",'createtime'=>time()]);
            $rate_rs = true;    
            if($rate>0){
                //写入日志
                $rate_rs = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>9,'number'=>-$rate,'before'=>$after,'after'=>($after-$rate),'note'=>"转账给用户编号：".$serial_number." 手续费",'createtime'=>time()]);
            }

            $wh = [];
            $wh['user_id'] = $user_id;
            $wh['kind_id'] = $kind_id;
            $before = Db::name("egg")->where($wh)->value('number');
            $inc_rs = Db::name("egg")->where($wh)->setInc('number',$number);
            //写入日志
            $inc_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$user_id,'kind_id'=>$kind_id,'type'=>2,'number'=>$number,'before'=>$before,'after'=>($before-$number),'note'=>"用户编号：".$this->auth->serial_number." 转账获得",'createtime'=>time()]);
            if($dec_rs && $dec_log && $rate_rs && $inc_rs && $inc_log){
                Db::commit();
            }else{
                Db::rollback();
                $this->error(__('Transfer failed, please try again'));
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } 
        $this->success(__('Transfer succeeded'));
    }

}
