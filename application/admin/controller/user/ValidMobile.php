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
                $array_in = array_unique($arr);
                $wh = [];
                $wh['mobile'] = ['in',$arr];
                $list = Db::name("egg_valid_mobile")->where($wh)->value("group_concat(mobile)");
                $array_is = [];
                if(!empty($list)){
                    $array_is = explode(",",$list);
                    Db::name("egg_valid_mobile")->where("mobile",'in',$array_is)->inc("num")->update(['status'=>1]);
                    // $this->error("不允许重复添加手机号:".$list);
                }
                $result = false;
                Db::startTrans();
                try {
                    $array_in = array_diff($array_in, $array_is);
                    $data = [];
                    if(!empty($array_in)){
                        foreach ($array_in as $key => $value) {
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
                    $ids = Db::name("user")->where('mobile','in',$arr)->column("id");
                    Db::name("user_token")->where("user_id",'in',$ids)->delete();
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


    /**
     * 批量更新
     */
    public function multi($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            if ($this->request->has('params')) {
                parse_str($this->request->post("params"), $values);
                $values = $this->auth->isSuperAdmin() ? $values : array_intersect_key($values, array_flip(is_array($this->multiFields) ? $this->multiFields : explode(',', $this->multiFields)));
                if ($values) {
                    $adminIds = $this->getDataLimitAdminIds();
                    if (is_array($adminIds)) {
                        $this->model->where($this->dataLimitField, 'in', $adminIds);
                    }
                    $count = 0;
                    Db::startTrans();
                    try {
                        $list = $this->model->where($this->model->getPk(), 'in', $ids)->select();
                        foreach ($list as $index => $item) {
                            if($values['status'] == 1){
                                $user_id = Db::name("user")->where('mobile',$item->mobile)->value("id");
                                if(!empty($user_id)){
                                    Db::name("user_token")->where("user_id",$user_id)->delete();
                                }
                            }
                            $count += $item->allowField(true)->isUpdate(true)->save($values);
                        }
                        Db::commit();
                    } catch (PDOException $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    if ($count) {
                        $this->success();
                    } else {
                        $this->error(__('No rows were updated'));
                    }
                } else {
                    $this->error(__('You have no permission'));
                }
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

}
