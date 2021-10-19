<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;
use think\config;

class Egg extends Api
{
    /*
     * 蛋收盘价格表，定时器每个小时整点运行
     */
    public function hours_price(){
        $kind_where = [];
        $kind_where[] = ['id', 'lt', 5];
        $egg_kind = Db::name("egg_kind")
            ->where($kind_where)
            ->order('id asc')
            ->select();
        if(count($egg_kind)>0){
            foreach ($egg_kind as $k=>$v ){
                //时间段最后一笔交易单价，如果为0的话就去配置蛋的价格
                $order_where = [];
                $order_where[] = ['kind_id', 'eq', $v['id']];
                $order_where[] = ['status', 'eq', 1];
                $order_where[] = ['pay_time', 'lt', time()];
                $order_where[] = ['pay_time', 'gt', time()-60*60];
                $order = Db::name('egg_order')->field('price')->where($order_where)->order("pay_time desc")->find();
                if($order){
                    $price = $order['price'];
                }else{
                    $price = Db::name("egg_price_config")->where(array('kind_id'=>$v['id']))->value('price');
                }
                $hours_price_data[] = [
                    'kind_id' => $v['id'],
                    'kind_name' => $v['name'],
                    'price' => $price,
                    'day' => date("Y-m-d"),
                    'hours' => date("H:i",time()),
                    'createtime' => time(),
                ];
            }
            Db::name("egg_hours_price")->insertAll($hours_price_data);
        }
    }

    /*
     * 农贸市场
     */
    public function market_index(){
        $kind_where = [];
        $kind_where[] = ['id', 'lt', 5];
        $egg_kind = Db::name("egg_kind")
            ->field("id,name")
            ->where($kind_where)
            ->order('id asc')
            ->select();
        if(count($egg_kind)>0) {
            foreach ($egg_kind as $k => $v) {
                $hours_where = [];
                $hours_where[] = ['kind_id', 'eq', $v['id']];
                $list = Db::name("egg_hours_price")
                    ->field("price,hours,kind_id")
                    ->where($hours_where)
                    ->order('hours asc')
                    ->select();
                $egg_kind[$k]['list'] = $this->hours($list);;
            }
        }
        $this->success('查询成功',$egg_kind);
    }

    public function hours($list)
    {
        $data = [];
        for ($x=0; $x<=23; $x++) {
            if($x<10){
                $data1['hours'] ='0'.$x.':00';
                $data1['price'] =  '0';
                $data[] = $data1;
            }else{
                $data1['hours'] = $x.':00';
                $data1['price'] =  '0';
                $data[] = $data1;
            }
        }
        foreach ($data as &$lv){
            foreach ($list as &$v){
                if($lv['hours'] == $v['hours']){
                    $lv['price'] = $v['price'];
                }
            }
        }
        return $data;
    }

    /*
     * 交易大厅
     */
    public function market_hall()
    {
        $buy_serial_umber = $this->request->get("buy_serial_umber",0);//会员编号
        $kind_id = $this->request->get("kind_id",1);//会员编号
        $page  = $this->request->get("page",1);
        $limit = $this->request->get("per_page",10);

        $user_id  = $this->auth->id;
        //自己的挂单
        $where = [];
        $where[] = ['buy_user_id', 'eq', $user_id];
        $where[] = ['kind_id', 'eq', $kind_id];
        $where[] = ['status', 'in', [0,2,3,5]];
        $my_order = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,number,status,order_sn")
            ->where($where)
            ->find();


        //挂单状态且没有超过有效期可以撤回
        if(count($my_order)>0 && $my_order['status']==5){
            //时间过期
            $valid_time = 0;
            $valid_time   = Config::get('valid_time') * 60 * 60;
            $end_time = $valid_time + $my_order['createtime'];
            if($end_time>time()){
                $my_order['is_cancel'] = 0;
            }else{
                $my_order['is_cancel'] = 1;
            }
        }

        //别人挂单
        $order_where = [];
        $order_where[] = ['buy_user_id', 'neq', $user_id];
        $order_where[] = ['kind_id', 'eq', $kind_id];
        $order_where[] = ['status', 'eq', 0];
        if($buy_serial_umber>0){
            $order_where[] = ['buy_serial_umber', 'eq', $buy_serial_umber ];
        }
        $order = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,status")
            ->where($order_where)
            ->order('price asc')
            ->page($page, $limit)
            ->select();

        $order_count = Db::name("egg_order")->where($order_where)->count();
        if($order_count>($page*$limit)){
            $has_next = 1;
        }else{
            $has_next = 0;
        }

        $list = array();
        $list['my_order'] = $my_order;
        $list['order'] = $order;
        $list['has_next'] = $has_next;
        $this->success('查询成功',$list);
    }

