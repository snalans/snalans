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

        $degg = Db::name("egg_log")
                    ->whereTime('createtime', 'd')
                    ->group("kind_id")
                    ->column("kind_id,sum(number)");

        // $minfo = Db::name("egg_log_all")->where("createtime",date("Y-m",strtotime("-1 month")))->select();
        // if(empty($minfo)){
        //     $legg = Db::name("egg_log_".date("Y_m",strtotime("-1 month")))
        //             ->whereTime('createtime', 'last month')
        //             ->group("kind_id")
        //             ->column("kind_id,sum(number)");
        //     $datas = [];
        //     for ($i=1; $i <= 4; $i++) { 
        //         $data = [];
        //         $data['kind_id']    = $i;
        //         $data['number']     = isset($legg[$i])?$legg[$i]:0;
        //         $data['createtime'] = date("Y-m",strtotime("-1 month"));
        //         $datas[] = $data;
        //     }
        //     Db::name("egg_log_all")->insertAll($datas);
        // }

        $megg = Db::name("egg")
                    ->group("kind_id")
                    ->column("kind_id,sum(number)");
        $this->view->assign([
            'egg1'            => isset($megg['1'])?$megg['1']:0,
            'egg2'            => isset($megg['2'])?$megg['2']:0,
            'egg3'            => isset($megg['3'])?$megg['3']:0,
            'egg4'            => isset($megg['4'])?$megg['4']:0,
            'degg1'           => isset($degg['1'])?$degg['1']:0,
            'degg2'           => isset($degg['2'])?$degg['2']:0,
            'degg3'           => isset($degg['3'])?$degg['3']:0,
            'degg4'           => isset($degg['4'])?$degg['4']:0,
            'totaluser'       => User::count(),
            'totaladdon'      => count(get_addon_list()),
            'totaladmin'      => Admin::count(),
            'totalcategory'   => \app\common\model\Category::count(),
            'todayusersignup' => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'  => User::whereTime('updatetime', 'today')->count(),
            'sevendau'        => User::whereTime('updatetime', '-7 days')->count(),
            'thirtydau'       => User::whereTime('updatetime', '-30 days')->count(),
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
        $wh = [];
        $wh['e.number']     = ['>',0];
        $wh['e.kind_id']    = 1;
        $egglist1 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where(['e.kind_id'=>1,'u.status'=>'normal'])
                    ->order(['e.number'=>'desc'])
                    ->limit(200)
                    ->select();
        $wh['e.kind_id']    = 2;
        $egglist2 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where($wh)
                    ->order(['e.number'=>'desc'])
                    ->limit(200)
                    ->select();
        $wh['e.kind_id']    = 3;
        $egglist3 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where($wh)
                    ->order(['e.number'=>'desc'])
                    ->limit(200)
                    ->select();
        $wh['e.kind_id']    = 4;
        $egglist4 = Db::name("egg")->alias("e")
                    ->field("u.mobile,e.number")
                    ->join("user u","u.id=e.user_id","LEFT")
                    ->where($wh)
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
