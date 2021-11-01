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
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->with(['attestation'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('attestation')->visible(['name']);
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
                if($row['status'] != 3){
                    $this->error('订单状态不允许执行');
                }
                $result = false;
                Db::startTrans();
                try {
                    $log_re = true;
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $number = 0;
                    if($params['status'] == 1){
                        $note = "申诉通过";
                        $number = $row['number'];
                        $user_id = $row['buy_user_id'];
                    }else if($params['status'] == 6){
                        $note = " 申诉不通过. ";
                        $number = $row['number'] + $row['rate'];
                        $user_id = $row['sell_user_id'];
                        //写入日志
                        $log_re = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$user_id,'kind_id'=>$row['kind_id'],'type'=>9,'order_sn'=>$row['order_sn'],'number'=>$row['rate'],'note'=>$note.",返还手续费",'createtime'=>time()]);
                    }else{
                        $this->error("无效操作");
                    }
                    $wh = [];
                    $wh['user_id'] = $user_id;
                    $wh['kind_id'] = $row['kind_id'];
                    $inc_rs = Db::name("egg")->where($wh)->setInc('number',$number);
                    //写入日志
                    $log_rs = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$user_id,'kind_id'=>$row['kind_id'],'type'=>1,'order_sn'=>$row['order_sn'],'number'=>$row['number'],'note'=>$note,'createtime'=>time()]);

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


    /**
     * 支付
     */
    public function pay($ids = null)
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
                if($row['status'] != 0 && $row['kind_id'] != 5){
                    $this->error('该订单不允许操作');
                }
                $result = false;
                $info = Db::name("egg_charge_code")->where("id",$params['charge_code'])->find();
                if(empty($info)){
                    $this->error('支付方式有误');
                }
                Db::startTrans();
                try {
                    $log_re = true;
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $params['attestation_type']     = $info['type'];
                    $params['attestation_image']    = $info['image'];
                    $params['attestation_account']  = $info['account'];
                    $params['status']               = 2;
                    $params['pay_time']             = time();
                    $result = $row->allowField(true)->save($params);
                    if ($result) {  
                        \app\common\library\Hsms::send($row['sell_mobile'], '','order');
                        Db::commit();                  
                        $this->success("支付成功");
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
        $pay_info = Db::name("egg_charge_code")->field(["user_id","add_time"],true)->where("user_id",$row['sell_user_id'])->select();
        if(!empty($pay_info)){
            foreach ($pay_info as $key => $value) {
                if($value['type']==1){
                    $pay_name = "支付宝";
                }else if($value['type']==2){
                    $pay_name = "微信";
                }else if($value['type']==3){
                    $pay_name = "钱包";
                }else{
                    $pay_name = "银行卡";
                }
                $pay_info[$key]['pay_name'] = $pay_name;
            }
        }
        $this->view->assign("pay_info",$pay_info);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
