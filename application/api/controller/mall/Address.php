<?php
namespace app\api\controller\mall;

use app\common\controller\Api;
use think\Validate;
use think\Db;

/**
 * 地址接口
 * @ApiWeigh   (38)
 */
class Address extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 获取地址列表
     *
     * @ApiMethod (GET)
     * 
     * @ApiReturnParams   (name="id", type="integer", description="地址ID")
     * @ApiReturnParams   (name="real_name", type="string", description="姓名")      
     * @ApiReturnParams   (name="phone", type="string", description="手机号")      
     * @ApiReturnParams   (name="area", type="string", description="地区")
     * @ApiReturnParams   (name="address", type="string", description="详细地址")   
     * @ApiReturnParams   (name="is_default", type="integer", description="默认地址 1=是 0=否")     
     */
    public function getList()
    {
        $list = Db::name("user_address")->field(["create_time"],true)
                    ->where("user_id",$this->auth->id)
                    ->order("is_default desc")
                    ->select();
        $this->success('',$list);
    }

    /**
     * 获取详情
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="地址ID") 
     * 
     * @ApiReturnParams   (name="real_name", type="string", description="姓名")      
     * @ApiReturnParams   (name="phone", type="string", description="手机号")      
     * @ApiReturnParams   (name="area", type="string", description="地区")
     * @ApiReturnParams   (name="address", type="string", description="详细地址")   
     * @ApiReturnParams   (name="is_default", type="integer", description="默认地址 1=是 0=否")     
     */
    public function getDetail()
    {
        $id = $this->request->post("id","");

        $wh = [];
        $wh['id']       = $id;
        $wh['user_id']  = $this->auth->id;
        $info = Db::name("user_address")->field(["user_id","create_time"],true)->where($wh)->find();
        $this->success('',$info);
    }

    /**
     * 设置默认
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="地址ID")
     * 
     */
    public function setDefault()
    {
        $id = $this->request->post("id","");

        $rs = Db::name("user_address")->where("user_id",$this->auth->id)->update(['is_default'=>0]);
        if($rs){
            $wh = [];
            $wh['id']       = $id;
            $wh['user_id']  = $this->auth->id;
            $rss = Db::name("user_address")->where($wh)->update(['is_default'=>1]);  
            if($rss){
                $this->success("设置成功");
            }
        }        
        $this->error('设置失败,请重试');
    }

    /**
     * 删除地址
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="地址ID")
     */
    public function del()
    {
        $id           = $this->request->post("id","");

        $wh = [];
        $wh['id']       = $id;
        $wh['user_id']  = $this->auth->id;
        $rs = Db::name("user_address")->where($wh)->delete();
        if($rs){
            $this->success("删除成功");
        }else{
            $this->error("删除失败,请重试");
        }
    }
    
    /**
     * 添加/编辑地址
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="id", type="integer", description="地址ID（编辑时候传）")
     * @ApiParams   (name="real_name", type="string", description="姓名")      
     * @ApiParams   (name="phone", type="string", description="手机号")      
     * @ApiParams   (name="area", type="string", description="地区")
     * @ApiParams   (name="address", type="string", description="详细地址")   
     * @ApiParams   (name="is_default", type="integer", description="默认地址 1=是 0=否")     
     */
    public function save()
    {
        $id             = $this->request->post("id","");
        $real_name      = $this->request->post("real_name","");
        $phone          = $this->request->post("phone","");
        $area           = $this->request->post("area","");
        $address        = $this->request->post("address","");
        $is_default     = $this->request->post("is_default",0);

        if(empty($real_name) || empty($area) || empty($address)){            
            $this->error("参数不正确,请检查");
        }

        if (!Validate::regex($phone, "^1\d{10}$")) {
            $this->error('手机号错误');
        }

        $num = Db::name("user_address")->where("user_id",$this->auth->id)->count();
        if($num > 10){
            $this->error("地址不能多于10条记录");
        }

        if($is_default == 1){
            Db::name("user_address")->where("user_id",$this->auth->id)->update(['is_default'=>0]);
        }

        $data = [];
        $data['area']           = $area;
        $data['address']        = $address;
        $data['real_name']      = $real_name;
        $data['phone']          = $phone;
        $data['is_default']     = $is_default;
        if(empty($id))
        {
            $data['user_id']        = $this->auth->id;
            $data['create_time']    = time();
            $rs = Db::name("user_address")->insertGetId($data);
            if(!empty($rs)){
                $this->success("添加成功");
            } else{
                $this->error("添加失败,请重试");
            } 
        } else{
            $wh = [];
            $wh['id']       = $id;
            $wh['user_id']  = $this->auth->id;
            $rs = Db::name("user_address")->where($wh)->update($data);
            if(!empty($rs)){
                $this->success("更新成功");
            } else{
                $this->error("更新失败,请重试");
            } 
        }

    }
}