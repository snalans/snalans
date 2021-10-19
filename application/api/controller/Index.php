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
     * @ApiReturnParams   (name="is_agreement", type="int", description="是否开启协议 1=是 2=否")
     * @ApiReturnParams   (name="adroid", type="string", description="安卓下载地址")
     * @ApiReturnParams   (name="ios", type="string", description="苹果下载地址")
     * @ApiReturnParams   (name="app_version", type="string", description="app版本号")
     */
    public function init()
    {
        $data = [];
        $data['adroid']         = Config::get("site.android_url");
        $data['ios']            = Config::get("site.ios_url");
        $data['is_agreement']   = Config::get("site.is_agreement");
        $data['app_version']    = Config::get("site.app_version");
        $this->success('', $data);
    }

    /**
     * 首页 白蛋兑换、彩蛋回收
     *
     */
    public function index()
    {
        $wh = [];
        $wh['id'] = ['in',[1,5]];
        $result = Db::name("egg_kind")->field(['valid_number','weigh'],true)->where($wh)->select();
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

        $result = Db::name("egg_news_type")->where("id",$type_id)->find();
        $this->success('success',$result);
    }

    /**
     * 文章列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="type_id", type="int", description="类型 1=轮播 2=公告 3=协议")
     * 
     * @ApiReturnParams   (name="id", type="string", description="文章id")
     * @ApiReturnParams   (name="title", type="int", description="标题")
     * @ApiReturnParams   (name="description", type="int", description="描述")
     * @ApiReturnParams   (name="image", type="int", description="图片")
     * @ApiReturnParams   (name="url", type="int", description="跳转地址")
     */
    public function getNews()
    {
        $type_id = $this->request->get('type_id',1);
        $wh = [];
        $wh['status']       = 1;
        $wh['news_type_id'] = $type_id;
        $result = Db::name("egg_news")
                    ->field("id,title,description,image,url")
                    ->where($wh)
                    ->order("weigh","DESC")
                    ->select();
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
        if(empty($id)){
            $wh['id'] = $id;
        }
        if(empty($title)){
            $wh['title'] = $title;
        }        
        $result = Db::name("egg_news")->where($wh)->find();
        if(!empty($result)){
            $result['add_time'] = date("Y-m-d H:i:s",$result['add_time']);
        }
        $this->success('success',$result);
    }


    /**
     * 积分兑换
     * 
     * @ApiMethod (POST)
     * @ApiParams   (name="kind_id", type="integer", description="名称id")
     * @ApiParams   (name="number", type="integer", description="数量")
     */
    public function exchange()
    {
        $kind_id  = $this->request->post('kind_id',1);
        $number  = $this->request->post('number',1);
        $wh = [];
        $wh['id']    = $kind_id;
        $wh['point'] = ['>',0];
        $point = Db::name("egg_kind")->where($wh)->value("point");
        if(empty($point)){
            $this->error(__('Parameter error'));
        }
        $score = $point*$number;
        if($score > $this->auth->score || !is_numeric($number)){
            $this->error(__('Not enough points'));
        }

        Db::startTrans();
        $before = $this->auth->score;
        $after = $this->auth->score - $score;
        $score_rs = Db::name("user")->where("id",$this->auth->id)->update(['score'=>$after]);  

        $wh = [];
        $wh['user_id']      = $this->auth->id;
        $wh['kind_id']      = $kind_id;
        $num_rs = Db::name("egg")->where($wh)->setInc("number",$number);
        //写入日志
        $log_rs = Db::name("egg_log_".date("Y_m"))->insert(['user_id'=>$this->auth->id,'kind_id'=>$kind_id,'type'=>5,'order_sn'=>'','number'=>$number,'note'=>"积分兑换",'createtime'=>time()]);
        $score_log = Db::name("user_score_log")->insert(['user_id' => $this->auth->id, 'score' => $score, 'before' => $before, 'after' => $after, 'memo' => "积分兑换"]);
        if($score_rs && $num_rs && $log_rs && $score_log){
            Db::commit();
            $this->success(__('Exchange successful'));    
        }else{
            Db::rollback();
            $this->error(__('Exchange failure, please try again'));    
        }
    }
    
}
