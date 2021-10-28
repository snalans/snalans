<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 日志接口
 * @ApiWeigh   (26)
 */
class Log extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 获取有效值日志
     * 
     * @ApiMethod (GET)
     * @ApiParams   (name="page", type="int", description="页码")
     * @ApiParams   (name="per_page", type="int", description="数量")
     * 
     * @ApiReturnParams   (name="serial_number", type="string", description="值来源的会员编号")
     * @ApiReturnParams   (name="type", type="int", description="类型：1孵化，2购买蛋")
     * @ApiReturnParams   (name="number", type="string", description="数量")
     * @ApiReturnParams   (name="add_time", type="string", description="获得时间")
     */
    public function getValidLog()
    {
        $page       = $this->request->get("page",1);
        $per_page   = $this->request->get("per_page",15);
        $list = Db::name("egg_valid_number_log")->alias("l")
                ->field("u.serial_number,l.type,l.number,FROM_UNIXTIME(l.add_time,'%Y-%m-%d %H:%i') as add_time")
                ->join("user u","u.id=l.origin_user_id","LEFT")
                ->where("l.user_id",$this->auth->id)
                ->order("l.add_time","desc")
                ->paginate($per_page);
        $this->success('',$list);
    }

    /**
     * 获取积分日志
     * 
     * @ApiMethod (GET)
     * @ApiParams   (name="page", type="int", description="页码")
     * @ApiParams   (name="per_page", type="int", description="数量")
     * 
     * @ApiReturnParams   (name="score", type="int", description="变更积分")
     * @ApiReturnParams   (name="before", type="int", description="变更前积分")
     * @ApiReturnParams   (name="after", type="int", description="变更后积分")
     * @ApiReturnParams   (name="memo", type="string", description="备注")
     * @ApiReturnParams   (name="createtime", type="string", description="创建时间")
     */
    public function getScoreLog()
    {
        $page       = $this->request->get("page",1);
        $per_page   = $this->request->get("per_page",15);
        $list = Db::name("user_score_log")->alias("l")
                ->field("l.before,l.score,l.after,l.memo,FROM_UNIXTIME(l.createtime,'%Y-%m-%d %H:%i') as createtime")
                ->where("l.user_id",$this->auth->id)
                ->order("l.createtime","desc")
                ->paginate($per_page);
        $this->success('',$list);
    }
}