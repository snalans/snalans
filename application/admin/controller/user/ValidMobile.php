<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use think\Db;

/**
 * 需要重置的手机号
 *
 * @icon fa fa-circle-o
 */
class ValidMobile extends Backend
{
    
    /**
     * ValidMobile模型对象
     * @var \app\admin\model\user\ValidMobile
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\ValidMobile;

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
                $arr = explode("\n",$params['mobile']);
                $arr = array_filter(array_map('trim', $arr));
                $arr = array_unique($arr);
                $wh = [];
                $wh['mobile'] = ['in',$arr];
                $list = Db::name("egg_valid_mobile")->where($wh)->value("group_concat(mobile)");
                if(!empty($list)){
                    $this->error("不允许重复添加手机号:".$list);
                }
                $result = false;
                Db::startTrans();
                try {
                    $data = [];
                    if(!empty($arr)){
                        foreach ($arr as $key => $value) {
                            $data[]['mobile'] = trim($value);
                        }
                    }
                    $result = Db::name("egg_valid_mobile")->insertAll($data);
                    //是否采用模型验证
                    // if ($this->modelValidate) {
                    //     $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    //     $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                    //     $this->model->validateFailException(true)->validate($validate);
                    // }
                    // $result = $this->model->allowField(true)->save($params);
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
                    $this->error("不允许重复添加手机号");
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }


}
