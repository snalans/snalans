<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Db;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    
    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;

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
                if($row['status'] != 3){
                    $this->error('订单状态不允许执行');
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
                    $number = 0;
                    if($params['status'] == 1){
                        $note = "管理员：".$this->auth->username." 审核通过. ".$params['note'];
                        $number = $row['number'];
                        $user_id = $row['buy_user_id'];
                    }else{
                        $note = "管理员：".$this->auth->username." 审核不通过. ".$params['note'];
                        $number = $row['number'] + $row['rate'];
                        $user_id = $row['sell_user_id'];
                        //写入日志
                        Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$user_id,'kind_id'=>$row['kind_id'],'type'=>9,'order_sn'=>$row['order_sn'],'number'=>$row['rate'],'note'=>$note." 返还手续费",'createtime'=>time()]);
                    }
                    $wh = [];
                    $wh['user_id'] = $user_id;
                    $wh['kind_id'] = $row['kind_id'];
                    Db::name("egg")->where($wh)->setInc('number',$number);
                    //写入日志
                    Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$user_id,'kind_id'=>$row['kind_id'],'type'=>4,'order_sn'=>$row['order_sn'],'number'=>$row['number'],'note'=>$note,'createtime'=>time()]);

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
