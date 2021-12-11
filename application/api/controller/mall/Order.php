<?php
namespace app\api\controller\mall;

use app\common\controller\Api;
use think\Validate;
use think\Config;
use think\Db;

/**
 * 商城订单接口
 * @ApiWeigh   (38)
 */
class Order extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 获取商城订单列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="type", type="integer", description="买卖类型默认： 1=买 2=卖")
     * @ApiParams   (name="status", type="int", description="状态 9=全部订单 2=待发货 3=待收货 1=交易完成 8=售后订单")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="order_sn", type="int", description="订单号")
     * @ApiReturnParams   (name="title", type="string", description="商品名称")
     * @ApiReturnParams   (name="image", type="string", description="商品图片")    
     * @ApiReturnParams   (name="egg_image", type="string", description="蛋图片")     
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")   
     * @ApiReturnParams   (name="number", type="integer", description="兑换数量")     
     * @ApiReturnParams   (name="rate", type="integer", description="手续费")   
     * @ApiReturnParams   (name="name", type="string", description="价格单位")  
     * @ApiReturnParams   (name="total_price", type="integer", description="总价格")     
     * @ApiReturnParams   (name="is_virtual", type="integer", description="是否虚拟商品 0=否 1=是")      
     * @ApiReturnParams   (name="status", type="string", description="状态 1=交易完成 2=待发货 3=待收货 5=申请退款 6=退款完成 7=申诉中")     
     * @ApiReturnParams   (name="status_str", type="string", description="状态名称") 
     * @ApiReturnParams   (name="serial_number", type="string", description="会员编号")  
     */
    public function getOrderList()
    {
        $type           = $this->request->get('type',1);
        $status         = $this->request->get('status',9);
        $page           = $this->request->get("page",1);        
        $per_page       = $this->request->get("per_page",15);

        $wh = [];
        if($type == 1){
            $wh['mo.buy_user_id'] = $this->auth->id; 
            $wh['mo.buy_del']     = 0; 
            $wh_str = "mo.buy_user_id";
        }else{
            $wh['mo.sell_user_id'] = $this->auth->id;
            $wh['mo.sell_del']     = 0; 
            $wh_str = "mo.sell_user_id";
        }
        if($status != 9){
            if($status == 8){
                $wh['mo.status'] = ['in',[5,7]];
            }else if($status == 1){
                $wh['mo.status'] = ['in',[1,6]];
            }else{
                $wh['mo.status'] = $status;
            }            
        }

        $list = Db::name("mall_order")->alias("mo")
                    ->field("mo.order_sn,mo.title,mo.image,mo.price,mo.number,mo.rate,mo.total_price,mo.status,mo.is_virtual,u.serial_number,ek.name,ek.image as egg_image")
                    ->join("user u","u.id=$wh_str","LEFT")
                    ->join("egg_kind ek","ek.id=mo.kind_id","LEFT")
                    ->where($wh)
                    ->order("mo.add_time desc")
                    ->paginate($per_page)->each(function($item){
                        if($item['status'] == 1){
                            $item['status_str'] = "交易完成";
                        }else if($item['status'] == 2){
                            if($item['is_virtual']==1){
                                $item['status_str'] = "待充值";
                            }else{
                                $item['status_str'] = "待发货";
                            }   
                        }else if($item['status'] == 3){
                            $item['status_str'] = "待收货";
                        }else if($item['status'] == 5){
                            $item['status_str'] = "申请退款";
                        }else if($item['status'] == 6){
                            $item['status_str'] = "退款完成";
                        }else if($item['status'] == 7){
                            $item['status_str'] = "申诉中";
                        }else{
                            $item['status_str'] = "待付款";
                        }
                        return $item;
                    });
        $this->success('',$list);
    }

    /**
     * 获取商品订单详情
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="type", type="integer", description="买卖类型默认： 1=买 2=卖")
     * @ApiParams   (name="order_sn", type="integer", description="订单号") 
     * 
     * @ApiReturnParams   (name="order_sn", type="int", description="订单号")
     * @ApiReturnParams   (name="title", type="string", description="商品名称")
     * @ApiReturnParams   (name="image", type="string", description="商品图片")    
     * @ApiReturnParams   (name="egg_image", type="string", description="蛋图片")     
     * @ApiReturnParams   (name="price", type="integer", description="支付价格")   
     * @ApiReturnParams   (name="name", type="string", description="价格单位")     
     * @ApiReturnParams   (name="rate", type="integer", description="手续费")   
     * @ApiReturnParams   (name="total_price", type="integer", description="总价格") 
     * @ApiReturnParams   (name="number", type="integer", description="兑换数量")   
     * @ApiReturnParams   (name="status", type="int", description="状态 1=交易完成 2=待发货 3=待收货 5=申请退款 6=退款完成 7=申诉中")     
     * @ApiReturnParams   (name="status_str", type="string", description="状态名称") 
     * @ApiReturnParams   (name="contactor", type="integer", description="联系人姓名")
     * @ApiReturnParams   (name="contactor_phone", type="integer", description="联系电话")
     * @ApiReturnParams   (name="address", type="integer", description="收货地址")
     * @ApiReturnParams   (name="express_name", type="integer", description="快递名称")          
     * @ApiReturnParams   (name="express_no", type="string", description="快递单号")      
     * @ApiReturnParams   (name="received_time", type="string", description="收货时间")    
     * @ApiReturnParams   (name="recharge_account", type="string", description="充值账号")    
     * @ApiReturnParams   (name="is_virtual", type="integer", description="是否虚拟商品 0=否 1=是")  
     * @ApiReturnParams   (name="send_time", type="integer", description="发货时间")       
     * @ApiReturnParams   (name="add_time", type="string", description="下单时间")   
     * @ApiReturnParams   (name="mobile", type="string", description="手机号")      
     * @ApiReturnParams   (name="serial_number", type="string", description="会员编号")  
     * @ApiReturnParams   (name="is_platform", type="string", description="是否平台商品订单 1=是 0=否")  
     */
    public function getOrderDetail()
    {
        $type       = $this->request->post('type',1);
        $order_sn   = $this->request->post("order_sn","");
        
        $wh = [];
        $wh['mo.order_sn'] = $order_sn;
        if($type == 1){
            $wh['mo.buy_user_id'] = $this->auth->id; 
            $wh['mo.buy_del']     = 0; 
            $wh_str = "mo.sell_user_id";
        }else{
            $wh['mo.sell_user_id'] = $this->auth->id;
            $wh['mo.sell_del']     = 0; 
            $wh_str = "mo.buy_user_id";
        }

        $info = Db::name("mall_order")->alias('mo')
                    ->field("mo.order_sn,mo.title,mo.image,mo.sell_user_id,mo.price,mo.number,mo.rate,mo.total_price,ek.name,ek.image as egg_image,mo.status,mo.contactor,mo.contactor_phone,mo.address,mo.express_name,mo.express_no,mo.recharge_account,mo.is_virtual,mo.received_time,mo.send_time,mo.add_time,u.mobile,u.serial_number")
                    ->join("user u","u.id=$wh_str","LEFT")
                    ->join("egg_kind ek","ek.id=mo.kind_id","LEFT")
                    ->where($wh)
                    ->find();
        if(!empty($info)){
            $info['is_platform'] = $info['sell_user_id']==0?1:0;
            if($info['status'] == 1){
                $info['status_str'] = "交易完成";
            }else if($info['status'] == 2){
                if($info['is_virtual']==1){
                    $info['status_str'] = "待充值";
                }else{
                    $info['status_str'] = "待发货";
                }                
            }else if($info['status'] == 3){
                $info['status_str'] = "待收货";
            }else if($info['status'] == 5){
                if($info['sell_user_id'] == 0){
                    $info['status_str'] = "申请中,有问题请联系客服";
                }else{
                    $info['status_str'] = "申请中,有问题请联系对方";
                }
                unset($info['sell_user_id']);
            }else if($info['status'] == 6){
                $info['status_str'] = "退款完成";
            }else if($info['status'] == 7){
                $info['status_str'] = "申诉中,有问题请联系对方";
            }else{
                $info['status_str'] = "待付款";
            }
            $info['send_time']      = empty($info['send_time'])?"":date("Y-m-d H:i",$info['send_time']);
            $info['received_time']  = empty($info['received_time'])?"":date("Y-m-d H:i",$info['received_time']);
            $info['add_time']       = date("Y-m-d H:i",$info['add_time']);
        }
        $this->success('',$info);
    }

    /**
     * 订单发货
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="express_name", type="string", description="快递名称")
     * @ApiParams   (name="express_no", type="string", description="快递单号")
     */
    public function send()
    {
        $order_sn           = $this->request->post("order_sn","");
        $express_name       = $this->request->post("express_name","");
        $express_no         = $this->request->post("express_no","");

        if(empty($order_sn) || empty($express_name) || empty($express_no)){            
            $this->error("参数不正确,请检查");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }
        
        $wh = [];
        $wh['order_sn']     = $order_sn;
        $wh['sell_user_id'] = $this->auth->id;
        $wh['status']       = 2;
        $info = Db::name("mall_order")->field("id,order_sn")->where($wh)->find();
        if(empty($info)){            
            $this->error("无效操作");
        }

        $data = [];
        $data['status']         = 3;
        $data['send_time']      = time();
        $data['express_name']   = $express_name;
        $data['express_no']     = $express_no;
        $rs = Db::name("mall_order")->where($wh)->update($data);
        if($rs){
            $this->success("发货成功");
        }else{
            $this->error("发货失败,请重试");
        }
    }
    
    
    /**
     * 申请退款/确认订单
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="status", type="int", description="状态 1=交易完成 5=申请退款 6=确认退款 7=拒绝退款")
     * @ApiParams   (name="note", type="string", description="申请理由")   
     */
    public function refund()
    {
        $order_sn       = $this->request->post("order_sn","");
        $status         = $this->request->post("status",1);
        $note           = $this->request->post("note","");

        if(empty($order_sn) || !in_array($status,[1,5,6,7])){            
            $this->error("参数不正确,请检查");
        }

        $wh = [];
        $wh['order_sn']     = $order_sn;
        if(in_array($status,[1,5])){
            if($status==1){
                $wh['status'] = 3;
            }else{
                $wh['status'] = ['in',[2,3]];
            }            
            $wh['buy_user_id'] = $this->auth->id;
        }else{
            $wh['status'] = 5;
            $wh['sell_user_id'] = $this->auth->id;
        }
        $result = Db::name("mall_order")->where($wh)->find();
        if(empty($result)){
            $this->error("无效操作");
        }

        $wh = [];
        $wh['order_sn']     = $order_sn;
        if($status == 1){
            $wh['status'] = 3;
            $wh['buy_user_id'] = $this->auth->id;
            $rs = Db::name("mall_order")->where($wh)->update(['status'=>1,'received_time'=>time()]);

            if($result['sell_user_id'] > 0)
            {
                $wh = [];
                $wh['user_id'] = $result['sell_user_id'];
                $wh['kind_id'] = $result['kind_id'];
                $before = Db::name("egg")->where($wh)->value('number');
                $grs = Db::name("egg")->where($wh)->setInc('number',$result['total_price']);
            }else{
                $before = 0;
                $grs = true;
            }

            $log_rs = Db::name("egg_log")->insert(['user_id'=>$result['sell_user_id'],'kind_id'=>$result['kind_id'],'type'=>1,'order_sn'=>$order_sn,'number'=>$result['total_price'],'before'=>$before,'after'=>($before+$result['total_price']),'note'=>"商城订单成交",'createtime'=>time()]);
            if($rs && $grs && $log_rs){
                Db::commit();
                $this->success("交易成功");
            }else{
                Db::rollback();                 
                $this->error("确认失败,请重试");
            }
        } else if($status == 5){
            if($result['is_virtual'] == 1){
                $this->error("虚拟商品不允许退款");
            }
            $wh['status'] = ['in',[2,3]];
            $wh['buy_user_id'] = $this->auth->id;
            $num = mb_strlen($note);
            if($num < 6 || $num > 200){
                $this->error("理由字符数需要在6~200之间");
            }

            $data = [];
            $data['status'] = 5;
            $data['note']   = $note;
            $rs = Db::name("mall_order")->where($wh)->update($data);
            if($rs){
                $this->success("申请成功");
            }else{
                $this->error("申请失败,请重试");
            }
        } else if($status == 6){
            DB::startTrans();  
            $wh['status']       = 5;
            $wh['sell_user_id'] = $this->auth->id;
            $rs = Db::name("mall_order")->where($wh)->update(['status'=>6]);

            $wh = [];
            $wh['user_id'] = $result['buy_user_id'];
            $wh['kind_id'] = $result['kind_id'];
            $number = $result['total_price'] + $result['rate'];
            $before = Db::name("egg")->where($wh)->value('number');
            $grs = Db::name("egg")->where($wh)->setInc('number',$number);

            $log_rs = Db::name("egg_log")->insert(['user_id'=>$result['buy_user_id'],'kind_id'=>$result['kind_id'],'type'=>1,'order_sn'=>$order_sn,'number'=>$result['total_price'],'before'=>$before,'after'=>($before+$result['total_price']),'note'=>"商城订单退款",'createtime'=>time()]);

            $rate_rs = true;    
            if($result['rate']>0){
                $rate_rs = Db::name("egg_log")->insert(['user_id'=>$result['buy_user_id'],'kind_id'=>$result['kind_id'],'type'=>9,'order_sn'=>$order_sn,'number'=>$result['rate'],'before'=>($before+$result['total_price']),'after'=>($before+$number),'note'=>"商城订单退款,返还手续费",'createtime'=>time()]);
            }
            if($rs && $grs && $log_rs && $rate_rs){
                Db::commit();
                $this->success("退款成功");
            }else{
                Db::rollback();   
                $this->error("退款失败,请重试");              
            }
        } else if($status == 7){
            $wh['status'] = 5;
            $wh['sell_user_id'] = $this->auth->id;
            $rs = Db::name("mall_order")->where($wh)->update(['status'=>7]);
            if($rs){
                $this->success("拒绝成功,转入申诉");
            }else{
                $this->error("拒绝失败,请重试");
            }
        } 
    }


    /**
     * 取消退款申请
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     */
    public function cancelRefund()
    {
        $order_sn = $this->request->post("order_sn","");

        if(empty($order_sn)){            
            $this->error("参数不正确,请检查");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }
        
        $wh = [];
        $wh['order_sn']     = $order_sn;
        $wh['buy_user_id']  = $this->auth->id;
        $wh['status']       = 5;
        $info = Db::name("mall_order")->field("id,order_sn")->where($wh)->find();
        if(empty($info)){            
            $this->error("无效操作");
        }
        if(empty($info['express_no'])){
            $data['status'] = 2;
        }else{
            $data['status'] = 3;
        }
        $rs = Db::name("mall_order")->where($wh)->update($data);
        if($rs){
            $this->success("取消成功");
        }else{
            $this->error("取消失败,请重试");
        }
    }

    /**
     * 下单购买
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="id", type="integer", description="商品ID")
     * @ApiParams   (name="number", type="integer", description="数量")
     * @ApiParams   (name="address_id", type="integer", description="地址ID")
     * @ApiParams   (name="recharge_account", type="string", description="充值账户")
     * @ApiParams   (name="paypwd", type="string", description="支付密码")
    */
    public function makeOrder()
    {
        if(empty(Config::get("site.mall_is_open"))){
            $this->error("商城维护中,稍后再来交易...");
        }
        $id         = $this->request->post("id",0);
        $address_id = $this->request->post("address_id",0);
        $recharge_account = $this->request->post("recharge_account","");
        $number     = $this->request->post("number/d",1);
        $paypwd     = $this->request->post('paypwd',"");

        //商品信息
        $wh = [];
        $wh['id']       = $id;
        $wh['status']   = 1;
        $info = Db::name("mall_product")->where($wh)->find();
        if(empty($info)){
            $this->error("无效商品");
        }
        if($info['stock']<=0){
            Db::name("mall_product")->where("id",$id)->update(['status'=>0]);
        }
        if($info['stock'] < $number){
            $this->error("商品库存不够");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }

        if($info['user_id'] == $this->auth->id){
            $this->error("不能购买给自己的产品");
        }

        $egg_where = [];
        $egg_where['user_id'] = $this->auth->id;
        $egg_where['kind_id'] = $info['kind_id'];
        $egg_num = Db::name("egg")->where($egg_where)->value('number');

        //蛋数量判断
        $sell_egg = $info['price']*$number;
        $rate = 0;
        $rate_config = Db::name("egg_kind")->where("id",$info['kind_id'])->value("rate_config");
        $rate = ceil($sell_egg/10)*$rate_config;
        $total_egg = $sell_egg + $rate;
        if($total_egg > $egg_num){
            $this->error("您的可支付蛋数量不足".$total_egg.'个！');
        }

        if($info['is_virtual'] == 0){
            $wh = [];
            $wh['id']      = $address_id;
            $wh['user_id'] = $this->auth->id;
            $address = Db::name("user_address")->where($wh)->find();
            if(empty($address)){
                $this->error("请选择配送地址");            
            }
        }else{
            $this->error("充值账户不能为空"); 
        }

        $auth = new \app\common\library\Auth();
        if ($this->auth->paypwd != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error('支付密码错误');
        }

        DB::startTrans();  
        try {
            $order_sn = \app\common\model\Order::getOrderSn();
            
            $img_arr = explode(",",$info['images']);
            $data = [];
            $data['buy_user_id']        = $this->auth->id;
            $data['sell_user_id']       = $info['user_id'];
            $data['product_id']         = $id;
            $data['kind_id']            = $info['kind_id'];
            $data['order_sn']           = $order_sn;
            $data['image']              = $img_arr[0];
            $data['title']              = $info['title'];
            $data['price']              = $info['price'];
            $data['is_virtual']         = $info['is_virtual'];
            $data['number']             = $number;
            $data['rate']               = $rate;
            $data['total_price']        = $sell_egg;

            if($info['is_virtual'] == 0){
                $data['contactor']          = $address['real_name'];
                $data['contactor_phone']    = $address['phone'];
                $data['address']            = $address['area']." ".$address['address'];
            }else{
                $data['recharge_account']   = $recharge_account;
            }

            $data['status']             = 2;
            $data['add_time']           = time();    
            $rs = Db::name("mall_order")->insertGetId($data);

            //扣除蛋
            $egg_where = [
                'user_id' => $this->auth->id,
                'kind_id' => $info['kind_id'],
                'number'  => ['>=',$total_egg],
            ];        
            $add_rs = Db::name("egg")->where($egg_where)->dec('number',$total_egg)->update();

            //蛋日志
            $log = \app\admin\model\egg\Log::saveLog($this->auth->id,$info['kind_id'],1,$order_sn,'-'.$sell_egg,$egg_num,($egg_num-$sell_egg),"商城消费");

            //蛋手续费
            $log_fee = true;
            if($rate > 0){
                $log_fee = \app\admin\model\egg\Log::saveLog($this->auth->id,$info['kind_id'],9,$order_sn,'-'.$rate,($egg_num-$sell_egg),($egg_num-$total_egg),"商城消费手续费");
            }

            $wh = [];
            $wh['id']       = $id;
            $wh['status']   = 1;
            $wh['stock']    = ['>=',$number];
            $prs = Db::name("mall_product")->where($wh)->dec('stock',$number)->inc("sell_num",$number)->update();
            if ($rs && $add_rs && $log && $log_fee && $prs) {
                DB::commit();
                // 通知卖家
                // \app\common\library\Hsms::send($info['user_id'], '','order');
            } else {
                DB::rollback();
                $this->error("购买失败,请重新购买");
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }   
        if($info['is_virtual'] == 0){
            $this->success('购买成功,等待发货');
        }else{  
            $this->success('申请成功,等待审核');
        }
        
    }


    /**
     * 订单删除
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="order_sn", type="string", description="订单号")
     * @ApiParams   (name="type", type="integer", description="买卖类型默认： 1=买 2=卖")
     */
    public function orderDel()
    {
        $order_sn       = $this->request->post("order_sn","");
        $type           = $this->request->post("type",1);

        if(empty($order_sn) || empty($type)){            
            $this->error("参数不正确,请检查");
        }

        if($this->auth->status != 'normal' || $this->auth->is_attestation != 1){
            $this->error("账号无效或者未认证");
        }
        
        $wh = [];
        $wh['order_sn']     = $order_sn;
        if($type == 1){
            $wh['buy_user_id'] = $this->auth->id;
        }else{
            $wh['sell_user_id'] = $this->auth->id;
        }
        $wh['status']       = ['in',[1,6]];
        $info = Db::name("mall_order")->field("id,order_sn")->where($wh)->find();
        if(empty($info)){            
            $this->error("无效操作");
        }
        if($type == 1){
            $rs = Db::name("mall_order")->where("id",$info['id'])->update(['buy_del'=>1]);
        }else{
            $rs = Db::name("mall_order")->where("id",$info['id'])->update(['sell_del'=>1]);
        }        
        if($rs){
            $this->success("删除成功");
        }else{
            $this->error("删除失败,请重试");
        }
    }
}