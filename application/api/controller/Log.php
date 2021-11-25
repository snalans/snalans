<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 日志接口
 * @ApiWeigh   (33)
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
     * @ApiParams   (name="is_team", type="int", description="是否团队有效值 1=是 0=不是")
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
        $is_team        = $this->request->get("is_team/d",0);
        $page           = $this->request->get("page",1);
        $per_page       = $this->request->get("per_page",15);

        if($is_team == 1){
            $wh = [];
            $wh['c.ancestral_id'] = $this->auth->id;
            $wh['c.level']        = ['<',4];
            $list = Db::name("egg_valid_number_log")->alias("l")
                    ->field("u.serial_number,l.type,l.number,FROM_UNIXTIME(l.add_time,'%Y-%m-%d %H:%i') as add_time")
                    ->join("user u","u.id=l.origin_user_id","LEFT")
                    ->join("membership_chain c","c.user_id=l.origin_user_id","LEFT")
                    ->where($wh)
                    ->order("l.add_time","desc")
                    ->paginate($per_page);
        }else{

            $list = Db::name("egg_valid_number_log")->alias("l")
                    ->field("u.serial_number,l.type,l.number,FROM_UNIXTIME(l.add_time,'%Y-%m-%d %H:%i') as add_time")
                    ->join("user u","u.id=l.origin_user_id","LEFT")
                    ->where("l.user_id",$this->auth->id)
                    ->order("l.add_time","desc")
                    ->paginate($per_page);
        }
        $this->success('',$list);
    }

    /**
     * 获取蛋积分日志
     * 
     * @ApiMethod (GET)
     * @ApiParams   (name="page", type="int", description="页码")
     * @ApiParams   (name="per_page", type="int", description="数量")
     * 
     * @ApiReturnParams   (name="type", type="int", description="类型：1团队分红，2积分兑换蛋")
     * @ApiReturnParams   (name="score", type="int", description="变更积分")
     * @ApiReturnParams   (name="memo", type="string", description="备注")
     * @ApiReturnParams   (name="createtime", type="string", description="创建时间")
     */
    public function getScoreLog()
    {
        $page       = $this->request->get("page",1);
        $per_page   = $this->request->get("per_page",15);
        $wh = [];
        $wh['user_id']  = $this->auth->id;
        $wh['type']     = ['<>',3];
        $list = Db::name("egg_score_log")
                ->field("type,score,memo,FROM_UNIXTIME(createtime,'%m-%d %H:%i') as createtime")
                ->where($wh)
                ->order("createtime","desc")
                ->paginate($per_page);
        $this->success('',$list);
    }


    /**
     * 获取团队有效值列表
     * 
     * @ApiMethod (GET)
     * @ApiParams   (name="page", type="int", description="页码")
     * @ApiParams   (name="per_page", type="int", description="数量")
     * 
     * @ApiReturnParams   (name="avatar", type="string", description="用户头像")
     * @ApiReturnParams   (name="serial_number", type="string", description="用户编号")
     * @ApiReturnParams   (name="title", type="string", description="等级名称")
     * @ApiReturnParams   (name="valid_number", type="int", description="有效值")
     * @ApiReturnParams   (name="is_attestation", type="string", description="是否认证 0=否 1=是 2=待审核 3=失败")
     */
    public function getTeamValid()
    {
        $page       = $this->request->get("page",1);
        $per_page   = $this->request->get("per_page",15);

        $wh = [];
        $wh['c.ancestral_id'] = $this->auth->id;
        $wh['c.level']        = ['<',4];
        $list = Db::name("membership_chain")->alias("c")
                ->field("u.id,u.nickname,u.avatar,u.serial_number,l.title,u.is_attestation,u.valid_number,u.createtime")
                ->join("user u","u.id=c.user_id","LEFT")
                ->join("user_level_config l","l.level=u.level","LEFT")
                ->where($wh)
                ->order("u.createtime","desc")
                ->paginate($per_page)->each(function($item){
                    $item['avatar'] = $item['avatar']? cdnurl($item['avatar'], true) : letter_avatar($item['nickname']);
                    if($item['is_attestation'] != 1){
                        $item['title'] = "普通会员";
                    }
                    $item['createtime'] = date("Y-m-d",$item['createtime']);
                    unset($item['id']);
                    unset($item['nickname']);
                    return $item;
                });

        $this->success('',$list);
    }
}