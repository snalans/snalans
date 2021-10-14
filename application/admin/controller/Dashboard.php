<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");

        $egg = Db::name("egg_log_".date("Y_m"))
                    ->whereTime('createtime', 'd')
                    ->where("number",">",0)
                    ->group("kind_id")
                    ->column("kind_id,sum(number)");
        $this->view->assign([
            'egg1'            => isset($egg['1'])?$egg['1']:0,
            'egg2'            => isset($egg['2'])?$egg['2']:0,
            'egg3'            => isset($egg['3'])?$egg['3']:0,
            'egg4'            => isset($egg['4'])?$egg['4']:0,
            'totaluser'       => User::count(),
            'totaladdon'      => count(get_addon_list()),
            'totaladmin'      => Admin::count(),
            'totalcategory'   => \app\common\model\Category::count(),
            'todayusersignup' => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'  => User::whereTime('logintime', 'today')->count(),
            'sevendau'        => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            'thirtydau'       => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            'threednu'        => User::whereTime('jointime', '-3 days')->count(),
            'sevendnu'        => User::whereTime('jointime', '-7 days')->count(),
            'dbtablenums'     => count($dbTableList),
            'dbsize'          => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'attachmentnums'  => Attachment::count(),
            'attachmentsize'  => Attachment::sum('filesize'),
            'picturenums'     => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'     => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));
        $egglist1 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where(['e.kind_id'=>1,'u.status'=>'normal'])
                    ->order(['e.number'=>'desc'])
                    ->limit(5)
                    ->select();
        $egglist2 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where(['e.kind_id'=>2,'u.status'=>'normal'])
                    ->order(['e.number'=>'desc'])
                    ->limit(5)
                    ->select();
        $egglist3 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where(['e.kind_id'=>3,'u.status'=>'normal'])
                    ->order(['e.number'=>'desc'])
                    ->limit(5)
                    ->select();
        $egglist4 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where(['e.kind_id'=>4,'u.status'=>'normal'])
                    ->order(['e.number'=>'desc'])
                    ->limit(5)
                    ->select();
        $this->view->assign("egglist1",$egglist1);
        $this->view->assign("egglist2",$egglist2);
        $this->view->assign("egglist3",$egglist3);
        $this->view->assign("egglist4",$egglist4);

        return $this->view->fetch();
    }

}