    /*
     * 挂单
     */
    public function market_buy()
    {
        $user_id  = $this->auth->id;
        $kind_id  = $this->request->get("kind_id",0);
        $price  = $this->request->get("price",0);
        $number = $this->request->get("number",0);

        if($kind_id<=0 || $kind_id>4){
            $this->error("请选择有效的蛋种类！");
        }

        $egg_name = Db::name("egg_kind")
            ->where('kind_id',$kind_id)
            ->value('name');

        if($number==0){
            $this->error("请输入蛋数量！");
        }

        //挂单数量
        $where = [];
        $where[] = ['buy_user_id', 'eq', $user_id];
        $where[] = ['kind_id', 'eq', $kind_id];
        $where[] = ['status', 'in', [0,2,3]];
        $count = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,number,status")
            ->where($where)
            ->count();
        if($count>0){
            $this->error("只能挂买一个订单！");
        }

        //蛋基础价格
        $egg_order  = Db::name("egg_order")
            ->where('kind_id',$kind_id)
            ->find();

        if($price<=0){
            $this->error("请输入有效的蛋价格");
        }

        if($egg_order['price']>$price || $egg_order['max_price']<$price){
            $this->error("蛋价格范围是".$egg_order['price'].'元-'.$egg_order['max_price'].'元');
        }

        $u_where = [];
        $u_where[] = ['status','eq','normal'];
        $u_where[] = ['is_attestation','eq',1];
        $user_info = Db::name("user")
            ->field("id,serial_number,mobile")
            ->where($u_where)
            ->find();

        if(count($user_info)==0){
            $this->error("账号无效或者未认证");
        }

        //生成挂单订单
        $order_data = array();
        $order_data['order_sn'] = date("Ymdhis", time()).mt_rand(1000,9999);
        $order_sn = $order_data['order_sn'];
        $order_data['buy_user_id'] = $user_id;
        $order_data['buy_serial_umber'] = $user_info['serial_number'];
        $order_data['buy_mobile'] = $user_info['mobile'];
        $order_data['name'] = $egg_name;
        $order_data['kind_id'] = $kind_id;
        $order_data['price'] = $price;
        $order_data['number'] = $number;
        $order_data['rate'] = ceil($number*Config::get('rate_config')/100);
        $order_data['amount'] = $price * $number;
        $order_data['status'] = 5;
        $order_data['createtime'] = time();

        $re = Db::name("egg_order")->insert($order_data);

        if ($re == true){
            $this->success("挂单成功");
        }else{
            $this->error('挂单失败');
        }
    }

