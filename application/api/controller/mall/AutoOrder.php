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
     * 认证图片上传
     */ 
    public function imgAttestation()
    {        
        // exit;
        $getB2 = new \app\common\library\Upload();
        $getB2->getBackblazeb2();

        $config = Config::get('upload');
        $client = new Client($config['accountId'],$config['applicationKey']);
        $domain = "eggloop.co";

        $attestation_id = Cache::get('attestation_id',500000);
        $wh = [];
        $wh['id'] = ['>',$attestation_id];
        $wh['front_img'] = ['like',"%www.$domain%"];
        $list = Db::name("egg_attestation")->where($wh)->order("id","asc")->limit(14)->select();
        
        // echo '<pre>';
        // print_r($list);        
        // exit;
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
                        echo $value['id']."=>front_img \n";
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
                        echo  $value['id']."=>reverse_img \n";
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
                        echo $value['id']."=>hand_img \n";
                    }
                }
                Cache::set('attestation_id',$value['id'],60*60);
            }
            echo  date("Y-m-d H:i:s")."结束 \n";
        }
    }


    /*
     * 收款图片上传
     */ 
    public function imgCharge()
    {        
        $getB2 = new \app\common\library\Upload();
        $getB2->getBackblazeb2();
        $config = Config::get('upload');
        $client = new Client($config['accountId'],$config['applicationKey']);
        $domain = "eggloop.co";

        $code_id = Cache::get('code_id',500000);
        $wh = [];
        $wh['id'] = ['>',$code_id];
        $wh['image'] = ['like',"%www.$domain%"];
        $list = Db::name("egg_charge_code")->field("id,image")
                        ->where($wh)
                        ->order("id","desc")
                        ->limit(5)
                        ->select();
        
        // echo '<pre>';
        // print_r($list);        
        // exit;
        if(!empty($list))
        {            
            $num = 0;
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
                        echo $value['id']."\n";
                    }
                }
                Cache::set('code_id',$value['id'],60*60);
            }
            echo  date("Y-m-d H:i:s")."结束 \n";
        }
    }
}