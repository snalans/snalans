<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;
use think\config;
use think\Db;

/**
 * 农贸市场接口
 */
class Egg extends Api
{
    protected $noNeedLogin = ['hours_price','market_index','market_automatic_confirm','hours','market_automatic_refund'];
    protected $noNeedRight = '*';
    /**
     * 蛋收盘价格表（定时器每个小时整点运行）
     */
    public function hours_price(){
        $kind_where = array('id'=>array('lt',5));
        $egg_kind = Db::name("egg_kind")
            ->where($kind_where)
            ->order('id asc')
            ->select();

        $hours_where = array(
            'day'=>array('eq',date("Y-m-d")),
            'hours'=>array('eq',date("H",time()).':00')
        );
        $hours_price_count = Db::name("egg_hours_price")
            ->where($hours_where)
            ->count();

        if(count($egg_kind)>0 && $hours_price_count==0){
            foreach ($egg_kind as $k=>$v ){
                //时间段最后一笔交易单价，如果为0的话就去配置蛋的价格
                $order_where = array(
                    'kind_id'=>array('eq',$v['id']),
                    'status'=>array('eq',1),
                );
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
                    'hours' => date("H",time()).':00',
                    'createtime' => time(),
                ];
            }

            Db::name("egg_hours_price")->insertAll($hours_price_data);
        }
        $this->success('查询成功');
    }

    /**
     * 农贸市场
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="day_time", type="integer", description="日期（例如2021-11-01）")
     */
    public function market_index(){
        $day_time = $this->request->post("day_time",'');
        $day_time  = $day_time?$day_time:date('Y-m-d',time());
        $kind_where = array(
            'id'=>array('lt',4)
        );
        $egg_kind = Db::name("egg_kind")
            ->field("id,name")
            ->where($kind_where)
            ->order('id asc')
            ->select();
        if(count($egg_kind)>0) {
            foreach ($egg_kind as $k => $v) {
                $hours_where = [];
                $hours_where['kind_id'] = $v['id'];
                $hours_where['day'] = $day_time;
                $hours_where['hours'] =['between',['09:00','21:00']];
                $list = Db::name("egg_hours_price")
                    ->field("price,hours,kind_id")
                    ->where($hours_where)
                    ->order('hours asc')
                    ->select();
                $egg_kind[$k]['list'] = $this->hours($list,date("H",time()));
            }
        }
        $this->success('查询成功',$egg_kind);
    }

    public function hours($list=array(),$num=0)
    {
        $data = [];
        for ($x=9; $x<=21; $x++) {
            if($x<10){
                $data1['hours'] ='0'.$x.':00';
                $data1['price'] =  $num>$x?'0':'';
                $data[] = $data1;
            }else{
                $data1['hours'] = $x.':00';
                $data1['price'] =  $num>$x?'0':'';
                $data[] = $data1;
            }
        }
        if(count($list)>0){
            foreach ($data as &$lv){
                foreach ($list as &$v){
                    if($lv['hours'] == $v['hours']){
                        $lv['price'] = $v['price'];
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 交易大厅
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="buy_serial_umber", type="integer", description="会员编号")
     * @ApiParams   (name="kind_id", type="integer", description="蛋分类id")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="分页数量")
     *
     * @ApiReturnParams   (name="buy_serial_umber", type="integer", description="会员编号")
     * @ApiReturnParams   (name="number", type="integer", description="数量")
     * @ApiReturnParams   (name="price", type="integer", description="价格")
     * @ApiReturnParams   (name="status", type="integer", description="状态默认：9=全部 0=待付款 1=完成 2=待确认 3=申诉 4=无效（撤单） 5=挂单  6退款")
     * @ApiReturnParams   (name="order_sn", type="integer", description="订单编号")
     */
    public function market_hall()
    {
        $buy_serial_umber = $this->request->post("buy_serial_umber",'');//会员编号
        $kind_id = $this->request->post("kind_id",1);//蛋分类id
        $page  = $this->request->post("page",1);
        $limit = $this->request->post("per_page",10);

        $kind_id = $kind_id>0?$kind_id:1;

        if($kind_id<=0 || $kind_id>3){
            $this->error("请选择有效的蛋种类！");
        }

        $user_id  = $this->auth->id;
        //自己的挂单
        $where = array(
            'buy_user_id'=>array('eq',$user_id),
            'kind_id'=>array('eq',$kind_id),
            'status'=>array('eq',5)
        );

        $my_order = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,number,status,order_sn,createtime,buy_user_id")
            ->where($where)
            ->find();

        //挂单状态且没有超过有效期可以撤回
        if(!empty($my_order) && $my_order['status']==5){

            //时间过期
            $valid_time = 0;
            $valid_time   = Config::get('site.valid_time') * 60 * 60;
            $end_time = $valid_time + $my_order['createtime'];
            if($end_time>time()){
                $my_order['is_cancel'] = 0;
            }else{
                $my_order['is_cancel'] = 1;
            }
        }

        //别人挂单
        if(!empty($buy_serial_umber)){
            $order_where = array(
                'buy_user_id'=>array('neq',$user_id),
                'kind_id'=>array('eq',$kind_id),
                'status'=>array('eq',5),
                'buy_serial_umber'=>array('eq',$buy_serial_umber)
            );
        }else{
            $order_where = array(
                'buy_user_id'=>array('neq',$user_id),
                'kind_id'=>array('eq',$kind_id),
                'status'=>array('eq',5)
            );
        }

        $order = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,status,order_sn,number,rate,buy_user_id")
            ->where($order_where)
            ->order('price desc,id asc')
            ->paginate($limit);
//            ->page($page, $limit)
//            ->select();


//        $order_count = Db::name("egg_order")->where($order_where)->count();
//        if($order_count>($page*$limit)){
//            $has_next = 1;
//        }else{
//            $has_next = 0;
//        }

        $list = array();
        $list['my_order'] = $my_order;
        $list['order'] = $order;
//        $list['has_next'] = $has_next;
        $this->success('查询成功',$list);
    }

    /**
     * 挂单
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="kind_id", type="integer", description="蛋分类id")
     * @ApiParams   (name="price", type="integer", description="价格")
     * @ApiParams   (name="number", type="integer", description="数量")
     */
    public function market_buy()
    {
        $user_id  = $this->auth->id;
        $kind_id  = $this->request->post("kind_id",0);
        $price  = $this->request->post("price",0);
        $number = $this->request->post("number",0);

        if($kind_id<=0 || $kind_id>3){
            $this->error("请选择有效的蛋种类！");
        }
        if($kind_id == 1){            
            if($number <= 0 || $number > 500){
                $this->error("数量要在1~500之间");
            }
        }
        if($kind_id == 2){            
            if($number <= 0 || $number > 200){
                $this->error("数量要在1~200之间");
            }
        }
        if($kind_id == 3){            
            if($number <= 0 || $number > 60){
                $this->error("数量要在1~60之间");
            }
        }

        $egg_kind_info = Db::name("egg_kind")
            ->field("name,rate_config")
            ->where('id',$kind_id)
            ->find();

        //挂单数量
        $where = array(
            'buy_user_id'=>array('eq',$user_id),
            'kind_id'=>array('eq',$kind_id),
            'status'=>array('in',[0,2,3,5])
        );

        $count = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,number,status")
            ->where($where)
            ->count();
        if($count>0){
            $this->error("只能挂买一个订单！");
        }

        //蛋基础价格
        $egg_price_config  = Db::name("egg_price_config")
            ->where('kind_id',$kind_id)
            ->find();

        if($price<=0){
            $this->error("请输入有效的蛋价格");
        }

        if($egg_price_config['price']>$price || $egg_price_config['max_price']<$price){
            $this->error("蛋价格范围是".$egg_price_config['price'].'元-'.$egg_price_config['max_price'].'元');
        }

        $u_where = [];
        $u_where['id'] = $user_id;
        $u_where['status'] = 'normal';
        $u_where['is_attestation'] = 1;
        $user_info = Db::name("user")
            ->field("id,serial_number,mobile")
            ->where($u_where)
            ->find();

        if(empty($user_info)){
            $this->error("账号无效或者未认证");
        }

        // $rate = 0;
        // if($egg_kind_info['rate_config']>0){
        //     $rate = $number*$egg_kind_info['rate_config']/100;
        // }           
        if($kind_id == 3){
            $rate = ceil($number/5)*$egg_kind_info['rate_config'];
        }else{
            $rate = ceil($number/10)*$egg_kind_info['rate_config'];
        }        

        //生成挂单订单
        $order_data = array();
        $order_data['order_sn'] = date("Ymdhis", time()).mt_rand(1000,9999);
        $order_sn = $order_data['order_sn'];
        $order_data['buy_user_id'] = $user_id;
        $order_data['buy_serial_umber'] = $user_info['serial_number'];
        $order_data['buy_mobile'] = $user_info['mobile'];
        $order_data['name'] = $egg_kind_info['name'];
        $order_data['kind_id'] = $kind_id;
        $order_data['price'] = $price;
        $order_data['number'] = $number;
        // $order_data['rate'] = ceil(ceil($number/10)*$egg_kind_info['rate_config']);
        $order_data['rate'] = $rate;
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

    /**
     * 撤单
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="order_sn", type="integer", description="订单编号")
     */
    public function market_cancel()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->post("order_sn",0);

        //订单
        $where = [];
        $where['order_sn'] = $order_sn;
        $where['buy_user_id'] = $user_id;
        $order = Db::name("egg_order")
            ->field("*")
            ->where($where)
            ->find();

        if(count($order)== 0 || $order['status']!=5 || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("不能撤单");
        }

        //时间过期
        $valid_time = 0;
        $valid_time   = Config::get('site.valid_time') * 60 * 60;
        $end_time = $valid_time + $order['createtime'];

        if($end_time>time()){
            $this->error(Config::get('site.valid_time')."小时内不能撤单");
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

    /**
    * 出售
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="order_sn", type="integer", description="订单编号")
     * @ApiParams   (name="paypwd", type="string", description="支付密码")
     * @ApiParams   (name="google_code", type="string", description="谷歌验证码")
    */
    public function market_sale()
    {
        $user_id   = $this->auth->id;
        $order_sn  = $this->request->post("order_sn",0);
        $paypwd    = $this->request->post('paypwd',"");
        $google_code = $this->request->post('google_code');

        //订单
        $order = Db::name("egg_order")
            ->field("*")
            ->where('order_sn',$order_sn)
            ->find();

        if(empty($order) || $order['status']!=5 || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }

        if($order['buy_user_id'] == $user_id){
            $this->error("不能出售给自己");
        }

        $auth = new \app\common\library\Auth();
        if ($this->auth->paypwd != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error('支付密码错误');
        }
        $google_secret = Db::name("user_secret")->where("user_id",$this->auth->id)->value("google_secret"); 
        if(!empty($google_secret)){
            $ga = new \app\admin\model\PHPGangsta_GoogleAuthenticator;
            $checkResult = $ga->verifyCode($google_secret, $google_code);
            if(!$checkResult){
                $this->error("谷歌验证码错误!");
            }        
        }else{
            $this->error("请先绑定谷歌验证,进行谷歌验证!");
        }

        //出售单数
        $where = [];
        $where["sell_user_id"]  = $user_id;
        $where["status"]        = ['in',[0,2,3]];
        $count = Db::name("egg_order")->field("id")->where($where)->count();
        if($count>0){
            $this->error("存在未完成订单,无法出售。");
        }

        $order_start_time = Config::get('site.order_start_time');
        $order_end_time = Config::get('site.order_end_time');
        $start_time   = Config::get('site.order_start_time') * 60 * 60 + strtotime(date("Y-m-d"));
        $end_time   = Config::get('site.order_end_time') * 60 * 60  + strtotime(date("Y-m-d"));

        //$this->error("交易时间".Config::get('site.order_start_time'));
        if($order_start_time!= 0 || $order_end_time != 0){
            if($start_time>time() || $end_time<time()){
                $this->error("交易时间".$order_start_time.":00-".$order_end_time.":00");
            }
        }

        $egg_where = [];
        $egg_where['user_id'] = $user_id;
        $egg_where['kind_id'] = $order['kind_id'];
        $egg_num = Db::name("egg")->where($egg_where)->value('sum(number-freezing)');

        //蛋数量不够
        $total_egg = $order['number'] + $order['rate'];
        if($total_egg>$egg_num){
            $this->error("您的可交易蛋资产不足".$total_egg.'个！');
        }

        $u_where = [];
        $u_where['status'] = 'normal';
        $u_where['is_attestation'] = 1;
        $u_where['id'] = $user_id;
        $user_info = Db::name("user")
            ->field("id,serial_number,mobile")
            ->where($u_where)
            ->find();

        if(empty($user_info)){
            $this->error("账号无效或者未认证");
        }

        //卖家支付方式
        $pay_where = array(
            'user_id'=>array('eq',$user_id),
            'type'=>array('neq',3),
        );
        $pay_count = Db::name("egg_charge_code")
            ->where($pay_where)
            ->count();
        if($pay_count==0){
            $this->error("请往会员中心添加支付方式");
        }

        DB::startTrans();
        try{
            //更新订单
            $data = [];
            $data['sell_user_id'] = $user_id;
            $data['sell_serial_umber'] = $user_info['serial_number'];
            $data['sell_mobile'] = $user_info['mobile'];
            $data['status'] = 0;
            $data['sale_time'] = time();
            $wh = [];
            $wh['order_sn'] = $order_sn;
            $wh['status'] = 5;
            $re = Db::name("egg_order")->where($wh)->data($data)->update();

            //扣除蛋
            $egg_where = array(
                'user_id'=>array('eq',$user_id),
                'kind_id'=>array('eq',$order['kind_id']),
                'number'=>array('egt',$total_egg)
            );
            $add_rs = Db::name("egg")->where($egg_where)->dec('number',$total_egg)->update();

            //蛋日志
            $log_add = \app\admin\model\egg\Log::saveLog($user_id,$order['kind_id'],1,$order_sn,'-'.$order['number'],$egg_num,($egg_num-$order['number']),"出售");

            //蛋手续费
            $log_fee_add = \app\admin\model\egg\Log::saveLog($user_id,$order['kind_id'],9,$order['order_sn'],'-'.$order['rate'],($egg_num-$order['number']),($egg_num-$total_egg),"农贸市场交易手续费");

            if ($re && $add_rs && $log_add && $log_fee_add) {
                //通知买家
                \app\common\library\Hsms::send($order['buy_mobile'], '','order');
                DB::commit();
            } else {
                DB::rollback();
                $this->error("出售失败");
            }
        }//end try
        catch(\Exception $e)
        {
            DB::rollback();
            $this->error("出售失败");
        }
        $this->success('出售成功，等待对方打款');
    }

    /**
    * 打款凭证
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="order_sn", type="integer", description="订单编号")
     * @ApiParams   (name="pay_img", type="integer", description="凭证")
     * @ApiParams   (name="type", type="integer", description="类型 1=支付宝 2=微信 3=钱包")
    */
    public function market_pay()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->post("order_sn",0);
        $pay_img  = $this->request->post("pay_img",'');
        $type  = $this->request->post("type",1);//类型 1=支付宝 2=微信 3=钱包

        if(empty($pay_img)){
            $this->error("请上传付款凭证！");
        }

        //订单
        $order_where = [];
        $order_where['order_sn'] = $order_sn;
        $order_where['buy_user_id'] = $user_id;
        $order_where['status'] = 0;
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(empty($order) || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }

        //卖家支付信息
        $where = [];
        $where['user_id'] = $order['sell_user_id'];
        $where['type'] = $type;
        $charge_code = Db::name("egg_charge_code")
            ->field("*")
            ->where($where)
            ->find();
        if(empty($charge_code)){
            $this->error("请选择付款方式");
        }

        //更新订单
        $data =array();
        $data['attestation_type'] = $charge_code['type'];
        $data['attestation_image'] = $charge_code['image'];
        $data['attestation_account'] = $charge_code['account'];
        $data['pay_img'] = $pay_img;
        $data['pay_time'] = time();
        $data['status'] = 2;
        $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();
        if ($re == true){
            //通知卖家
            \app\common\library\Hsms::send($order['sell_user_id'], '','order');
            $this->success("上传凭证成功，等待卖家确认支付");
        }else{
            $this->error('上传凭证失败');
        }
    }

    /**
    * 卖家确认支付
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="order_sn", type="integer", description="订单编号")
    */
    public function market_confirm()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->post("order_sn",0);

        //订单
        $order_where = [];
        $order_where['order_sn'] = $order_sn;
        $order_where['sell_user_id'] = $user_id;
        $order_where['status'] = 2;
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(empty($order)  || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }
        DB::startTrans();
        try{
            //更新订单
            $data =array();
            $data['status'] = 1;
            $data['over_time'] = time();
            $re = Db::name("egg_order")->where('order_sn',$order_sn)->data($data)->update();

            //蛋给买家
            $egg_where = array(
                'user_id'=>array('eq',$order['buy_user_id']),
                'kind_id'=>array('eq',$order['kind_id']),
            );
            $before = Db::name("egg")->where($egg_where)->value('number');
            $add_rs = Db::name("egg")->where($egg_where)->inc('number',$order['number'])->update();

            //买家获得蛋日志
            $log_add = \app\admin\model\egg\Log::saveLog($order['buy_user_id'],$order['kind_id'],1,$order_sn,$order['number'],$before,($before+$order['number']),"农场市场卖家确认支付");

            //增加买家有效值
//            $egg_info = Db::name("egg_kind")
//                ->field('*')
//                ->where('id',$order['kind_id'])
//                ->find();
//            $valid_rs = true;
//            $res_vip = true;
//            $valid_log_res = true;
//            if($egg_info['valid_number']>0){
//                $valid_number = $egg_info['valid_number'] * $order['number'];
//                $valid_rs = Db::name("user")->where('id',$order['buy_user_id'])->inc('valid_number',$valid_number)->update();
//                if($valid_rs == true){
//                    $userLevelConfig = new \app\common\model\UserLevelConfig();
//                    $res_vip = $userLevelConfig->update_vip($order['buy_user_id']);
//                }
//
//                $log = [];
//                $log['user_id'] = $order['buy_user_id'];
//                $log['origin_user_id'] = $order['sell_user_id'];
//                $log['number'] = $valid_number;
//                $log['add_time'] = time();
//                $log['type'] = 2;
//                $log['order_sn'] = $order['order_sn'];
//                $valid_log_res  = Db::name("egg_valid_number_log")->insert($log);
//            }


            if ($re == false || $add_rs == false ||  $log_add == false ) {
                DB::rollback();
                $this->error("确认支付失败");
            } else {
                //通知买家
                \app\common\library\Hsms::send($order['buy_mobile'], '','order');
                DB::commit();
            }
        }//end try
        catch(\Exception $e)
        {
            DB::rollback();
            $this->error($e->getMessage());
        }
        $this->success('确认支付成功');
    }

    /**
    * 卖家申诉
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="order_sn", type="integer", description="订单编号")
     * @ApiParams   (name="reason", type="integer", description="申诉理由")
    */
    public function market_appeal()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->post("order_sn",0);
        $note  = $this->request->post("reason",'');

        if(empty($note)){
            $this->error("请填写申诉理由！");
        }
        //订单
        $order_where = [];
        $order_where['order_sn'] = $order_sn;
        $order_where['sell_user_id'] = $user_id;
        $order_where['status'] = 2;
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->find();

        if(empty($order)  || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
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

    /**
    * 订单详情
     *
     * @ApiMethod (Get)
     * @ApiParams   (name="order_sn", type="integer", description="订单编号")
    */
    public function market_order_detail()
    {
        $user_id  = $this->auth->id;
        $order_sn  = $this->request->get("order_sn",0);
        //订单
        $order_where = [];
        $order_where['order_sn'] = $order_sn;
        $order = Db::name("egg_order")
            ->field("order_sn,buy_serial_umber,name,kind_id,price,number,rate,amount,status,buy_user_id")
            ->where($order_where)
            ->find();

        if(empty($order) || $order['status']!=5 || $order['number']<=0 || $order['rate']<=0 || $order['amount']<=0){
            $this->error("无效订单");
        }


        $this->success('查询成功',$order);
    }

    /**
    * 卖家自动确认订单
    */
    public function market_automatic_confirm(){

//        $egg_kind = Db::name("egg_kind")
//            ->field('*')
//            ->order('id asc')
//            ->select();
//        $config_egg_kind = [];
//        foreach ($egg_kind as $key=>$value){
//            $config_egg_kind[$value['id']] = $value;
//        }

        //超时待确认的订单
        $confirm_time = 0;
        $confirm_time   = Config::get('site.confirm_time') * 60 * 60;
        $time_out = time() - $confirm_time;
        //订单
        $order_where = array(
            'pay_time'=>array('lt',$time_out),
            'status'=>array('eq',2),
            'number'=>array('gt',0),
            'rate'=>array('gt',0),
            'amount'=>array('gt',0)
        );
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
                    $data['over_time'] = time();
                    $re = Db::name("egg_order")->where('order_sn', $v['order_sn'])->data($data)->update();

                    //蛋给买家
                    $egg_where = array(
                        'user_id'=>array('eq',$v['buy_user_id']),
                        'kind_id'=>array('eq',$v['kind_id']),
                    );
                    $before = Db::name("egg")->where($egg_where)->value('number');
                    $add_rs = Db::name("egg")->where($egg_where)->inc('number',$v['number'])->update();

                    //买家获得蛋日志
                    $log_add = \app\admin\model\egg\Log::saveLog($v['buy_user_id'],$v['kind_id'],1,$v['order_sn'],$v['number'],$before,($before+$v['number']),"农场市场卖家确认支付");


                    //增加买家有效值
//                    $valid_rs = true;
//                    $res_vip = true;
//                    $valid_log_res = true;
//                    $egg_info = $config_egg_kind[$v['kind_id']];
//                    if($egg_info['valid_number']>0){
//                        $valid_number = $egg_info['valid_number'] * $v['number'];
//                        $valid_rs = Db::name("user")->where('id',$v['buy_user_id'])->inc('valid_number',$valid_number)->update();
//                        if($valid_rs == true){
//                            $userLevelConfig = new \app\common\model\UserLevelConfig();
//                            $res_vip = $userLevelConfig->update_vip($v['buy_user_id']);
//                        }
//
//                        $log = [];
//                        $log['user_id'] = $v['buy_user_id'];
//                        $log['origin_user_id'] = $v['sell_user_id'];
//                        $log['number'] = $valid_number;
//                        $log['add_time'] = time();
//                        $log['type'] = 2;
//                        $log['order_sn'] = $v['order_sn'];
//                        $valid_log_res  = Db::name("egg_valid_number_log")->insert($log);
//                    }

                    if ($re == false || $add_rs == false ||  $log_add == false ) {
                        DB::rollback();
                        $this->error("确认订单失败");
                    } else {
                        // \app\common\library\Hsms::send($v['buy_mobile'], '','order');
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
    /**
     * 彩蛋回收
     *
     * @ApiMethod (Post)
     * @ApiParams   (name="number", type="integer", description="数量")
     * @ApiParams   (name="paypwd", type="string", description="支付密码")
     */
    public function market_sale_colour(){
        $user_id  = $this->auth->id;
        $kind_id  = 5;
        $number = $this->request->post("number",0);
        $paypwd         = $this->request->post('paypwd',"");
        $egg_info = Db::name("egg_kind")
            ->field('*')
            ->where('id',$kind_id)
            ->find();

        $number = $number>0?$number:0;

        if($number==0){
            $this->error("请输入蛋数量！");
        }

        $auth = new \app\common\library\Auth();
        if ($this->auth->paypwd != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error('支付密码错误');
        }

        if($number>$egg_info['stock']){
            $this->error("库存不足！");
        }

        //卖家钱包支付方式
        $pay_where = array(
            'user_id'=>array('eq',$user_id),
            'type'=>array('eq',3)
        );
        $pay_count = Db::name("egg_charge_code")
            ->where($pay_where)
            ->count();
        if($pay_count==0){
            $this->error("请往会员中心添加钱包支付方式");
        }

        //挂单数量
        $where = array(
            'buy_user_id'=>array('eq',$user_id),
            'kind_id'=>array('eq',$kind_id),
            'status'=>array('in',[0,2,3])
        );

        $count = Db::name("egg_order")
            ->field("id,buy_serial_umber,name,price,number,status")
            ->where($where)
            ->count();
        if($count>0){
            $this->error("只能有一个彩蛋回收订单！");
        }

        //判断用户是否有彩蛋
        $egg_where = [];
        $egg_where['user_id'] = $user_id;
        $egg_where['kind_id'] = $kind_id;
        $egg_num = Db::name("egg")->where($egg_where)->value('number');

        //蛋数量不够
        if($number>$egg_num){
            $this->error("您的彩蛋数量不足".$number.'个！');
        }

        $u_where = [];
        $u_where['id'] = $user_id;
        $u_where['status'] = 'normal';
        $u_where['is_attestation'] = 1;
        $user_info = Db::name("user")
            ->field("id,serial_number,mobile")
            ->where($u_where)
            ->find();

        if(empty($user_info)){
            $this->error("账号无效或者未认证");
        }

        DB::startTrans();
        try {
            //减库存
            $kind_where = array(
                'id'=>array('eq',$kind_id),
                'stock'=>array('egt',$number)
            );
            $res = Db::name("egg_kind")->where($kind_where)->dec('stock',$number)->update();


            //生成彩蛋回收订单
            $order_data = array();
            $order_data['order_sn'] = date("Ymdhis", time()).mt_rand(1000,9999);
            $order_sn = $order_data['order_sn'];
            $order_data['sell_user_id'] = $user_id;
            $order_data['sell_serial_umber'] = $user_info['serial_number'];
            $order_data['sell_mobile'] = $user_info['mobile'];
            $order_data['name'] = $egg_info['name'];
            $order_data['kind_id'] = $kind_id;
            $order_data['price'] = $egg_info['price'];
            $order_data['number'] = $number;
            $order_data['rate'] = 0;
            $order_data['amount'] = $egg_info['price'] * $number;
            $order_data['status'] = 0;
            $order_data['createtime'] = time();

            $re = Db::name("egg_order")->insert($order_data);

            //扣除蛋
            $egg_where = array(
                'user_id'=>array('eq',$user_id),
                'kind_id'=>array('eq',$kind_id),
                'number'=>array('egt',$number)
            );
            $add_rs = Db::name("egg")->where($egg_where)->dec('number',$number)->update();

            //蛋日志
            $log_add = \app\admin\model\egg\Log::saveLog($user_id,$kind_id,1,$order_sn,'-'.$number,$egg_num,($egg_num-$number),"出售");

            if ($re == false || $res == false || $add_rs==false || $log_add==false ) {
                DB::rollback();
                $this->error("彩蛋回收失败");
            } else {
                DB::commit();
            }
        }//end try
        catch (\Exception $e) {
            DB::rollback();
            $this->error("彩蛋回收失败");
        }
        $this->success("彩蛋回收成功，请耐心等待打款");
    }

    /**
     * 买家有效时间未打款自动退款订单
     */
    public function market_automatic_refund(){
        //超时待未打款的订单
        $confirm_time = 0;
        $confirm_time   = Config::get('site.confirm_time') * 60 * 60;
        $time_out = time() - $confirm_time;
        //订单
        $order_where = array(
            'sale_time'=>array('lt',$time_out),
            'status'=>array('eq',0),
            'number'=>array('gt',0),
            'rate'=>array('gt',0),
            'kind_id'=>array('lt',5),
            'amount'=>array('gt',0)
        );
        $order = Db::name("egg_order")
            ->field("*")
            ->where($order_where)
            ->limit(30)
            ->select();
        if(count($order)>0){
            foreach($order as $k=>$v) {
                DB::startTrans();
                try {
                    //更新订单
                    $data = array();
                    $data['status'] = 6;
                    $data['refund_status'] = 1;
                    $re = Db::name("egg_order")->where('order_sn', $v['order_sn'])->data($data)->update();

                    $res_user = true;
                    $res_user_update = true;
                    if($re == true){

                        //更新用户超时未打款次数
                        $res_user = Db::name("user")->where('id', $v['buy_user_id'])->inc('unpay_num',1)->update();

                        if($res_user == true){
                            //未打款次数
                            $refund_count = Db::name("user")->where('id', $v['buy_user_id'])->value('unpay_num');
                            if($refund_count>=3){
                                //封买家账号
                                $user_data = array();
                                $user_data['status'] = 'hidden';
                                $res_user_update = Db::name("user")->where('id', $v['buy_user_id'])->data($user_data)->update();

                                //清空买家token
                                Db::name("user_token")->where('user_id', $v['buy_user_id'])->delete();

                            }
                        }
                    }

                    //蛋退给卖家
                    $number = $v['number'] + $v['rate'];
                    $egg_where = array(
                        'user_id'=>array('eq',$v['sell_user_id']),
                        'kind_id'=>array('eq',$v['kind_id'])
                    );
                    $before = Db::name("egg")->where($egg_where)->value('number');
                    $add_rs = Db::name("egg")->where($egg_where)->inc('number',$number)->update();

                    //卖家出售蛋返还日志
                    $log_add = \app\admin\model\egg\Log::saveLog($v['sell_user_id'],$v['kind_id'],1,$v['order_sn'],$v['number'],$before,($before+$v['number']),"超时未付款返还出售");

                    //卖家手续费蛋返还日志
                    $log_fee = \app\admin\model\egg\Log::saveLog($v['sell_user_id'],$v['kind_id'],9,$v['order_sn'],$v['rate'],($before+$v['number']),($before+$number),"超时未付款返还手续费");


                    if ($re == false || $add_rs == false ||  $log_add == false || $log_fee = false || $res_user == false || $res_user_update == false) {
                        DB::rollback();
                        $this->error("买家有效时间未打款自动退款订单失败");
                    } else {
                        //\app\common\library\Hsms::send($v['buy_mobile'], '','order');
                        DB::commit();
                        continue;
                    }
                }//end try
                catch (\Exception $e) {
                    DB::rollback();
                    $this->error("买家有效时间未打款自动退款订单失败");
                }
            }
        }
    }
}
