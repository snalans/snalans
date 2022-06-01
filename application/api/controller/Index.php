<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Config;
use think\Db;

/**
 * 首页接口
 * @ApiWeigh   (30)
 */
class Index extends Api
{
    protected $noNeedLogin = ['init','index','getNewsType','getNews','getNewsDetail','getUrl'];
    protected $noNeedRight = ['*'];


    /**
     * 加载初始化
     * @ApiReturnParams   (name="egg_info", type="string", description="蛋的信息 name:蛋名称 image：蛋图片 egg_image:窝里蛋 ch_image:小鸡图片 bg_image:背景图片 stock:库存数量")
     * @ApiReturnParams   (name="share_image", type="int", description="分享背景图")
     * @ApiReturnParams   (name="invite_url", type="int", description="邀请地址")
     * @ApiReturnParams   (name="is_open", type="int", description="是否开启APP 1=开启 0=关闭")
     * @ApiReturnParams   (name="is_agreement", type="int", description="是否开启协议 1=开启 0=关闭")
     * @ApiReturnParams   (name="adroid", type="string", description="安卓下载地址")
     * @ApiReturnParams   (name="ios", type="string", description="苹果下载地址")
     * @ApiReturnParams   (name="app_version", type="string", description="app版本号")
     * @ApiReturnParams   (name="apk_version", type="string", description="apk包版本号")
     * @ApiReturnParams   (name="upgrade", type="string", description="是否强制升级 0=否 1=是")
     * @ApiReturnParams   (name="tips", type="string", description="更新提示语")
     * @ApiReturnParams   (name="re_attestation", type="int", description="是否需要认证 1=是 0=否")
     */
    public function init()
    {
        $data = [];
        $data['egg_info']       = Db::name("egg_kind")->cache(true,300)->field("id,name,image,egg_image,ch_image,bg_image,stock")->order("id","asc")->select();
        $data['share_image']    = Db::name("egg_news")->cache(true,300)->where("news_type_id",4)->value("image");
        $data['invite_url']     = Config::get("site.invite_url")??"http://h5.aneggloop.co";
        $data['is_open']        = Config::get("site.is_open")??1;
        $data['adroid']         = Config::get("site.android_url")??"";
        $data['ios']            = Config::get("site.ios_url")??"";
        $data['is_agreement']   = Config::get("site.is_agreement")??0;
        $data['app_version']    = Config::get("site.app_version")??"";
        $data['apk_version']    = Config::get("site.apk_version")??"";
        $data['upgrade']        = Config::get("site.upgrade")??0;
        $data['tips']           = Config::get("site.tips")??"";
        $data['re_attestation'] = 0;
        $time = Config::get("site.atte_time")??0;
        if($this->auth->updatetime < strtotime($time) && $this->auth->is_attestation == 1 && $this->auth->id > 308){
            $data['re_attestation'] = 1;
        }
        $this->success('success', $data);
    }

    /**
     * 首页-彩蛋回收,其它蛋兑换
     *
     */
    public function index()
    {
        $wh = [];
        $wh['status'] = 1;
        $result = Db::name("egg_kind")->cache(true,300)->field(['valid_number','rate_config','weigh'],true)->where($wh)->order("weigh","DESC")->select();
        $this->success('success',$result);
    }

    /**
     * 文章分类
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="type_id", type="int", description="类型 1=轮播 2=公告 3=协议")
     * 
     * @ApiReturnParams   (name="name", type="string", description="名称")
     * @ApiReturnParams   (name="status", type="int", description="状态 1=开启 0=关闭")
     * @ApiReturnParams   (name="weigh", type="int", description="权重")
     */
    public function getNewsType()
    {
        $type_id = $this->request->get('type_id',3);
        $result = Db::name("egg_news_type")->cache(true)->where("id",$type_id)->find();
        $this->success('success',$result);
    }

    /**
     * 文章列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="type_id", type="int", description="类型 1=轮播 2=公告 3=协议")
     * @ApiParams   (name="page", type="integer", description="页码")
     * @ApiParams   (name="per_page", type="integer", description="数量")
     * 
     * @ApiReturnParams   (name="id", type="string", description="文章id")
     * @ApiReturnParams   (name="title", type="int", description="标题")
     * @ApiReturnParams   (name="image", type="sting", description="图片")
     * @ApiReturnParams   (name="description", type="int", description="描述")
     * @ApiReturnParams   (name="add_time", type="sting", description="时间")
     */
    public function getNews()
    {
        $type_id    = $this->request->get('type_id',1);
        $page       = $this->request->get("page",1);        
        $per_page   = $this->request->get("per_page",50);
        $wh = [];
        $wh['status']       = 1;
        $wh['news_type_id'] = $type_id;
        $result = Db::name("egg_news")->cache(true,300)
                    ->field("id,title,description,image,add_time")
                    ->where($wh)
                    ->order("weigh","DESC")
                    ->paginate($per_page)->each(function($item){
                        $item['add_time'] = date("Y-m-d H:i",$item['add_time']);
                        return $item;
                    });
        $this->success('success',$result);
    }

