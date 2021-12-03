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
     * @ApiMethod (POST)
     * @ApiParams   (name="type", type="integer", description="买卖类型默认： 1=买 2=卖")
     * @ApiParams   (name="status", type="integer", description="状态默认：9=全部 0=待付款 1=完成 2=待确认 3=申诉 4=无效（撤单） 5=挂单  6退款")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="order_sn", type="string", description="订单号")
     * @ApiReturnParams   (name="name", type="string", description="商品名称")
     * @ApiReturnParams   (name="price", type="integer", description="单价")
     * @ApiReturnParams   (name="number", type="integer", description="数量")
     * @ApiReturnParams   (name="amount", type="integer", description="总价")
     * @ApiReturnParams   (name="status", type="integer", description="状态 0=待付款 1=完成 2=待确认 3=申诉 4=无效（撤单） 5=挂单  6退款")
     * @ApiReturnParams   (name="refund_status", type="int", description="退款类型：1=超时未打款退款  0=申诉退款")
     * @ApiReturnParams   (name="createtime", type="integer", description="创建时间")
     */
    public function getOrderList()
    {
        $type       = $this->request->post("type",1);
        $status     = $this->request->post("status",9);
        $page       = $this->request->post("page",1);        
        $per_page   = $this->request->post("per_page",10);

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
                ->field("order_sn,name,price,number,amount,status,refund_status,createtime")
                ->where($wh)
                ->order("createtime","desc")
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
     * @ApiReturnParams   (name="attestation_type", type="string", description="类型 1=支付宝 2=微信 3=钱包")
     * @ApiReturnParams   (name="attestation_image", type="string", description="支付图片地址")
     * @ApiReturnParams   (name="attestation_account", type="string", description="卖家收款地址")
     * @ApiReturnParams   (name="kind_id", type="int", description="蛋类型ID")
     * @ApiReturnParams   (name="status", type="integer", description="状态 0=待付款 1=完成 2=待确认 3=申诉 4=无效（撤单） 5=挂单  6退款")
     * @ApiReturnParams   (name="refund_status", type="int", description="退款类型：1=超时未打款退款  0=申诉退款")
     * @ApiReturnParams   (name="note", type="string", description="备注")
     * @ApiReturnParams   (name="pay_time", type="string", description="付款时间")
     * @ApiReturnParams   (name="createtime", type="string", description="下单时间")
     * 
     * @ApiReturnParams   (name="id", type="integer", description="收款ID")
     * @ApiReturnParams   (name="type", type="integer", description="类型 1=支付宝 2=微信 3=钱包 4=银行卡")
     * @ApiReturnParams   (name="account", type="string", description="账号")
     * @ApiReturnParams   (name="image", type="string", description="地址")
     * @ApiReturnParams   (name="name", type="string", description="姓名")
     * @ApiReturnParams   (name="mobile", type="string", description="手机号")
     * @ApiReturnParams   (name="open_bank", type="string", description="开户行")
     */
    public function getOrderDetail()
    {
        $order_sn = $this->request->post("order_sn","");
        $type     = $this->request->post("type",1);

        $wh = [];
        $wh['order_sn'] = $order_sn;
        if($type == 1){            
            $field = "order_sn,name,price,number,amount,sell_user_id,sell_serial_umber,attestation_type,attestation_image,attestation_account,kind_id,status,refund_status,pay_img,pay_time,note,createtime";

            $wh['buy_user_id'] = $this->auth->id;
        }else{
            $field = "order_sn,name,price,number,amount,buy_serial_umber,kind_id,status,refund_status,pay_img,pay_time,note,createtime";

            $wh['sell_user_id'] = $this->auth->id;
        }
        $data = Db::name("egg_order")->field($field)->where($wh)->find();
        if(empty($data)){
            $this->error("订单不存在。");
        }
        $data['pay_time'] = $data['pay_time']?date("Y-m-d H:i",$data['pay_time']):"";
        $data['createtime'] = date("Y-m-d H:i",$data['createtime']);
        $data['pay_list'] = "";
        if($type == 1 && $data['status'] == 0){
            $data['pay_list'] = Db::name("egg_charge_code")->field(["user_id","add_time"],true)->where("user_id",$data['sell_user_id'])->select();
        }
        $this->success('',$data);
    }

    /**
     * 买家上传支付照片,确认支付
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="charge_code_id", type="string", description="收款信息ID")
     * @ApiParams   (name="pay_img", type="string", description="付款截图")
     * @ApiParams   (name="paypwd", type="string", description="支付密码")
     */
    public function confirm()
    {
        $order_sn           = $this->request->post("order_sn","");
        $charge_code_id     = $this->request->post("charge_code_id","");
        $pay_img            = $this->request->post("pay_img","");
        $paypwd             = $this->request->post('paypwd');

        $wh = [];
        $wh['order_sn']     = $order_sn;
        $wh['status']       = 0;
        $wh['buy_user_id'] = $this->auth->id;
        $order = Db::name("egg_order")->field("id,sell_user_id,sell_mobile")->where($wh)->find();
        if(empty($order)){            
            $this->error("操作无效");
        }

        if(empty($pay_img)){
            $this->error("付款证明不能为空");
        }        

        $wh = [];
        $wh['id']       = $charge_code_id;
        $wh['user_id']  = $order["sell_user_id"];
        $info = Db::name("egg_charge_code")->where($wh)->find();
        if(empty($info)){
            $this->error("收款方式错误");
        }

        $auth = new \app\common\library\Auth();
        if ($this->auth->paypwd != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error('支付密码错误');
        }

        $data = [];
        $data['attestation_type']    = $info['type'];
        $data['attestation_image']   = $info['image'];
        $data['attestation_account'] = $info['account'];
        $data['pay_img']             = $pay_img;
        $data['status']              = 2;
        $data['pay_time']            = time();

        $rs = Db::name("egg_order")->where("id",$order['id'])->update($data);
        if($rs){      
            \app\common\library\Hsms::send($order['sell_mobile'], '','order');
            $this->success('提交成功,等待确认');
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
        }else if($status == 3){
            $num = mb_strlen($note);
            if($num < 6 || $num > 200){
                $this->error("理由字符数需要在6~200之间");
            }
        }         
        
        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        $wh = [];
        $wh['order_sn']     = $order_sn;
        $wh['status']       = 2;
        $wh['sell_user_id'] = $this->auth->id;
        $info = Db::name("egg_order")->where($wh)->find();
        if(empty($info) || $info['number']<=0 || $info['rate']<=0 || $info['amount']<=0){
            $this->error("无效操作");
        }

        Db::startTrans();
        try {
            $grs    = true;
            $log_rs = true;
            $ors = Db::name("egg_order")->where("id",$info['id'])->update(['over_time'=>time(),'status'=>$status,'note'=>$note]);
            if($status == 1){
                $result = Db::name("egg_order")->field("buy_user_id,buy_mobile,kind_id,number")->where("order_sn",$order_sn)->find();
                $wh = [];
                $wh['user_id'] = $result['buy_user_id'];
                $wh['kind_id'] = $result['kind_id'];
                $before = Db::name("egg")->where($wh)->value('number');
                $grs = Db::name("egg")->where($wh)->setInc('number',$result['number']);

                $log_rs = Db::name("egg_log")->insert(['user_id'=>$result['buy_user_id'],'kind_id'=>$result['kind_id'],'type'=>1,'order_sn'=>$order_sn,'number'=>$result['number'],'before'=>$before,'after'=>($before+$result['number']),'note'=>"订单成交",'createtime'=>time()]);
            }
            if($ors && $grs && $log_rs){
                Db::commit();                
            }else{
                Db::rollback();
                $this->error('确认失败,请重试'); 
            }           
            
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }   
        if($status == 1){
            $this->success('完成交易'); 
        }else{
            $this->success('申诉成功,等待审核'); 
        }
    }
}