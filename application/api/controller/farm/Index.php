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
    public    $alldate = 30;   //签到周期

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
     * 
     * @ApiReturnParams   (name="id", type="string", description="窝ID")
     * @ApiReturnParams   (name="name", type="string", description="窝名称")
     * @ApiReturnParams   (name="status", type="integer", description='状态 1=完成-提示可加蛋 0=孵化中')
     * @ApiReturnParams   (name="hatch_num", type="integer", description='孵化进度-天')
     * @ApiReturnParams   (name="position", type="string", description="审核备注")
     * @ApiReturnParams   (name="shape", type="integer", description="形态 0=蛋 2=鸡")
     * @ApiReturnParams   (name="is_reap", type="integer", description="是否获得 0=否 1=收获")
     */
    public function index()
    {
        $data['egg_list'] = Db::name("egg")->alias("e")
                    ->field("e.kind_id,ek.name,e.number")
                    ->join("egg_kind ek","ek.id=e.kind_id","LEFT")
                    ->where("e.user_id",$this->auth->id)
                    ->order("ek.weigh","DESC")
                    ->select();
        $nest_list = Db::name("egg_hatch")->alias("eh")
                    ->join("egg_nest_kind en","en.id=eh.nest_kind_id","LEFT")
                    ->field("eh.id,en.name,eh.status,eh.hatch_num,eh.position,eh.shape,eh.is_reap,eh.uptime")
                    ->where("eh.user_id",$this->auth->id)
                    ->order("en.kind_id","ASC")
                    ->order("eh.position","ASC")
                    ->select();
        if(!empty($nest_list)){            
            foreach ($nest_list as $key => $value) {
                if($value['is_reap'] == 1){
                    $nest_list[$key]['is_reap'] = time() > ($value['uptime']+$this->alldate)?1:0;
                }
                if($value['shape'] == 1){
                    $nest_list[$key]['shape'] = time() > ($value['uptime']+$this->alldate)?0:2;
                }
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
            $total = Db::name("egg")->field("kind_id,number,frozen")->where($wh)->find();
            if($total['number'] <= 0){
                $this->error(__('Insufficient quantity'));
            }else{
                $this->hatchEgg($egg_hatch_id,$total);
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
        Db::transaction(function() use($egg){
            $result = Db::name("egg_hatch_config")->where("kind_id",$egg['kind_id'])->find();
            $msg = '';
            $rs = true;
            $rs_number = true;
            $rs_egg_log = true;
            if(time() >= ($egg['uptime'] + $this->alldate)){
                $data = [];
                $data['hatch_num']  = $egg['hatch_num']+1;
                $data['uptime']     = time();
                if($egg['shape'] == 0){
                    if($egg['hatch_num'] >= $result['hatch_cycle']){
                        $data['shape']  = 1;
                    }
                }else {
                    if($egg['is_reap'] == 1){
                        $kind_id = $egg['kind_id'] == 4?5:$egg['kind_id'];
                        $wh = [];
                        $wh['user_id'] = $this->auth->id;
                        $wh['kind_id'] = $kind_id;
                        $rs_number = Db::name("egg")->where($wh)->setInc('number');
                        $rs_egg_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>0,'order_sn'=>'','number'=>1,'note'=>"喂养获得",'createtime'=>time()]);
                        $data['is_reap'] = 0;
                    }else{                    
                        $reap = $data['hatch_num']-$result['hatch_cycle']-$result['raw_cycle'];
                        if($reap >= 0 && ($reap % $result['raw_cycle']) == 0){
                            $data['is_reap'] = 1;
                        }
                    }
                    if($egg['shape'] == 1){
                        $data['shape']  = 2;
                    }
                }
                if($data['hatch_num'] > $result['grow_cycle']){
                    $data['status']     = 1;
                    $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
                    $msg = __('Feeding finished');
                }else{                
                    $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
                }
                $msg = $rs?__('Successful hatching'):__('Incubation failed, please try again');
            }else{
                $msg = __('Already hatching');
            }
            
            if($rs && $rs_number && $rs_egg_log){
                Db::commit();
                $this->success($msg); 
            }else{
                Db::rollback();
                $this->error($msg);
            }
        });

    }

    /**
     * 添蛋孵化
     * @ApiInternal
     */
    public function hatchEgg($egg_hatch_id=0,$result=[])
    {        
        Db::transaction(function() use($egg_hatch_id,$result){
            $rs_frozen = true;
            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $result['kind_id'];
            $reduce_rs = Db::name("egg")->where($wh)->setDec('number');
            if($result['kind_id'] == 1 && $result['frozen'] > 0){
                $rs_frozen = Db::name("egg")->where($wh)->setDec('frozen');
            }
            //写入日志
            $rs_egg_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$result['kind_id'],'type'=>0,'order_sn'=>'','number'=>-1,'note'=>"农场进行孵化",'createtime'=>time()]);
            $data = [];
            $data['status']     = 0;
            $data['hatch_num']  = 1;
            $data['shape']      = 0;
            $data['is_reap']    = 0;
            $data['uptime']     = time();
            $data['createtime'] = time();
            $rs_hatch = Db::name("egg_hatch")->where("id",$egg_hatch_id)->update($data);

            $valid_number = Db::name("egg_kind")->where("id",$result['kind_id'])->value("valid_number");
            $rs_valid = Db::name("user")->where("id",$this->auth->id)->setInc('valid_number',$valid_number);
            //上级发放有效值
            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['level']   = ['<',3];
            $plist = Db::name("membership_chain")->where($wh)->select();
            if(!empty($plist)){
                foreach ($plist as $key => $value) {                
                    Db::name("user")->where("id",$value['ancestral_id'])->setInc('valid_number',$valid_number);
                }
            }            
            if($reduce_rs && $rs_frozen && $rs_egg_log && $rs_hatch && $rs_valid){
                Db::commit();
                $this->success(__('Eggs were added to hatch successfully'));
            }else{
                Db::rollback();
                $this->error(__('Failed to add eggs for hatching, please try again'));
            }
        });
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
        $number         = $this->request->post('number');
        $paypwd         = $this->request->post('paypwd');

        $auth = new \app\common\library\Auth();
        if ($this->auth->password != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error(__('Paypwd is incorrect'));
        }

        $wh = [];
        $wh['serial_number'] = $serial_number;
        $wh['status']       = 'normal';
        $user_id = Db::name("user")->where($wh)->value("id");
        if(empty($user_id)){
            $this->error(__('User does not exist!'));
        }

        $rate = ceil($number*Config::get("site.rate_config")/100)??0;
        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['kind_id'] = $kind_id;
        $total = Db::name("egg")->where($wh)->value('`number`-`frozen`');
        if($total < ($number + $rate)){
            $this->error('数量不够,转账失败!');
        }
        $log_rate = true;
        Db::startTrans();
        try {
            $reduce_rs = Db::name("egg")->where($wh)->setDec('number',($number+$rate));

            //写入日志
            $log_reduce = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>2,'order_sn'=>'','number'=>-$number,'note'=>"转账给用户编号：".$serial_number." 减少",'createtime'=>time()]);
            
            if($rate>0){
                //写入日志
                $log_rate = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>9,'order_sn'=>'','number'=>-$rate,'note'=>"转账给用户编号：".$serial_number." 手续费",'createtime'=>time()]);
            }

            $wh = [];
            $wh['user_id'] = $user_id;
            $wh['kind_id'] = $kind_id;
            $add_rs = Db::name("egg")->where($wh)->setInc('number',$number);
            //写入日志
            $log_add = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$user_id,'kind_id'=>$kind_id,'type'=>2,'order_sn'=>'','number'=>$number,'note'=>"用户编号：".$this->auth->serial_number." 转账获得",'createtime'=>time()]);
            if($reduce_rs && $log_reduce && $log_rate && $add_rs && $log_add){
                Db::commit();
                $this->success(__('Transfer succeeded'));
            }else{
                Db::rollback();
                $this->error(__('Transfer failed, please try again'));
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }    
    }

}
