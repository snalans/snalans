<?php

namespace app\admin\controller\general;

use app\admin\model\Admin;
use app\common\controller\Backend;
use fast\Random;
use think\Session;
use think\Validate;
use think\Db;

/**
 * 个人配置
 *
 * @icon fa fa-user
 */
class Profile extends Backend
{

    protected $searchFields = 'id,title';

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $this->model = model('AdminLog');
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->where('admin_id', $this->auth->id)
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 更新个人信息
     */
    public function update()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $params = array_filter(array_intersect_key(
                $params,
                array_flip(array('email', 'nickname', 'password', 'avatar'))
            ));
            unset($v);
            if (!Validate::is($params['email'], "email")) {
                $this->error(__("Please input correct email"));
            }
            if (isset($params['password'])) {
                if (!Validate::is($params['password'], "/^[\S]{6,16}$/")) {
                    $this->error(__("Please input correct password"));
                }
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']) . $params['salt']);
            }
            $exist = Admin::where('email', $params['email'])->where('id', '<>', $this->auth->id)->find();
            if ($exist) {
                $this->error(__("Email already exists"));
            }
            if ($params) {
                $admin = Admin::get($this->auth->id);
                $admin->save($params);
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                Session::set("admin", $admin->toArray());
                $this->success();
            }
            $this->error();
        }
        return;
    }

    /**
     * 绑定谷歌命令
     */
    public function google()
    {
        $ga = new \app\admin\model\PHPGangsta_GoogleAuthenticator;
        $secret = Db::name("admin")->where("id",$this->auth->id)->value("google_secret"); 
        $is_bing = empty($secret)?0:1;  
        if ($this->request->isPost()) {
            $google_code    = input("google_code",'');
            $secret         = input("secret",'');
            if(empty($google_code)){
                $this->error("验证码不能为空!");
            }            
            if(empty($is_bing)){
                // 2 = 2*30sec clock tolerance
                $checkResult = $ga->verifyCode($secret, $google_code, 2);
                if($checkResult){
                    $rs = Db::name("admin")->where("id",$this->auth->id)->update(["google_secret"=>$secret]);
                    if($rs){
                        return $this->success("绑定成功!");
                    }
                } 
            } 
            return $this->error("绑定失败!");
        }
        if(empty($is_bing)){
            $secret = $ga->createSecret();
            $this->assign("secret",$secret);
            $qrCodeUrl = $ga->getQRCodeGoogleUrl($_SERVER["REQUEST_SCHEME"].'://'.$_SERVER["HTTP_HOST"].'@admin-'.$this->auth->username, $secret);  
            $this->assign("qrCodeUrl",$qrCodeUrl);
        }
        $this->assign("is_bing",$is_bing);
        return $this->view->fetch();
    }

    /**
     * 解绑绑定谷歌命令
     */
    public function unbind()
    {
        if ($this->request->isPost()) {
            $ga = new \app\admin\model\PHPGangsta_GoogleAuthenticator;
            $google_code    = input("google_code",'');
            $secret         = input("secret",'');
            if(empty($google_code)){
                $this->error("验证码不能为空!");
            }            
            // 2 = 2*30sec clock tolerance
            $secret = Db::name("admin")->where("id",$this->auth->id)->value("google_secret"); 
            $checkResult = $ga->verifyCode($secret, $google_code, 2);
            if($checkResult){
                $rs = Db::name("admin")->where("id",$this->auth->id)->update(["google_secret"=>'']);
                if($rs){
                    return $this->success("解除绑定成功。");
                }
            }             
        }
        return $this->error("解除绑定失败!");
    }
}
