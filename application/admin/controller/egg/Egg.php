<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;
use think\Db;

/**
 * 蛋
 *
 * @icon fa fa-circle-o
 */
class Egg extends Backend
{
    
    /**
     * Egg模型对象
     * @var \app\admin\model\egg\Egg
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\egg\Egg;

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
                    ->with(['eggkind','user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('eggkind')->visible(['name']);
				$row->getRelation('user')->visible(['username','mobile']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
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
                if(!is_numeric($params['change_number']) || $params['change_number']==0){
                    $this->error('不能为0的数值');
                }
                if($params['type']==1){
                    $new_number = $row['number']+$params['change_number'];
                    $params['number'] = $new_number;
                }else{
                    if(!in_array($row['kind_id'],[1,2,3])){
                        $this->error('添加蛋积分类型错误');
                    }
                    $new_number = $row['point']+intval($params['change_number']*10000)/10000;
                    $params['point'] = $new_number;
                }
                
                if($new_number < 0){                    
                    $this->error('变动数量超出已有的数量');
                }

                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    $note = "管理员：".$this->auth->username." ".$params['note'];
                    if($params['type']==1){
                        $log = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$row['user_id'],'kind_id'=>$row['kind_id'],'type'=>4,'order_sn'=>'','number'=>$params['change_number'],'note'=>$note,'createtime'=>time()]);
                    }else{
                        $log = Db::name("egg_score_log")->insert(['user_id'=>$row['user_id'],'kind_id'=>$row['kind_id'],'type'=>3,'score'=>$params['change_number'],'memo'=>$note,'createtime'=>time()]);
                    }

                    if ($result !== false && $log) {
                        Db::commit();
                        $this->success();
                    } else {
                        Db::rollback();
                        $this->error(__('No rows were updated'));
                    }
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
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
