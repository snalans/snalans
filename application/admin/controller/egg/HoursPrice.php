<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;
use think\Db;

/**
 * //蛋收盘价格管理
 *
 * @icon fa fa-circle-o
 */
class HoursPrice extends Backend
{
    
    /**
     * HoursPrice模型对象
     * @var \app\admin\model\egg\HoursPrice
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\egg\HoursPrice;

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
                $attr = array_values(array_filter(explode("\n",$params['content']),function($str){return trim($str);}));
                Db::startTrans();
                try {
                    $kind_name = Db::name("egg_kind")->where("id",$params['kind_id'])->value("name");
                    $datas = [];
                    foreach ($attr as $key => $value) {
                        $info       = explode("#",$value);
                        $day        = date("Y-m-d",strtotime($info[1]));
                        $hours      = date("H:i",strtotime($info[1]));
                        $wh = [];
                        $wh['kind_id']    = $params['kind_id'];
                        $wh['day']        = $day;
                        $wh['hours']      = $hours;
                        $rs = Db::name("egg_hours_price")->where($wh)->find();
                        if(empty($rs))
                        {
                            $data = [];
                            $data['kind_id']    = $params['kind_id'];
                            $data['kind_name']  = $kind_name;
                            $data['price']      = $info[0];
                            $data['day']        = $day;
                            $data['hours']      = $hours;
                            $data['createtime'] = strtotime($info[1]);
                            $datas[] = $data;
                        }
                    }
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = Db::name("egg_hours_price")->insertAll($datas);
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
                $params['kind_name'] = Db::name("egg_kind")->where("id",$params['kind_id'])->value("name");
                
                try {
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

    public function mkPrice()
    {
        $range = 1.2;
        $kind_id = 1;

        $datas = [];
        $price1 = $min_price1 = 0.5;
        $price2 = $min_price2 = 10;
        $price3 = $min_price3 = 80;
        $stime = strtotime("2021-06-06 09:00:00");
        for ($i=1; $i < 86; $i++) { 
            $time = strtotime("+$i day",$stime);
            $day  = date("Y-m-d",$time);
            
            $wh = [];
            $wh['kind_id']    = 1;
            $wh['day']        = $day;
            $rs = Db::name("egg_hours_price")->where($wh)->find();
            if(empty($rs))
            {
                for ($n=0; $n < 13; $n++) 
                { 
                    $t = strtotime("+$n hours",$stime);
                    $hours = date("H:i",$t);
                    $data = [];
                    $data['kind_id']    = 1;
                    $data['kind_name']  = "白蛋";
                    $data['price']      = $price1;
                    $data['day']        = $day;
                    $data['hours']      = $hours;
                    $data['createtime'] = $t;
                    $datas[] = $data;
                    $data['kind_id']    = 2;
                    $data['kind_name']  = "铜蛋";
                    $data['price']      = $price2;
                    $datas[] = $data;
                    $data['kind_id']    = 3;
                    $data['kind_name']  = "银蛋";
                    $data['price']      = $price3;
                    $datas[] = $data;

                    $max_price1 = $min_price1 * $range;
                    $max_price2 = $min_price2 * $range;
                    $max_price3 = $min_price3 * $range;
                    if($n!=0){
                        $price1 = intval(rand($min_price1*100,$max_price1*100))/100;
                        $price2 = intval(rand($min_price2*100,$max_price2*100))/100;
                        $price3 = intval(rand($min_price3*100,$max_price3*100))/100;
                    }

                    if($n==12){
                        if($i%7==0){
                            $min_price1 = $min_price1 + intval($min_price1*0.07);
                            $min_price2 = $min_price2 + intval($min_price2*0.16);
                            $min_price3 = $min_price3 + intval($min_price3*0.17);
                        }
                    }
                }
            }
        }
        $num = Db::name("egg_hours_price")->insertAll($datas);
        echo $num." Successed";
    }
}
