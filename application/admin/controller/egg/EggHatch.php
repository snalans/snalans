<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;
use think\Db;

/**
 * 蛋孵化列管理
 *
 * @icon fa fa-circle-o
 */
class EggHatch extends Backend
{
    
    /**
     * EggHatch模型对象
     * @var \app\admin\model\egg\EggHatch
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\egg\EggHatch;

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

            $list = $this->model
                    ->with(['user','eggnestkind','eggkind'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['serial_number','username','mobile']);
				$row->getRelation('eggnestkind')->visible(['name']);
				$row->getRelation('eggkind')->visible(['name']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 还原鸡窝
     */
    public function reduction($ids = '')
    {        
        if ($this->request->isPost()) 
        {
            $data = [];
            $data['hatch_num']  = 0;
            $data['shape']      = 5;
            $data['is_reap']    = 0;
            $data['status']     = 1;
            $data['is_give']    = 0;
            $data['uptime']     = time();
            $rs = Db::name("egg_hatch")->where("id",$ids)->update($data);

            if ($rs !== false) {
                $this->success("窝清空成功".$rs);
            }
        }
        $this->error("操作失败".$rs);
    }

}
