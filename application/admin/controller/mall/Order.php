<?php

namespace app\admin\controller\mall;

use app\common\controller\Backend;
use think\Db;

/**
 * 商品管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    
    /**
     * Order模型对象
     * @var \app\admin\model\mall\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\mall\Order;

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
                    ->with(['user','selluser'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['serial_number','mobile']);
                $row->getRelation('selluser')->visible(['serial_number','mobile']);
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
                $result = false;
                $log_rs = true;
                $log_re = true;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    if($row['is_virtual'] == 1){
                        if($params['status'] == 1){                            
                            $params['received_time']    = time();
                            $params['send_time']        = time();
                        }else if($params['status'] == 6){
                            $total_amount = $row['total_price']+$row['rate'];
                            $wh = [];
                            $wh['user_id'] = $row['buy_user_id'];
                            $wh['kind_id'] = $row['kind_id'];
                            $before = Db::name("egg")->where($wh)->value('number');
                            $inc_rs = Db::name("egg")->where($wh)->setInc('number',$total_amount);
                            //写入日志
                            $log_rs = Db::name("egg_log")->insert(['user_id'=>$row['buy_user_id'],'kind_id'=>$row['kind_id'],'type'=>1,'order_sn'=>$row['order_sn'],'number'=>$row['total_price'],'before'=>$before,'after'=>($before+$row['total_price']),'note'=>"充值申请失败返还消费",'createtime'=>time()]);
                            if($row['rate']>0){         
                                //手续费写入日志
                                $log_re = Db::name("egg_log")->insert(['user_id'=>$row['buy_user_id'],'kind_id'=>$row['kind_id'],'type'=>9,'order_sn'=>$row['order_sn'],'number'=>$row['rate'],'before'=>($before+$row['total_price']),'after'=>($before+$total_amount),'note'=>"充值申请失败返还手续费",'createtime'=>time()]);
                            }
                        }
                        
                    }else{
                        $params['status']       = 3;
                        $params['send_time']    = time();
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
                if ($result !== false && $log_rs && $log_re) {
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


    /**
     * 申诉处理
     */
    public function appeal($ids = null)
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
                if(!in_array($row['status'],[5,7])){
                    $this->error('订单状态不允许执行');
                }
                $result = false;
                Db::startTrans();
                try {
                    $log_re = true;
                    $inc_rs = true;
                    $log_rs = true;
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $number = 0;
                    if($params['status'] == 1){
                        if($row['status'] == 5){
                            $note = $row['note']."\n 申请退款失败";
                        }else{
                            $note = $row['note']."\n 卖家申诉成功";
                        }                        
                        $number = $row['total_price'];
                        $user_id = $row['sell_user_id'];
                    }else if($params['status'] == 6){
                        if($row['status'] == 5){
                            $note = $row['note']."\n 申请退款成功返还消费";
                            $note_rate = $row['note']."\n 卖家申诉失败返还手续费";    
                        }else{
                            $note = $row['note']."\n 卖家申诉失败返还消费";
                            $note_rate = $row['note']."\n 卖家申诉失败返还手续费";                            
                        }  
                        $number = $row['total_price'] + $row['rate'];
                        $user_id = $row['buy_user_id'];
                    }else{
                        $this->error("无效操作");
                    }

                    if($user_id > 0)
                    {                        
                        $wh = [];
                        $wh['user_id'] = $user_id;
                        $wh['kind_id'] = $row['kind_id'];
                        $before = Db::name("egg")->where($wh)->value('number');
                        $inc_rs = Db::name("egg")->where($wh)->setInc('number',$number);
                        //写入日志
                        $log_rs = Db::name("egg_log")->insert(['user_id'=>$user_id,'kind_id'=>$row['kind_id'],'type'=>1,'order_sn'=>$row['order_sn'],'number'=>$row['total_price'],'before'=>$before,'after'=>($before+$row['total_price']),'note'=>$note,'createtime'=>time()]);

                        if($params['status'] == 6 && $row['rate']>0){         
                            //手续费写入日志
                            $log_re = Db::name("egg_log")->insert(['user_id'=>$user_id,'kind_id'=>$row['kind_id'],'type'=>9,'order_sn'=>$row['order_sn'],'number'=>$row['rate'],'before'=>($before+$row['total_price']),'after'=>($before+$number),'note'=>$note_rate,'createtime'=>time()]);
                        }
                    }

                    $result = $row->allowField(true)->save($params);
                    if ($result !== false && $inc_rs && $log_rs && $log_re) {  
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
