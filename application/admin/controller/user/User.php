<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;
use fast\Random;
use think\Validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\Db;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,mobile';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
    }

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
                ->with(['puser','levels','attestation'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
                $v->getRelation('puser')->visible(['mobile']);
                $v->getRelation('levels')->visible(['title']);
                $v->getRelation('attestation')->visible(['name']);
                $wh = [];
                $wh['c.ancestral_id']   = $v->id;
                $wh['c.level']          = ['<',4];
                $wh['u.status']         = 'normal';
                $wh['u.is_attestation'] = 1;
                $sum = Db::name("membership_chain")->alias("c")
                        ->join("user u","u.id=c.user_id","LEFT")
                        ->where($wh)
                        ->sum("u.valid_number");
                $v->total_valid_number = $sum;
                $wh = [];
                $wh['pid']              = $v->id;
                $v->team_number = Db::name("user")->where($wh)->count();
                if($v->is_attestation != 1){
                    $v->levels['title'] = '普通会员';
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

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
            $this->token();
        }
        return parent::add();
    }

    /**
     * 查看详情
     */
    public function see($ids = null)
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
        $level_str = "普通用户";
        if($row['is_attestation']==1){
            $title = Db::name("user_level_config")->where("level",$row['level'])->value("title");
            $level_str = empty($title)?"农民":$title;
        }
        $row['level'] = $level_str;
        $row['avatar'] = $row['avatar']?$row['avatar']:letter_avatar($row['nickname']);
        $this->view->assign("row", $row);
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
                try {
                    if (!Validate::is($params['password'], '\S{6,16}')) {
                        exception(__("Please input correct password"));
                    }
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

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

    /**
     * 修改状态
     */
    public function status($ids = "")
    {
        $row = $this->model->get($ids);
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
                    // $params['note'] = $this->auth->username.":".$params['note']." >> ".$row['note'];
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
                    if($params['status'] == 'hidden'){
                        Db::name("user_token")->where("user_id",$ids)->delete();
                        $userLevelConfig = new \app\common\model\UserLevelConfig();
                        $wh = [];
                        $wh['user_id'] = $ids;
                        $wh['level']   = ['<=',3];
                        $plist = Db::name("membership_chain")->where($wh)->order("level","ASC")->select();
                        if(!empty($plist)){
                            foreach ($plist as $key => $value) {                         
                                $userLevelConfig->update_vip($value['ancestral_id']);
                            }
                        }
                    }
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
     * 修改等级
     */
    public function level($ids = null)
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
        $row['level_str'] = $row['is_attestation']==1?"农民":"普通用户";
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /*
     * 查询会员信息
     */
    public function getUserInfo()
    {
        $mobile = input("mobile","");
        if (Validate::regex($mobile, "^1\d{10}$")) {
            $info = Db::name("user")->field("id,mobile as name,avatar,loginip as ip")->where("mobile",$mobile)->find();
            if(!empty($info)){
                if(empty($info['avatar'])){
                    $info['avatar'] = "data:image/png;base64,/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFsaXR5ID0gOTAK/9sAQwADAgIDAgIDAwMDBAMDBAUIBQUEBAUKBwcGCAwKDAwLCgsLDQ4SEA0OEQ4LCxAWEBETFBUVFQwPFxgWFBgSFBUU/9sAQwEDBAQFBAUJBQUJFA0LDRQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQU/8AAEQgANAA0AwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+t+KMij8qACTgcmgAyKOK9U8KfCu2FrHc6yGlmcbhbBiqp7MRyT/AJ5robz4ceH7uEoLIQNjiSFyGH64/OgDwrIo4rf8X+EZ/Cl+sbN51tKCYpsYz6g+4rA/KgAyKKBRQAma2vB0Mdz4p0yOUAoZ1OD0OOQPzFY2PapbS5ksrqK4hO2WJw6H0IORQB9LZNJk1jeF/E9r4o08TwELMoAmhPVG/qPQ1tHgZOABQBxnxXhjl8KF3A3xzIUPucg/oTXi+a7v4m+MIdbli06ybzLWBt7yjo79OPYc8981wmPagABooxRQAcV2PgHwKfEsrXd2GTTomxgHBlb0B9PU/wCRyVvA91cRQRgmSRgij1JOBX0XpGmw6PpltZQjEcKBc+p7n8Tk0AS2djb6fbrBbQpBCg4SMYFTYoyKMigDl/F3gOz8SwPLGi2+oAZWZRgOfRvX69R+leJXdpLY3UtvPGY5omKOh6givpXIryv4waMsN3aanGuPOHlS47sB8p/LI/CgDzjiil/OigDa8GIJPFelBhkeep/EHIr6B9aKKADuaDRRQACuL+LKB/CgJGStwhH1wR/WiigDxgciiiigD//Z";
                }
                $this->success('success','',$info);
            }
        }
        $this->error("用户不存在");
    }

    /**
     * 编辑信息
     */
    public function info($ids = null)
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
                $new_number = $row['valid_number']+$params['change_number'];
                
                if($new_number < 0){                    
                    $this->error('变动数量超出已有的数量');
                }
                $params['valid_number'] = $new_number;

                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
              
                    $note = "管理员：".$this->auth->username." ".$params['note'];
                    $log = Db::name("egg_valid_number_log")->insert([
                        'user_id'=>$row['id'],
                        'type'=>3,
                        'number'=>$params['change_number'],
                        'before'=>$row['valid_number'],
                        'after'=>$new_number,
                        'note'=>$note,
                        'add_time'=>time(),
                    ]);
                    $result = $row->allowField(true)->save($params);

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

    /**
     * 清退所有用户
     */
    public function all_sign_out()
    {
        if (!$this->request->isAjax()) {
            $this->error(__("Invalid parameters"));
        }
        $rs = Db::name("user_token")->where("user_id",">",1)->delete();
        if($rs){
            $this->success("清退成功");
        }else{
            $this->error("清退失败");
        }
    }

    /*
     * 查询多账号同IP登录
     */
    public function get_account()
    {
        set_time_limit(0);
        $date = input("date",date("Y-m-d"));
        $num = input("num",10);
        $wh = [];
        $wh['logintime'] = ['>=',strtotime($date)];
        $list = Db::name("user")->alias("u")
                ->field("u.mobile,u.loginip,u.logintime,uu.mobile as p_mobile")
                ->join("user uu","uu.id=u.pid")
                ->order("loginip","ASC")
                ->select(function($query) use($wh,$num){
                    $list_ip = Db::name("user")
                    ->where($wh)
                    ->group('loginip')
                    ->having("count(id)>$num")
                    ->column("loginip"); 
                    $query->where("u.loginip",'in',$list_ip);
                });

        $cols_arr = [
            "A"     => '手机号',
            "B"     => '登录IP',   
            "C"     => '登录时间',  
            "D"     => '上级手机号',  
        ];
        $newExcel = new Spreadsheet();  //创建一个新的excel文档
        $objSheet = $newExcel->getActiveSheet();  //获取当前操作sheet的对象        
        $objSheet->setTitle('同IP登录数据表');  //设置当前sheet的标题
        //设置第一栏的标题
        foreach ($cols_arr as $key => $value) 
        {
            //设置宽度为true,不然太窄了
            $newExcel->getActiveSheet()->getColumnDimension($key)->setWidth(20);
            $objSheet->setCellValue($key.'1', $value);
        }

        if(!empty($list))
        {          
            $num=1;
            foreach ($list as $k => $val) 
            {         
                $num++;
                $objSheet->setCellValue("A".$num, $val['mobile']);
                $objSheet->setCellValue("B".$num, $val['loginip']);
                $objSheet->setCellValue("C".$num, date("Y-m-d H:i:s",$val['logintime']));
                $objSheet->setCellValue("D".$num, $val['p_mobile']);
            }
        }else{
            $objSheet->setCellValue("A2", "数据为空");
        }

        /*--------------下面是设置其他信息------------------*/
        ob_end_clean();
        $excel_type = 'Xlsx';
        if($excel_type == "Xls"){            
            header('Content-Type: application/vnd.ms-excel');
            header("Content-Disposition: attachment;filename=". date('Y-m-d') .".xlsx");
            header('Cache-Control: max-age=0');
            $objWriter = IOFactory::createWriter($newExcel, 'Xls');
        }else{            
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: inline;filename=". date('Y-m-d') .".xlsx");
            header('Cache-Control: max-age=0');
            $objWriter = IOFactory::createWriter($newExcel, 'Xlsx');
        }
        $objWriter->save('php://output');
        exit();

    }
}
