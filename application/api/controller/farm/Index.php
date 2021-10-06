<?php
namespace app\api\controller\farm;

use app\common\controller\Api;
use think\Db;

/**
 * 农场接口
 */
class Index extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    protected $layout = '';
    protected $error = null;
    public    $alldate = 3600*24;

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
     * @ApiReturnParams   (name="name", type="string", description="窝名称")
     * @ApiReturnParams   (name="status", type="integer", description="状态 1=完成 0=孵化中")
     * @ApiReturnParams   (name="hatch_num", type="integer", description='孵化进度(天)')
     * @ApiReturnParams   (name="position", type="string", description="审核备注")
     * @ApiReturnParams   (name="shape", type="integer", description="形态 0=蛋 1=鸡")
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
        $data['nest_list'] = Db::name("egg_hatch")->alias("eh")
                    ->join("egg_nest_kind en","en.id=eh.nest_kind_id","LEFT")
                    ->field("en.name,eh.status,eh.hatch_num,eh.position,eh.shape,eh.is_reap")
                    ->where("eh.user_id",$this->auth->id)
                    ->order("en.weigh","DESC")
                    ->order("eh.position","ASC")
                    ->select();
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
            $this->error(__('The nest does not exist, please try again!'));
        }

        // 执行加蛋孵化
        if($result['status']==1){
            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $result['kind_id'];
            $total = Db::name("egg")->where($wh)->value('number');
            if($total <= 0){
                $this->error('数量不够,转账失败!');
            }else{
                $this->hatchEgg($egg_hatch_id,$result['kind_id']);
            }
        }else{
            $this->eheckCycle($result);
        }
    }

    /**
     * 判断周期,进行孵化与喂养
     * @ApiInternal
     */
    public function eheckCycle($egg=[])
    {
        $result = Db::name("egg_hatch_config")->where("kind_id",$egg['kind_id'])->find();
        $msg        = '';
        $rs         = false;
        $add_rs     = true;
        $log_add    = true;
        Db::startTrans();
        if(time() >= ($egg['uptime'] + $this->alldate)){
            $data = [];
            $data['hatch_num']  = $egg['hatch_num']+1;
            $data['uptime']     = time();
            if($egg['shape'] == 0){
                if($egg['hatch_num'] >= $result['hatch_cycle']){
                    $data['shape']  = 1;
                }
            }else {
                if($data['is_reap'] = 1){
                    $wh = [];
                    $wh['user_id'] = $this->auth->id;
                    $wh['kind_id'] = $egg['kind_id'];
                    $add_rs = Db::name("egg")->where($wh)->setInc('number',1);
                    $log_add = \app\admin\model\egg\Log::saveLog($this->auth->id,$egg['kind_id'],0,'',1,"喂养获得");
                    $data['is_reap'] = 0;
                }else{                    
                    $reap = $data['hatch_num']-$result['hatch_cycle']-$result['raw_cycle'];
                    if(($reap%$result['raw_cycle']) == 0){
                        $data['is_reap'] = 1;
                    }
                }
            }
            if($data['hatch_num'] > $result['grow_cycle']){
                $data['status']     = 1;
                $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
                $msg = "喂养完毕";
            }else{                
                $rs = Db::name("egg_hatch")->where("id",$egg['id'])->update($data);
            }
            $msg = $rs?"孵化成功":"孵化失败,请重试.";
        }else{
            $msg = "已经在孵化中...";
        }

        if($rs && $add_rs && $log_add){
            Db::commit();
            $this->success($msg);
        }else{
            Db::rollback();
            $this->error($msg);
        }
    }

    /**
     * 添蛋孵化
     * @ApiInternal
     */
    public function hatchEgg($egg_hatch_id=0,$kind_id=0)
    {
        Db::startTrans();
        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['kind_id'] = $kind_id;
        $reduce_rs = Db::name("egg")->where($wh)->setDec('number',1);
        $log_reduce = \app\admin\model\egg\Log::saveLog($this->auth->id,$kind_id,0,'',-1,"农场进行孵化");
        $data = [];
        $data['status']     = 0;
        $data['hatch_num']  = 1;
        $data['shape']      = 0;
        $data['is_reap']    = 0;
        $data['uptime']     = time();
        $data['createtime'] = time();
        $rs = Db::name("egg_hatch")->where("id",$egg_hatch_id)->update($data);
        if($reduce_rs && $rs && $log_reduce){
            Db::commit();
            $this->success(__('Eggs were added to hatch successfully'));
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
            $this->error('用户不存在!');
        }

        $rate = ceil($number*10/100)??0;
        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['kind_id'] = $kind_id;
        $total = Db::name("egg")->where($wh)->value('number');
        if($total < ($number + $rate)){
            $this->error('数量不够,转账失败!');
        }
        $log_rate = true;
        Db::startTrans();
        $reduce_rs = Db::name("egg")->where($wh)->setDec('number',($number+$rate));
        $log_reduce = \app\admin\model\egg\Log::saveLog($this->auth->id,$kind_id,2,'',-$number,"转账给用户编号：".$serial_number." 减少");
        if($rate>0){
            $log_rate = \app\admin\model\egg\Log::saveLog($this->auth->id,$kind_id,9,'',-$rate,"转账给用户编号：".$serial_number." 手续费");
        }

        $wh = [];
        $wh['user_id'] = $user_id;
        $wh['kind_id'] = $kind_id;
        $add_rs = Db::name("egg")->where($wh)->setInc('number',$number);
        $log_add = \app\admin\model\egg\Log::saveLog($user_id,$kind_id,2,'',$number,"用户编号：".$this->auth->serial_number." 转账获得");
        if($reduce_rs && $log_reduce && $log_rate && $add_rs && $log_add){
            Db::commit();
            $this->success('转账成功');
        }else{
            Db::rollback();
            $this->error('转账失败,请重试！');
        }
    }

}
