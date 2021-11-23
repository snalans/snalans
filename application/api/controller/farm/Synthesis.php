<?php
namespace app\api\controller\farm;

use app\common\controller\Api;
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
        $number        = $this->request->post("number",1);

        if(!in_array($kind_id,[2,3,4]) || $number<=0 || $number>20){    
            $this->error("参数错误");
        }

        $u_where = [];
        $u_where['status'] = 'normal';
        $u_where['is_attestation'] = 1;
        $u_where['id'] = $this->auth->id;
        $user_info = Db::name("user")
            ->field("id,serial_number,mobile")
            ->where($u_where)
            ->find();
        if(empty($user_info)){
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
                $dec_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$value['ch_kind_id'],'type'=>3,'order_sn'=>'','number'=>-$dec_num,'note'=>"合成扣减",'createtime'=>time()]);
            }

            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $kind_id;
            $inc_rs = Db::name("egg")->where($wh)->setInc('hatchable',$number); 
            $inc_log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>3,'order_sn'=>'','number'=>$number,'note'=>"合成获得可孵化的蛋",'createtime'=>time()]);
            if($dec_rs && $dec_log && $inc_rs && $inc_log){
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

}