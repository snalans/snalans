<?php

namespace app\common\model;

use think\Db;
use think\Model;
use fast\Random;

/**
 * 会员模型
 */
class User extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';
    // 追加属性
    protected $append = [
        'url',
    ];

    /**
     * 获取个人URL
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getUrlAttr($value, $data)
    {
        return "/u/" . $data['id'];
    }

    /**
     * 获取头像
     * @param string $value
     * @param array  $data
     * @return string
     */
    public function getAvatarAttr($value, $data)
    {
        if (!$value) {
            //如果不需要启用首字母头像，请使用
            //$value = '/assets/img/avatar.png';
            $value = letter_avatar($data['nickname']);
        }
        return $value;
    }

    /**
     * 获取会员的组别
     */
    public function getGroupAttr($value, $data)
    {
        return UserGroup::get($data['group_id']);
    }

    /**
     * 获取验证字段数组值
     * @param string $value
     * @param array  $data
     * @return  object
     */
    public function getVerificationAttr($value, $data)
    {
        $value = array_filter((array)json_decode($value, true));
        $value = array_merge(['email' => 0, 'mobile' => 0], $value);
        return (object)$value;
    }

    /**
     * 设置验证字段
     * @param mixed $value
     * @return string
     */
    public function setVerificationAttr($value)
    {
        $value = is_object($value) || is_array($value) ? json_encode($value) : $value;
        return $value;
    }

    /**
     * 变更会员余额
     * @param int    $money   余额
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function money($money, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $money != 0) {
                $before = $user->money;
                //$after = $user->money + $money;
                $after = function_exists('bcadd') ? bcadd($user->money, $money, 2) : $user->money + $money;
                //更新会员信息
                $user->save(['money' => $after]);
                //写入日志
                MoneyLog::create(['user_id' => $user_id, 'money' => $money, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 变更会员积分
     * @param int    $score   积分
     * @param int    $user_id 会员ID
     * @param string $memo    备注
     */
    public static function score($score, $user_id, $memo)
    {
        Db::startTrans();
        try {
            $user = self::lock(true)->find($user_id);
            if ($user && $score != 0) {
                $before = $user->score;
                $after = $user->score + $score;
                $level = self::nextlevel($after);
                //更新会员信息
                $user->save(['score' => $after, 'level' => $level]);
                //写入日志
                ScoreLog::create(['user_id' => $user_id, 'score' => $score, 'before' => $before, 'after' => $after, 'memo' => $memo]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 根据积分获取等级
     * @param int $score 积分
     * @return int
     */
    public static function nextlevel($score = 0)
    {
        $lv = array(1 => 0, 2 => 30, 3 => 100, 4 => 500, 5 => 1000, 6 => 2000, 7 => 3000, 8 => 5000, 9 => 8000, 10 => 10000);
        $level = 1;
        foreach ($lv as $key => $value) {
            if ($score >= $value) {
                $level = $key;
            }
        }
        return $level;
    }

    /**
     * 获取邀请码
     * @param int $type 类型 1=邀请码 2=会员编号
     * @return string
     */
    public static function getInviteCode($type=1)
    {
        if($type==1){
            $code = Random::alnum(6);
            $result = Db::name("user")->where("serial_number",$code)->find();
        }else{
            $code = Random::alnum(8);
            $result = Db::name("user")->where("serial_number",$code)->find();
        }
        if($result){
            return self::getInviteCode($type);
        }
        return $code;
    }

    /**
     * 生成默认的蛋和窝数据
     * @param int $user_id 会员id
     * @return boole
     */
    public static function defaultEggNest($user_id=0)
    {
        $result = Db::name("egg_kind")->select();
        if(!empty($result)){
            $datas = [];
            foreach ($result as $key => $value) {
                $data = [];
                $data['user_id'] = $user_id;
                $data['kind_id'] = $value['id'];
                $datas[] = $data;
            }
            Db::name("egg")->insertAll($datas);
        }

        $nest = Db::name("egg_nest_kind")->select();
        if(!empty($nest)){
            $datas = [];
            $nests = [];
            foreach ($nest as $k => $val) {
                if($val['default']>0){
                    for ($i=1; $i <= $val['default']; $i++) { 
                        $data = [];
                        $data['user_id']        = $user_id;
                        $data['kind_id']        = $val['kind_id'];
                        $data['nest_kind_id']   = $val['id'];
                        $data['position']       = $i;
                        $datas[] = $data;
                    }     
                    $ne = [];
                    $ne['user_id']    = $user_id;
                    $ne['nest_kind_id'] = $val['id'];
                    $ne['type']       = 1;
                    $ne['number']     = $val['default'];
                    $ne['note']       = '注册赠送';
                    $ne['createtime'] = time();
                    $nests[] = $ne;
                }
            }
            Db::name("egg_hatch")->insertAll($datas);
            Db::name("egg_nest_log")->insertAll($nests);
        }
    }


}
