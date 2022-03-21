<?php
namespace app\api\controller\mall;

use app\common\controller\Api;
use think\Validate;
use think\Config;
use think\Db;
use think\Cache;
use BackblazeB2\Client;
use BackblazeB2\Bucket;

/**
 * 商城订单接口
 * @ApiInternal
 */
class AutoOrder extends Api
{
    protected $noNeedLogin = "*";
    protected $noNeedRight = '*';
    private $limit = 10;

    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 自动退款-超时未发货
     *
     */
    public function autoRefund()
    {
        $send_hours = Config::get("site.send_hours");
        if($send_hours > 0){
            $wh = [];
            $wh['status']   = 2;
            $wh['add_time'] = ['<',time()-$send_hours*3600];
            $list = Db::name("mall_order")->where($wh)->order("add_time asc")->limit(300)->select();
            if(!empty($list)){
                foreach ($list as $key => $value) {
                    Db::startTrans();
                    try {
                        $total_amount = $value['total_price']+$value['rate'];
                        $wh = [];
                        $wh['user_id'] = $value['buy_user_id'];
                        $wh['kind_id'] = $value['kind_id'];
                        $before = Db::name("egg")->where($wh)->value('number');
                        $inc_rs = Db::name("egg")->where($wh)->setInc('number',$total_amount);
                        //写入日志
                        $log_rs = Db::name("egg_log")->insert(['user_id'=>$value['buy_user_id'],'kind_id'=>$value['kind_id'],'type'=>1,'order_sn'=>$value['order_sn'],'number'=>$value['total_price'],'before'=>$before,'after'=>($before+$value['total_price']),'note'=>"卖家超时未发货退款",'createtime'=>time()]);

                        $log_re = true;
                        if($value['rate']>0){         
                            //手续费写入日志
                            $log_re = Db::name("egg_log")->insert(['user_id'=>$value['buy_user_id'],'kind_id'=>$value['kind_id'],'type'=>9,'order_sn'=>$value['order_sn'],'number'=>$value['rate'],'before'=>($before+$value['total_price']),'after'=>($before+$total_amount),'note'=>"卖家超时未发货退款,返还手续费",'createtime'=>time()]);
                        }
                        $rs = Db::name("mall_order")->where("id",$value['id'])->update(["status"=>6]);
                        if($rs && $inc_rs && $log_rs && $log_re){
                            Db::commit();
                        }else{
                            Db::rollback();
                            $this->error("超时未付款-退款失败",$value['order_sn']);
                        }
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success("超时未付款-退款成功",$value['order_sn']);
                }
            }else{
                $this->success("超时未付款-无订单");
            }
        }        
    }

    /**
     * 自动确认-超时未签收
     *
     */
    public function autoSure()
    {
        $voer_hours = Config::get("site.voer_hours");
        if($voer_hours > 0){
            $wh = [];
            $wh['status']    = 3;
            $wh['send_time'] = ['<',time()-$voer_hours*3600];
            $list = Db::name("mall_order")->where($wh)->order("send_time asc")->limit(300)->select();
            if(!empty($list)){
                foreach ($list as $key => $value) {
                    Db::startTrans();
                    try {
                        $wh = [];
                        $wh['user_id'] = $value['sell_user_id'];
                        $wh['kind_id'] = $value['kind_id'];
                        $before = Db::name("egg")->where($wh)->value('number');
                        $inc_rs = Db::name("egg")->where($wh)->setInc('number',$value['total_price']);
                        //写入日志
                        $log_rs = Db::name("egg_log")->insert(['user_id'=>$value['sell_user_id'],'kind_id'=>$value['kind_id'],'type'=>1,'order_sn'=>$value['order_sn'],'number'=>$value['total_price'],'before'=>$before,'after'=>($before+$value['total_price']),'note'=>"商城订单交易完成",'createtime'=>time()]);

                        $rs = Db::name("mall_order")->where("id",$value['id'])->update(["status"=>1,"received_time"=>time()]);
                        if($inc_rs && $log_rs && $rs){
                            Db::commit();
                        }else{
                            Db::rollback();
                            $this->error("超时未确认收获-确认收获失败",$value['order_sn']);
                        }
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success("超时未确认收获-确认收获成功",$value['order_sn']);
                }
            }else{
                $this->success("超时未签收-无订单");
            }
        }        
    }

    /**
     * 自动拉黑超过10天不玩的玩家
     *
     */
    public function autoHidden()
    {
        $black_days = Config::get("site.black_days");
        $days = $black_days??15;
        $wh = [];
        $wh['id']             = ['>',308];
        $wh['status']         = 'normal';
        $wh['is_attestation'] = 1;
        $wh['updatetime']     = ['<',time()-3600*24*$days];
        $list = Db::name("user")->field("id,mobile,note")->where($wh)->limit(200)->select();
        if(!empty($list)){
            foreach ($list as $key => $value) {
                Db::name("user")->where("id",$value['id'])->update(['status'=>'hidden','note'=>"太久不玩拉黑 ".$value['note']]);
            }
        }
        $this->success("自动拉黑玩家");
    }

   /*
    * 图片上传云统一入口
    */ 
   public function cloudImage()
   {
        echo '<pre>';
        // $getB2 = new \app\common\library\Upload();
        // $getB2->getBackblazeb2();
        $config = Config::get('upload');
        $client = new Client($config['accountId'],$config['applicationKey']);

        $this->imgAttestation($config,$client);
        $this->imgCharge($config,$client);
        $this->orderCharge($config,$client);
   }
    
    /*
     * 认证图片上传
     */ 
    private function imgAttestation($config=[],$client,$domain="eggloop.co")
    {        
        $wh = [];
        $wh['u.is_attestation'] = 1;
        $wh['at.add_time'] = ['>=',strtotime("-5 day")];
        $wh['at.front_img'] = ['like',"%www.$domain%"];
        $list = Db::name("egg_attestation")->alias("at")
                    ->field("at.id,at.user_id,at.front_img,at.reverse_img,at.hand_img")
                    ->join("user u","u.id=at.user_id")
                    ->where($wh)
                    ->where('at.add_time','<=',strtotime("-2 day"));
                    ->order("at.id","asc")
                    ->limit($this->limit)
                    ->select();

        print_r($list);        
        return true;
        if(!empty($list))
        {            
            $num = 0;
            echo date("Y-m-d H:i:s")."开始 \n";
            foreach ($list as $key => $value) 
            {
                $front_img = str_replace("https://www.$domain/uploads","",$value['front_img']);
                $path_front = str_replace("https://www.".$domain,"/www/wwwroot/$domain/public",$value['front_img']);
                if(file_exists($path_front))
                {
                    $file1 = $client->upload([
                        'BucketName' => $config['BucketName'],
                        'BucketId' => $config['BucketId'],
                        'FileName' => $front_img,
                        'Body' => fopen($path_front, 'r'),
                    ]); 
                    $json = json_encode($file1);
                    $front = json_decode($json,1);
                    if(!empty($front['id'])){
                        $data = [];
                        $data["front_img"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['front_img']);
                        Db::name("egg_attestation")->where("id",$value['id'])->update($data);
                        
                        $str = "'"."/uploads".$front_img."'";
                        Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                        unlink($path_front);
                        echo $value['id']." #user_id: ".$value['user_id']." =>front_img \n";
                    }
                }

                $reverse_img = str_replace("https://www.$domain/uploads","",$value['reverse_img']);
                $path_reverse = str_replace("https://www.".$domain,"/www/wwwroot/$domain/public",$value['reverse_img']);                
                if(file_exists($path_reverse))
                {                
                    $file2 = $client->upload([
                        'BucketName' => $config['BucketName'],
                        'BucketId' => $config['BucketId'],
                        'FileName' => $reverse_img,
                        'Body' => fopen($path_reverse, 'r'),
                    ]); 
                    $json = json_encode($file2);
                    $reverse = json_decode($json,1);
                    if(!empty($reverse['id'])){
                        $data = [];
                        $data["reverse_img"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['reverse_img']);
                        Db::name("egg_attestation")->where("id",$value['id'])->update($data);
                                                
                        $str = "'"."/uploads".$reverse_img."'";
                        Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                        unlink($path_reverse);
                        echo $value['id']." #user_id: ".$value['user_id']." =>reverse_img \n";
                    }
                }

                $hand_img = str_replace("https://www.$domain/uploads","",$value['hand_img']);
                $path_hand = str_replace("https://www.".$domain,"/www/wwwroot/$domain/public",$value['hand_img']);                
                if(file_exists($path_hand))
                {
                    $file3 = $client->upload([
                        'BucketName' => $config['BucketName'],
                        'BucketId' => $config['BucketId'],
                        'FileName' => $hand_img,
                        'Body' => fopen($path_hand, 'r'),
                    ]); 
                    $json = json_encode($file3);
                    $hand = json_decode($json,1);
                    if(!empty($hand['id'])){
                        $data = [];
                        $data["hand_img"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['hand_img']);
                        Db::name("egg_attestation")->where("id",$value['id'])->update($data);
                        
                        $str = "'"."/uploads".$hand_img."'";
                        Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                        unlink($path_hand);
                        echo $value['id']." #user_id: ".$value['user_id']." =>hand_img \n";
                    }
                }
            }
            echo  date("Y-m-d H:i:s")."结束 \n";
        }
    }


    /*
     * 收款图片上传
     */ 
    private function imgCharge($config=[],$client,$domain="eggloop.co")
    {        
        $wh = [];
        $wh['add_time'] = ['>=',strtotime("-5 day")];
        $wh['image'] = ['like',"%www.$domain%"];
        $list = Db::name("egg_charge_code")->field("id,user_id,image")
                        ->where($wh)
                        ->order("id","asc")
                        ->limit($this->limit)
                        ->select();

        print_r($list);             
        return true;
        if(!empty($list))
        {            
            echo date("Y-m-d H:i:s")."开始 \n";
            foreach ($list as $key => $value) 
            {
                $front_img = str_replace("https://www.$domain/uploads","",$value['image']);
                $path_front = str_replace("https://www.".$domain,"/www/wwwroot/$domain/public",$value['image']);
                if(file_exists($path_front))
                {
                    $file1 = $client->upload([
                        'BucketName' => $config['BucketName'],
                        'BucketId' => $config['BucketId'],
                        'FileName' => $front_img,
                        'Body' => fopen($path_front, 'r'),
                    ]); 
                    $json = json_encode($file1);
                    $front = json_decode($json,1); 
                    if(!empty($front['id'])){
                        $data = [];
                        $data["image"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['image']);
                        Db::name("egg_charge_code")->where("id",$value['id'])->update($data);
                        
                        $str = "'"."/uploads".$front_img."'";
                        Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                        unlink($path_front);
                        echo $value['id']." # user_id: ".$value['user_id']."\n";
                    }
                }else{
                    $data = [];
                    $data["image"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['image']);
                    Db::name("egg_charge_code")->where("id",$value['id'])->update($data);
                    $str = "'"."/uploads".$front_img."'";
                    Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                    echo $value['id']." #N user_id: ".$value['user_id']."\n";
                }
            }
            echo  date("Y-m-d H:i:s")."结束 \n";
        }
    }
    
    
    /*
     * 蛋订单图片上传
     */ 
    private function orderCharge($config=[],$client,$domain="eggloop.co")
    {        
        $wh = [];
        $wh['status'] = 1;
        $wh['pay_time'] = ['>=',strtotime("-5 day")];
        $wh['pay_img'] = ['like',"%www.$domain%"];
        $list = Db::name("egg_order")->field("id,order_sn,attestation_image,pay_img")
                        ->where($wh)
                        ->order("id","asc")
                        ->limit($this->limit)
                        ->select();
        print_r($list);          
        return true;
        if(!empty($list))
        {            
            echo date("Y-m-d H:i:s")."开始 \n";
            foreach ($list as $key => $value) 
            {
                $attestation_image = str_replace("https://www.$domain/uploads","",$value['attestation_image']);
                $path_attestation = str_replace("https://www.".$domain,"/www/wwwroot/$domain/public",$value['attestation_image']);
                if(file_exists($path_attestation))
                {
                    $file1 = $client->upload([
                        'BucketName' => $config['BucketName'],
                        'BucketId' => $config['BucketId'],
                        'FileName' => $attestation_image,
                        'Body' => fopen($path_attestation, 'r'),
                    ]); 
                    $json = json_encode($file1);
                    $front = json_decode($json,1);    
                    if(!empty($front['id'])){
                        $data = [];
                        $data["attestation_image"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['attestation_image']);
                        Db::name("egg_order")->where("id",$value['id'])->update($data);
                        
                        $str = "'"."/uploads".$attestation_image."'";
                        Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                        unlink($path_attestation);
                        echo $value['id']." 1# order_sn : ".$value['order_sn']."\n";
                    }
                }

                $pay_img = str_replace("https://www.$domain/uploads","",$value['pay_img']);
                $path_pay = str_replace("https://www.".$domain,"/www/wwwroot/$domain/public",$value['pay_img']);
                if(file_exists($path_pay))
                {
                    $file2 = $client->upload([
                        'BucketName' => $config['BucketName'],
                        'BucketId' => $config['BucketId'],
                        'FileName' => $pay_img,
                        'Body' => fopen($path_pay, 'r'),
                    ]); 
                    $json = json_encode($file2);
                    $pay = json_decode($json,1);    
                    if(!empty($pay['id'])){
                        $data = [];
                        $data["pay_img"] = str_replace("www.$domain/uploads","oss.eggloop.co",$value['pay_img']);
                        Db::name("egg_order")->where("id",$value['id'])->update($data);
                        
                        $str = "'"."/uploads".$pay_img."'";
                        Db::query("UPDATE fa_attachment SET url = REPLACE(url, '/uploads', 'https://oss.eggloop.co') WHERE url IN ($str)");
                        unlink($path_pay);
                        echo $value['id']." 2# order_sn : ".$value['order_sn']."\n";
                    }
                }
            }
            echo  date("Y-m-d H:i:s")."结束 \n";
        }
    }
}