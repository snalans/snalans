<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;
use think\Db;

/**
 * 蛋孵化列管理
 *
 * @icon fa fa-circle-o
 */
class EggHatch extends Backend
{
    
    /**
     * EggHatch模型对象
     * @var \app\admin\model\egg\EggHatch
     */
    protected $model = null;
    protected $multiFields = 'is_close';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\egg\EggHatch;

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
                
                $row->getRelation('user')->visible(['serial_number','username','mobile']);
            }
            $ext = [];
            $wh = [];
            $wh['u.level']          = 1;
            $wh['eh.nest_kind_id']  = 1;
            $wh['eh.status']        = 0;
            $ext['ext1'] = Db::name("egg_hatch")->alias("eh")
                        ->join("user u","u.id=eh.user_id","LEFT")
                        ->where($wh)
                        ->group("u.id")
                        ->count();
            $wh = [];
            $wh['u.level']          = 2;
            $wh['eh.nest_kind_id']  = 1;
            $wh['eh.status']        = 0;
            $ext['ext2'] = Db::name("egg_hatch")->alias("eh")
                        ->join("user u","u.id=eh.user_id","LEFT")
                        ->where($wh)
                        ->group("u.id")
                        ->count();
            $wh = [];
            $wh['u.level']          = 3;
            $wh['eh.nest_kind_id']  = 3;
            $wh['eh.status']        = 0;
            $ext['ext3'] = Db::name("egg_hatch")->alias("eh")
                        ->join("user u","u.id=eh.user_id","LEFT")
                        ->where($wh)
                        ->group("u.id")
                        ->count();                   

            $result = array("total" => $list->total(), "rows" => $list->items(),"extend"=>$ext);

            return json($result);
        }
        return $this->view->fetch();
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

                $total = Db::name("egg_nest_kind")->where("kind_id",$params['nest_kind_id'])->value("total");
                $wh = [];
                $wh['user_id']      = $params['user_id'];
                $wh['nest_kind_id'] = $params['nest_kind_id'];
                $wh['is_close']     = 0;
                $num = Db::name("egg_hatch")->where($wh)->count();
                if($num >= $total){
                    $this->error("窝数量达上限,无法增加。"); 
                }
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $wh = [];
                    $wh['user_id']        = $params['user_id'];
                    $wh['nest_kind_id']   = $params['nest_kind_id'];
                    $wh['kind_id']        = $params['nest_kind_id'];
                    $position = Db::name("egg_hatch")->where($wh)->max("position");
                    $data = [];
                    $data['user_id']        = $params['user_id'];
                    $data['nest_kind_id']   = $params['nest_kind_id'];
                    $data['kind_id']        = $params['nest_kind_id'];
                    $data['status']         = 1;
                    $data['hatch_num']      = 0;
                    $data['shape']          = 0;
                    $data['is_reap']        = 0;
                    $data['is_buy']         = 1;
                    $data['position']       = $position+1;
                    $result = Db::name("egg_hatch")->insert($data);   
                    $log = [];
                    $log['user_id']          = $params['user_id'];
                    $log['nest_kind_id']     = $params['nest_kind_id'];
                    $log['reward_config_id'] = 0;
                    $log['type']             = 3;
                    $log['number']           = 1;
                    $log['note']             = empty($params['note'])?"商城购买":$params['note'];
                    $log['createtime']       = time();
                    $log_rs = Db::name("egg_nest_log")->insertGetId($log); 
                    if($result && $log_rs){
                        Db::commit();
                    }else{
                        Db::rollback();
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
     * 还原鸡窝
     */
    public function reduction($ids = '')
    {        
        if ($this->request->isPost()) 
        {
            $data = [];
            $data['hatch_num']  = 0;
            $data['shape']      = 5;
            $data['is_reap']    = 0;
            $data['status']     = 1;
            $data['is_give']    = 0;
            $data['uptime']     = time();
            $rs = Db::name("egg_hatch")->where("id",$ids)->update($data);

            if ($rs !== false) {
                $this->success("窝清空成功");
            }
        }
        $this->error("操作失败".$rs);
    }

}
