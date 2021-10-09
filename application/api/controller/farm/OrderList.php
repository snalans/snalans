<?php
namespace app\api\controller\farm;

use app\common\controller\Api;
use think\Db;

/**
 * 订单接口
 * @ApiWeigh   (28)
 */
class OrderList extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    public    $alldate = 3600*24;

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取订单列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="type", type="integer", description="买卖类型默认： 1=买 2=卖")
     * @ApiParams   (name="status", type="integer", description="状态默认：9=全部 0=待付款 1=完成 2=待确认 3=申诉 4=无效 ")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="order_sn", type="string", description="订单号")
     * @ApiReturnParams   (name="name", type="string", description="商品名称")
     * @ApiReturnParams   (name="price", type="integer", description="单价")
     * @ApiReturnParams   (name="number", type="integer", description="数量")
     * @ApiReturnParams   (name="amount", type="integer", description="总价")
     * @ApiReturnParams   (name="status", type="integer", description="状态 0=待付款 1=完成 2=待确认 3=申诉 4=无效")
     * @ApiReturnParams   (name="createtime", type="integer", description="创建时间")
     */
    public function getOrderList()
    {
        $type       = $this->request->get("type",1);
        $status     = $this->request->get("status",9);
        $page       = $this->request->get("page",1);        
        $per_page   = $this->request->get("per_page",10);

        $wh = [];
        if($type == 1){    
            $wh['buy_user_id'] = $this->auth->id;
        }else{
            $wh['sell_user_id'] = $this->auth->id;
        }
        if($status != 9){
            $wh['status'] = $status;
        }
        $list = Db::name("egg_order")
                ->field("order_sn,name,price,number,amount,status,createtime")
                ->where($wh)
                ->paginate($per_page)->each(function($item,$index){
                    $item['createtime'] = date("Y-m-d H:i",$item['createtime']);
                    return $item;
                });
        $this->success('',$list);
    }

    /**
     * 获取订单详情
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="type", type="int", description="买卖类型 1=买 2=卖")
     * 
     * @ApiReturnParams   (name="order_sn", type="string", description="订单号")
     * @ApiReturnParams   (name="name", type="string", description="商品名称")
     * @ApiReturnParams   (name="price", type="string", description="单价")
     * @ApiReturnParams   (name="number", type="string", description="数量")
     * @ApiReturnParams   (name="amount", type="string", description="总金额")
     * @ApiReturnParams   (name="sell_serial_umber", type="string", description="卖家编号")
     * @ApiReturnParams   (name="buy_serial_umber", type="string", description="买家编号")
     * @ApiReturnParams   (name="attestation_name", type="string", description="卖家收款名称")
     * @ApiReturnParams   (name="attestation_address", type="string", description="卖家收款地址")
     * @ApiReturnParams   (name="note", type="string", description="备注")
     * @ApiReturnParams   (name="pay_time", type="string", description="付款时间")
     * @ApiReturnParams   (name="createtime", type="string", description="下单时间")
     * 
     * @ApiReturnParams   (name="type", type="integer", description="类型 1=支付宝 2=微信 3=钱包")
     * @ApiReturnParams   (name="account", type="string", description="账号")
     * @ApiReturnParams   (name="image", type="string", description="地址")
     */
    public function getOrderDetail()
    {
        $order_sn = $this->request->post("order_sn","");
        $type     = $this->request->post("type",1);

        $wh = [];
        $wh['order_sn'] = $order_sn;
        if($type == 1){            
            $field = "order_sn,name,price,number,amount,sell_user_id,sell_serial_umber,attestation_name,attestation_address,status,pay_img,pay_time,note,createtime";

            $wh['buy_user_id'] = $this->auth->id;
        }else{
            $field = "order_sn,name,price,number,amount,buy_serial_umber,status,pay_img,pay_time,note,createtime";

            $wh['sell_user_id'] = $this->auth->id;
        }
        $data = Db::name("egg_order")->field($field)->where($wh)->find();
        if(empty($data)){
            $this->error("订单不存在。");
        }
        $data['pay_time'] = $data['pay_time']?date("Y-m-d H:i",$data['pay_time']):"";
        $data['createtime'] = $data['createtime'];

        if($type == 1 && $data['status'] == 0){
            $data['pay_list'] = Db::name("egg_charge_code")->field("type,account,image")->where("user_id",$data['sell_user_id'])->select();
        }
        $this->success('',$data);
    }

    /**
     * 买家上传支付照片,确认支付
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="attestation_name", type="string", description="卖家收款名称")
     * @ApiParams   (name="attestation_address", type="string", description="卖家收款地址")
     * @ApiParams   (name="pay_img", type="string", description="付款截图")
     */
    public function confirm()
    {
        $order_sn               = $this->request->post("order_sn","");
        $attestation_name       = $this->request->post("attestation_name","");
        $attestation_address    = $this->request->post("attestation_address","");
        $pay_img                = $this->request->post("pay_img","");

        $data = [];
        $data['attestation_name']    = $attestation_name;
        $data['attestation_address'] = $attestation_address;
        $data['pay_img']             = $pay_img;
        $data['status']              = 2;
        $data['pay_time']            = time();

        $wh = [];
        $wh['order_sn']     = $order_sn;
        $wh['status']       = 0;
        $wh['buy_user_id']  = $this->auth->id;
        $rs = Db::name("egg_order")->where($wh)->update($data);
        if($rs){
            $this->success('确认成功');
        }else{
            $this->error("操作失败,请重试。");
        }
    }
    
    /**
     * 卖家审核付款情况，确认支付或者申诉
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="status", type="integer", description="状态 1=完成 3=申诉")   
     * @ApiParams   (name="note", type="string", description="申诉理由")     
     */
    public function complete()
    {
        $order_sn = $this->request->post("order_sn","");
        $status   = $this->request->post("status",1);
        $note     = $this->request->post("note","");

        if(!in_array($status,[1,3])){            
            $this->error("参数不正确,请重试。");
        }else if($status == 3 && empty($note)){
            $this->error("请填写申诉理由。");
        } 

        Db::startTrans();
        try {
            $rs_add = true;
            $log_add = true;   
            $wh = [];
            $wh['order_sn']     = $order_sn;
            $wh['status']       = 2;
            $wh['sell_user_id'] = $this->auth->id;
            $rs = Db::name("egg_order")->where($wh)->update(['status'=>$status,'note'=>$note]);
            if($status == 1){
                $result = Db::name("egg_order")->field("buy_user_id,kind_id,number")->where("order_sn",$order_sn)->find();
                $wh = [];
                $wh['user_id'] = $result['buy_user_id'];
                $wh['kind_id'] = $result['kind_id'];
                $rs_add = Db::name("egg")->where($wh)->setInc('number',$result['number']);
                $log_add = \app\admin\model\egg\Log::saveLog($result['buy_user_id'],$result['kind_id'],1,$order_sn,$result['number'],"订单成交");
            }
            if($rs && $rs_add && $log_add){
                Db::commit();
                $this->success('确认成功');
            }else{
                Db::rollback();
                $this->error("操作失败,请重试。");
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }
}