    /*
     * 撤单
     */
    public function market_cancel()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);

        //订单
        $where = [];
        $where[] = ['order_sn','eq',$order_sn];
        $where[] = ['buy_user_id','eq',$user_id];
        $order = Db::name("egg_order")
            ->field("*")
            ->where($where)
            ->find();

        if(count($order)== 0 || $order['status']!=5 || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("不能撤单");
        }

        //时间过期
        $valid_time = 0;
        $valid_time   = Config::get('valid_time') * 60 * 60;
        $end_time = $valid_time + $order['createtime'];

        if($end_time>time()){
            $this->error(Config::get('valid_time')."小时内不能撤单");
        }

        //更新订单
        $data =array();
        $data['status'] = 4;
        $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();
        if ($re == true){
            $this->success("撤单成功");
        }else{
            $this->error('撤单失败');
        }
    }

    /*
    * 出售
    */
    public function market_sale()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);

        //订单
        $order = Db::name("egg_order")
            ->field("*")
            ->where('order_sn',$order_sn)
            ->find();

        if(count($order)== 0 || $order['status']!=5 || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }

        $egg_where = [];
        $egg_where[] = ['user_id','eq',$user_id];
        $egg_where[] = ['kind_id','eq',$order_sn['kind_id']];
        $egg_num = Db::name("egg")->where($egg_where)->value('number');

        //蛋数量不够
        $total_egg = $order['number'] + $order['rate'];
        if($total_egg>$egg_num){
            $this->error("您的蛋数量不足",$total_egg.'个！');
        }

        $u_where = [];
        $u_where[] = ['status','eq','normal'];
        $u_where[] = ['is_attestation','eq',1];
        $user_info = Db::name("user")
            ->field("id,serial_number,mobile")
            ->where($u_where)
            ->find();

        if(count($user_info)==0){
            $this->error("账号无效或者未认证");
        }

        DB::startTrans();
        try{
            //更新订单
            $data =array();
            $data['sell_user_id'] = $user_id;
            $data['sell_serial_umber'] = $user_info['serial_number'];
            $data['sell_mobile'] = $user_info['mobile'];
            $data['status'] = 0;
            $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();

            //扣除蛋
            $egg_where = [];
            $egg_where[] = ['user_id','eq',$user_id];
            $egg_where[] = ['kind_id','eq',$order_sn['kind_id']];
            $egg_where[] = ['number', 'egt', $total_egg];
            $add_rs = Db::name("egg")->where($egg_where)->dec('number',$total_egg)->update();

            //蛋日志
            $log_add = \app\admin\model\egg\Log::saveLog($user_id,$order_sn['kind_id'],1,$order_sn,$total_egg,"农场市场挂单");
            if ($re == false || $add_rs == false ||  $log_add == false) {
                DB::rollback();
                $this->error("出售失败");
            } else {
                DB::commit();
                //通知买家

                $this->success('出售成功，等待对方打款');
            }
        }//end try
        catch(\Exception $e)
        {
            DB::rollback();
            $this->error("出售失败");
        }
    }

    /*
    * 打款凭证
    */
    public function market_pay()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);
        $pay_img  = $this->request->get("pay_img",'');
        $type  = $this->request->get("type",1);//类型 1=支付宝 2=微信 3=钱包

        if(empty($pay_img)){
            $this->error("请上传付款凭证！");
        }

        //订单
        $order_where = [];
        $order_where[] = ['order_sn','eq',$order_sn];
        $order_where[] = ['buy_user_id','eq',$user_id];
        $order_where[] = ['status','eq',0];
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(count($order)== 0  || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }

        //卖家支付信息
        $where = [];
        $where[] = ['user_id','eq',$order['sell_user_id']];
        $where[] = ['type','eq',$type];
        $charge_code = Db::name("egg_charge_code")
            ->field("*")
            ->where($where)
            ->find();
        if(count($charge_code)==0){
            $this->error("请选择付款方式");
        }

        //更新订单
        $data =array();
        $data['attestation_type'] = $charge_code['type'];
        $data['attestation_image'] = $charge_code['image'];
        $data['attestation_account'] = $charge_code['account'];
        $data['pay_img'] = $pay_img;
        $data['status'] = 2;
        $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();
        if ($re == true){
            //通知卖家

            $this->success("确认支付成功");
        }else{
            $this->error('确认支付失败');
        }
    }

    /*
    * 卖家确认支付
    */
    public function market_confirm()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);

        //订单
        $order_where = [];
        $order_where[] = ['order_sn','eq',$order_sn];
        $order_where[] = ['sell_user_id','eq',$user_id];
        $order_where[] = ['status','eq',2];
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(count($order)== 0  || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }

        //更新订单
        $data =array();
        $data['status'] = 1;
        $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();
        if ($re == true){
            $this->success("确认支付成功");
        }else{
            $this->error('确认支付失败');
        }

    }

    /*
    * 卖家申诉
    */
    public function market_appeal()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);
        $note  = $this->request->get("reason",'');

        if(empty($note)){
            $this->error("请填写申诉理由！");
        }
        //订单
        $order_where = [];
        $order_where[] = ['order_sn','eq',$order_sn];
        $order_where[] = ['sell_user_id','eq',$user_id];
        $order_where[] = ['status','eq',2];
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(count($order)== 0  || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }

        //更新订单
        $data =array();
        $data['status'] = 3;
        $data['note'] = $note;
        $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();
        if ($re == true){
            $this->success('申诉成功，请耐心等待审核！');
        }else{
            $this->error("申诉失败");
        }
    }

    /*
    * 订单详情
    */
    public function market_order_detail()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);
        //订单
        $order_where = [];
        $order_where[] = ['order_sn','eq',$order_sn];
        $order_where[] = ['sell_user_id','eq',$user_id];
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(count($order)== 0  || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0 ){
            $this->error("无效订单");
        }

        if($order['sell_user_id'] !=$user_id && $order['buy_user_id'] !=$user_id){
            $this->error("无效订单");
        }

        //是否是卖家
        $order['is_sell'] = 0;
        if($order['sell_user_id'] ==$user_id){
            $order['is_sell'] = 1;
        }

        //是否是买家
        $order['is_buy'] = 0;
        if($order['buy_user_id'] ==$user_id){
            $order['is_buy'] = 1;
        }

        //卖家支付方式
        $order['pay_list'] = Db::name("egg_charge_code")
            ->field("*")
            ->where('user_id',$user_id)
            ->select();

        $this->success('查询成功',$order);
    }

    /*
    * 卖家自动确认订单
    */
    public function market_automatic_confirm(){
        //超时待确认的订单
        $confirm_time = 0;
        $confirm_time   = Config::get('confirm_time') * 60 * 60;
        $time_out = time() - $confirm_time;
        //订单
        $order_where = [];
        $order_where[] = ['pay_time','lt',$time_out];
        $order_where[] = ['status','eq',2];
        $order_where[] = ['number','gt',0];
        $order_where[] = ['rate','gt',0];
        $order_where[] = ['amount','gt',0];
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->limit(20)
            ->select();
        if(count($order)>0){
            foreach($order as $k=>$v) {
                DB::startTrans();
                try {
                    //更新订单
                    $data = array();
                    $data['status'] = 1;
                    $re = Db::name("egg_order")->where('order_sn', $v['order_sn'])->data($data)->update();
                    if ($re == false) {
                        DB::rollback();
                        $this->error("确认订单失败");
                    } else {
                        DB::commit();
                        continue;
                    }
                }//end try
                catch (\Exception $e) {
                    DB::rollback();
                    $this->error("确认订单失败");
                }
            }
        }

    }
}
