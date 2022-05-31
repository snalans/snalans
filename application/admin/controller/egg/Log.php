<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;
use think\Db;

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
                    ->field("log.*,user.serial_number as `user.serial_number`,user.username as `user.username`,user.mobile as `user.mobile`")
                    ->join("user user","user.id=log.user_id","LEFT")
                    ->where($where)    
                    ->order($sort, $order)
                    ->paginate($limit);
                    
            $filter = json_decode($this->request->get('filter'),true);
            $op = json_decode($this->request->get('op'),true);
            if(isset($filter['type'])){
                unset($filter['type']);
                unset($op['type']);
            }
            $this->request->get(['filter'=>json_encode($filter)]);
            $this->request->get(['filter'=>json_encode($op)]);
            $info = $this->model
                    ->with(['user'])
                    ->where($where)  
                    ->where("type",9)
                    ->group("kind_id")
                    ->column("kind_id,sum(number) as rate");
            $info = collection($info)->toArray();

            $rate = [];
            $rate['egg1'] = isset($info[1])?abs($info[1]):0;
            $rate['egg2'] = isset($info[2])?abs($info[2]):0;
            $rate['egg3'] = isset($info[3])?abs($info[3]):0;            

            $result = array("total" => $list->total(), "rows" => $list->items(),"extend"=>$rate);
            
            return json($result); 
        }
        return $this->view->fetch();
    }


    /**
     * 查看
     */
    public function get_month()
    {
        $data = [];
        $data['list'][] = [
            'id'    => date("Y-m"),
            'name'  => date("Y-m"),
        ];
        for ($i=1; $i < 12; $i++) { 
            $data['list'][] = [
                'id'   => date("Y-m",strtotime("- $i month")),
                'name' => date("Y-m",strtotime("- $i month")),
            ];
        }
        $data['total'] = 12;
        return json($data);
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    $params['type'] = 9;
                    $params['number'] = -abs($params['number']);
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    $params['number'] = -abs($params['number']);
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

}
