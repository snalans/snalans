<?php
namespace app\api\controller\farm;

use app\common\controller\Api;
use think\Db;

/**
 * 蛋合成接口
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
        $rs = true;
        $log_dec = true;
        foreach ($config as $key => $value) {            
            $wh = [];
            $wh['user_id'] = $this->auth->id;
            $wh['kind_id'] = $value['ch_kind_id'];
            $rs = Db::name("egg")->where($wh)->setDec('number',($value['number']*$number));
            $log_dec = \app\admin\model\egg\Log::saveLog($this->auth->id,$value['ch_kind_id'],3,'',-($value['number']*$number),"合成扣减");
            if(!$rs || !$log_dec){
                break;
            }
        }

        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['kind_id'] = $kind_id;
        $add_rs = Db::name("egg")->where($wh)->setInc('number',$number); 
        $log_add = \app\admin\model\egg\Log::saveLog($this->auth->id,$kind_id,3,'',$number,"合成获得");

        if($rs && $log_dec && $add_rs && $log_add){
            Db::commit();
            $this->success('合成成功!');
        }else{
            Db::rollback();
            $this->error('合成失败,请重试!');
        }       
    }

}