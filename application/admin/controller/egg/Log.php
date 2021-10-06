<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;

/**
 * 蛋变动日志
 *
 * @icon fa fa-circle-o
 */
class Log extends Backend
{
    
    /**
     * Log模型对象
     * @var \app\admin\model\egg\Log
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\egg\Log;

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
                    ->with(['user','eggkind'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);
            $rate = 0;        
            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['serial_number','username','mobile']);
				$row->getRelation('eggkind')->visible(['name']);
                if($row['type'] == 9){
                    $rate += $row['number']; 
                }
            }
            $total_rate = $this->model->where("type",9)->sum("number");
            $result = array("total" => $list->total(), "rows" => $list->items(),"extend"=>['total_rate'=>abs($total_rate),'rate'=>abs($rate)]);

            return json($result);
        }
        return $this->view->fetch();
    }

}
