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
        $kind_name = Db::name("egg_kind")->where("id",$params['kind_id'])->value("name");

        $date = "2021-06-07";
        $time = strtotime("+1 day",strtotime($date));
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
    }
}
