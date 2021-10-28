<?php

namespace app\admin\controller\service;

use app\common\controller\Backend;
use think\Db;

/**
 * 客服页面
 *
 * @icon fa fa-circle-o
 */
class Index extends Backend
{
    
    /**
     * News模型对象
     */
    protected $model = null;
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();

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
        $result = Db::name("admin")->field("id,nickname,avatar")->where("id",$this->auth->id)->find();
        $data = [];
        $data['id']             = "kefu".$result['id'];
        $data['nickname']       = $result['nickname'];
        $data['avatar']         = $result['avatar'] ? cdnurl($result['avatar'], true) : letter_avatar($result['nickname']);
        $data['group']          = 1;
        // $data['socket_server']  = 'egg.snalans.com/ws';
        $data['socket_server']  = '127.0.0.1:8282';
        $this->view->assign("uinfo",json_encode($data));
        $this->view->assign("status",1);
        $this->view->assign("word",[]);
        $this->view->assign("title","客服聊天窗口");
        $this->view->assign("nickname",$result['nickname']);
        return $this->view->fetch();
    }
}
