<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use think\Config;
use think\Db;

/**
 * 用户认证信息
 *
 * @icon fa fa-circle-o
 */
class Attestation extends Backend
{
    
    /**
     * Attestation模型对象
     * @var \app\admin\model\user\Attestation
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Attestation;

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
                    ->with(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['username','serial_number','nickname','mobile','is_attestation']);
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
                $is_attachment = Db::name("user")->where("id",$row['user_id'])->value("is_attestation");
                if($is_attachment != 2){
                    $this->error("用户认证已被审核!");
                }
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $note = "";
                    if($params['is_attestation']==1){
                        $note = "审核通过\n";
                    }else if($params['is_attestation']==3){
                        $note = "不通过\n";
                    }else{
                        $this->error('操作错误');
                    }

                    $params['note'] = date('Y-m-d H:i').":".$this->auth->username."审核结果：".$note.$row["note"];

                    $rs = Db::name("user")->where("id",$row['user_id'])->update(['updatetime'=>time(),'is_attestation'=>$params['is_attestation']]);
                    if($rs && $params['is_attestation'] == 1){
                        $number = Config::get("site.valid_number");
                        $wh = [];
                        $wh['user_id'] = $row['user_id'];
                        $wh['kind_id'] = 1;
                        $before = Db::name("egg")->where($wh)->value('number');
                        $add_rs = Db::name("egg")->where($wh)->inc("number",$number)->inc("frozen",$number)->update();
                        $add_log = \app\admin\model\egg\Log::saveLog($row['user_id'],1,0,'',$number,$before,($before+$number),"赠送体验蛋");
                        $userLevelConfig = new \app\common\model\UserLevelConfig();
                        // $userLevelConfig->update_vip($row['user_id']);
                        //上级发放有效值
                        $wh = [];
                        $wh['user_id'] = $row['user_id'];
                        $wh['level']   = ['<=',3];
                        $plist = Db::name("membership_chain")->where($wh)->order("level","ASC")->select();
                        if(!empty($plist)){
                            foreach ($plist as $key => $value) {                         
                                $userLevelConfig->update_vip($value['ancestral_id']);
                            }
                        }
                        // 直推奖励
                        \app\admin\model\egg\RewardConfig::getAward($row['user_id']);
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

    /**
     * 编辑姓名
     */
    public function edit_info($ids = null)
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