    /**
     * 文章详情
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="id", type="int", description="文章id")
     * @ApiParams   (name="title", type="string", description="标题")
     * 
     * @ApiReturnParams   (name="id", type="string", description="文章id")
     * @ApiReturnParams   (name="title", type="string", description="标题")
     * @ApiReturnParams   (name="description", type="string", description="描述")
     * @ApiReturnParams   (name="image", type="string", description="图片")
     * @ApiReturnParams   (name="url", type="string", description="跳转地址")
     * @ApiReturnParams   (name="content", type="string", description="内容")
     * @ApiReturnParams   (name="add_time", type="string", description="添加时间")
     */
    public function getNewsDetail()
    {
        $id = $this->request->get('id',"");
        $title = $this->request->get('title',"");
        $wh = [];
        if(!empty($id)){
            $wh['id'] = $id;
        }else if(!empty($title)){
            $wh['title'] = $title;
        }        
        $result = Db::name("egg_news")->cache(true)->where($wh)->find();
        if(!empty($result)){
            $result['add_time'] = date("Y-m-d H:i:s",$result['add_time']);
        }
        $this->success('success',$result);
    }


    /**
     * 积分兑换
     * 
     * @ApiMethod (POST)
     * @ApiParams   (name="kind_id", type="integer", description="蛋分类ID")
     * @ApiParams   (name="number", type="integer", description="数量")
     * @ApiParams   (name="paypwd", type="string", description="支付密码")
     */
    public function exchange()
    {
        $kind_id        = $this->request->post('kind_id',1);
        $number         = $this->request->post('number',1);
        $paypwd         = $this->request->post('paypwd',"");

        if(empty($number) || !is_numeric($number) || !in_array($kind_id,[1,2,3])){
            $this->error(__('Parameter error'));
        }

        if(!preg_match("/^[0-9]*$/",$number)){                
            $this->error("兑换数量要为正整数");
        }

        $wh = [];
        $wh['id'] = $kind_id;
        $kinfo = Db::name("egg_kind")->field("name,point,stock")->where($wh)->find();
        if(($kinfo['stock']-$number) < 0){
            $this->error("库存不够,无法兑换");
        }

        $auth = new \app\common\library\Auth();
        if ($this->auth->paypwd != $auth->getEncryptPassword($paypwd, $this->auth->salt)) {
            $this->error('支付密码错误');
        }

        $wh = [];
        $wh['kind_id'] = $kind_id;
        $wh['user_id'] = $this->auth->id;
        $info = Db::name("egg")->where($wh)->find();

        $score = $kinfo['point']*$number;
        if($score > $info['point']){
            $this->error(__('Not enough points'));
        }
        try {
            Db::startTrans();            
            $wh = [];
            $wh['user_id']      = $this->auth->id;
            $wh['kind_id']      = $kind_id;
            $num_rs = Db::name("egg")->where($wh)->inc("number",$number)->dec("point",$score)->update();
            //写入日志
            $log_rs = Db::name("egg_log")->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>5,'number'=>$number,'before'=>$info['number'],'after'=>($info['number']+$number),'note'=>"蛋积分兑换",'createtime'=>time()]);
            $score_log = Db::name("egg_score_log")->insert(['user_id' => $this->auth->id, 'score' => -$score, 'kind_id' => $kind_id, 'type'=>2,'memo' => "积分兑换:".$number."个".$kinfo['name'],'createtime'=>time()]);
            $dec = Db::name('egg_kind')->where("id",$kind_id)->dec("stock",$number)->update();
            if($num_rs && $log_rs && $score_log && $dec){
                Db::commit();
                $this->success(__('Exchange successful'));    
            }else{
                Db::rollback();
                $this->error(__('Exchange failure, please try again'));    
            }            
        } catch (Exception $e) {
            Db::rollback();
            $this->error("系统忙，请稍后兑换");   
        }
    }
    
}
