<?php

namespace app\admin\controller\auth;

use app\common\controller\Backend;
use think\Db;

/**
 * 黑名单
 *
 * @icon fa fa-circle-o
 */
class Blacklist extends Backend
{
    
    /**
     * Blacklist模型对象
     * @var \app\admin\model\auth\Blacklist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\auth\Blacklist;

    }

    public function import()
    {
        parent::import();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    /*
     * 获取黑名单信息
     */
    public function getInfo()
    {
        $black = Db::name("blacklist")->where("type",0)->value("group_concat(param)");

        $wh = [];
        $wh['status'] = 'hidden';
        if(!empty($black)){
            $wh['mobile'] = ['not in',$black];
        }
        $list = Db::name("user")->field("id,mobile")->where($wh)->select();
        if(!empty($list)){
            $datas = [];
            foreach ($list as $key => $value) {
                $data = [];
                $data['type']  = 0;
                $data['param'] = $value['mobile'];
                $datas[] = $data;

                $id_card = Db::name('egg_attestation')->where('user_id',$value['id'])->value("id_card");
                if(!empty($id_card))
                {                    
                    $data = [];
                    $data['type']  = 6;
                    $data['param'] = $id_card;
                    $datas[] = $data;
                }

                $charge = Db::name("egg_charge_code")->field("type,mobile,account")->where('user_id',$value['id'])->select();
                if(!empty($charge))
                {                    
                    foreach ($charge as $k => $val) {
                        $data = [];
                        $data['type']  = $val['type'];
                        $data['param'] = $val['type']==4?$val['mobile']:$val['account'];
                        $datas[] = $data;
                    }
                }
            }
            Db::name("blacklist")->insertAll($datas);
        }
        $this->success();
    }
}
