<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Config;
use think\Db;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功');
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
        $this->success('请求成功',$result);
    }

    /**
     * 获取下载app地址
     * 
     * @ApiMethod (GET)
     */
    public function getUrl()
    {
        $data['adroid'] = Config::get("site.android_url");
        $data['ios']    = Config::get("site.ios_url");
        $this->success('请求成功',$data);
    }
}
