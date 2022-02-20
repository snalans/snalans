<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use think\Db;

/**
 * 会员关系链
 *
 * @icon fa fa-circle-o
 */
class Membership extends Backend
{
    
    /**
     * Membership模型对象
     * @var \app\admin\model\user\Membership
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Membership;

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
    

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $wh = [];
            $wh['u.status'] = 'normal';
            $wh['u.is_attestation'] = 1;
            $list = Db::name("membership_chain")->alias("mc")
                    ->field("fu.id,fu.serial_number,fu.mobile,fu.valid_number,fu.status,fu.is_attestation,mc.ancestral_id,SUM(IF(u.`status`='normal' && u.is_attestation=1 && mc.level<=3,u.valid_number,0)) as total")
                    ->join("user u","u.id=mc.user_id","LEFT")
                    ->join("user fu","fu.id=mc.ancestral_id","LEFT")
                    ->where($where)
                    // ->where($wh)
                    ->order("total","desc")
                    ->group("mc.ancestral_id")
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
