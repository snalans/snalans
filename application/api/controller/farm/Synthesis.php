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
     * 获取订单列表
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

        if(!in_array($kind_id,[2,3,4])){    
            $this->error("合成目标蛋错误");
        }
        $config = Db::name("egg_synthesis_config")->where("kind_id",$kind_id)->select();     
        $egg_list = Db::name("egg")
                    ->where("user_id",$this->auth->id)
                    ->column("(`number`-`frozen`) as number","kind_id");
                    
        $flag = true;
        foreach ($config as $key => $value) {
            if(($value['number']*$number) > $egg_list[$value['ch_kind_id']]){
                $flag = false;
            }
        }
        if(!$flag){
            $this->error("合成失败,数量不够,请检查.");
        }
        
        Db::startTrans();
        try {
            foreach ($config as $key => $value) {            
                $wh = [];
                $wh['user_id'] = $this->auth->id;
                $wh['kind_id'] = $value['ch_kind_id'];
                Db::name("egg")->where($wh)->setDec('number',($value['number']*$number));
                Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$value['ch_kind_id'],'type'=>3,'order_sn'=>'','number'=>-($value['number']*$number),'note'=>"合成扣减",'createtime'=>time()]);
            }

            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $kind_id;
            Db::name("egg")->where($wh)->setInc('number',$number); 
            Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>3,'order_sn'=>'','number'=>$number,'note'=>"合成获得",'createtime'=>time()]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }   
        $this->success('合成成功!'); 
    }

}