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
    protected $noNeedLogin = ['index','getNews','getUrl'];
    protected $noNeedRight = ['*'];

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
     * banner和文章
     *
     * @ApiMethod (GET)
     * @param string $type  类型 1=banner 2=公告
     */
    public function getNews()
    {
        $type = $this->request->get('type');
        $wh = [];
        $wh['status']       = 1;
        $wh['news_type_id'] = $type;
        $result = Db::name("egg_news")
                    ->field(['news_type_id','weigh','status','add_time'],true)
                    ->where($wh)
                    ->order("weigh","DESC")
                    ->select();
        $this->success('success',$result);
    }

    /**
     * 获取下载app地址
     * 
     * @ApiMethod (GET)
     * @ApiReturnParams   (name="adroid", type="string", description="安卓下载地址")
     * @ApiReturnParams   (name="ios", type="string", description="苹果下载地址")
     * @ApiReturnParams   (name="app_version", type="string", description="app版本号")
     */
    public function getUrl()
    {
        $data['adroid'] = Config::get("site.android_url");
        $data['ios']    = Config::get("site.ios_url");
        $data['app_version']    = Config::get("site.app_version");
        $this->success('success',$data);
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
