<?php
namespace app\common\library;

use think\Hook;
use think\Log;
use fast\Http;

/**
 * 短信验证码类
 */
class Isms
{
    /**
     * 验证码有效时长
     * @var int
     */
    protected static $expire = 120;

    /**
     * 最大允许检测的次数
     * @var int
     */
    protected static $maxCheckNums = 10;
    protected static $uri        = "http://210.51.190.233:8085/mt/mt3.ashx";
    protected static $user       = 'wdnc888'; // 你的用户名, 必须有值
    protected static $password   = 'wdnc888888'; // 你的密码, 必须有值


    /**
     * 获取最后一次手机发送的数据
     *
     * @param   int    $mobile 手机号
     * @param   string $event  事件
     * @return  Sms
     */
    public static function get($mobile, $event = 'default')
    {
        $sms = \app\common\model\Sms::
        where(['mobile' => $mobile, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        Hook::listen('sms_get', $sms, null, true);
        return $sms ? $sms : null;
    }

    /**
     * 发送验证码
     *
     * @param   int    $mobile 手机号
     * @param   int    $code   验证码,为空时将自动生成4位数字
     * @param   string $event  事件
     * @return  boolean
     */
    public static function send($mobile, $code = null, $event = 'default')
    {
        $code = is_null($code) ? mt_rand(1000, 9999) : $code;
        $time = time();
        $ip = request()->ip();
        $sms = \app\common\model\Sms::create(['event' => $event, 'mobile' => $mobile, 'code' => $code, 'ip' => $ip, 'createtime' => $time]);
        $log_msg = '【蛋孵鸡,鸡生蛋】';
        if($event=='resetpwd'){                
            $log_msg .= '您申请重置登录密码，验证码：'.$code.'。';
        }else if($event=='resetpay'){                
            $log_msg .= '您申请重置支付密码，验证码：'.$code.'。';
        }else if($event=='login'){                
            $log_msg .= '您申请登录，验证码：'.$code.'。';
        }else if($event=='register'){                
            $log_msg .= '您申请注册会员，验证码：'.$code.'。';
        }else{
            return false;
        }
        $result = self::sendSms($mobile,$log_msg);
        if (!$result) {
            $sms->delete();
            return false;
        }
        return true;
    }

    /**
     * 发送通知
     *
     * @param   mixed  $mobile   手机号,多个以,分隔
     * @param   string $msg      消息内容
     * @param   string $template 消息模板
     * @return  boolean
     */
    public static function notice($mobile, $msg = '', $template = null)
    {
        $log_msg = '【爱运动】'.$msg;
        $result = self::sendSms($mobile,$log_msg);
        return $result ? true : false;
    }

    /**
     * 校验验证码
     *
     * @param   int    $mobile 手机号
     * @param   int    $code   验证码
     * @param   string $event  事件
     * @return  boolean
     */
    public static function check($mobile, $code, $event = 'default')
    {
        $time = time() - self::$expire;
        $sms = \app\common\model\Sms::where(['mobile' => $mobile, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        if ($sms) {
            if ($sms['createtime'] > $time && $sms['times'] <= self::$maxCheckNums) {
                $correct = $code == $sms['code'];
                if (!$correct) {
                    $sms->times = $sms->times + 1;
                    $sms->save();
                    return false;
                } else {
                    $result = \app\common\model\Sms::where(['id' => $sms['id']])->setInc("times");
                    return $result;
                }
            } else {
                // 过期则清空该手机验证码
                self::flush($mobile, $event);
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 清空指定手机号验证码
     *
     * @param   int    $mobile 手机号
     * @param   string $event  事件
     * @return  boolean
     */
    public static function flush($mobile, $event = 'default')
    {
        \app\common\model\Sms::
        where(['mobile' => $mobile, 'event' => $event])
            ->delete();
        Hook::listen('sms_flush');
        return true;
    }

    /**
     * 短信内容HEX编码
     * dataCoding = 8 ,支持所有国家的语言，建议直接使用 8
     *
     * @param   string $dataCoding  为编码方式
     * @param   string $realStr     为短信内容
     * @return  string
     */
    public static function encodeHexStr($realStr,$dataCoding=8)
    {
        if ($dataCoding == 15){
            return strtoupper(bin2hex(iconv('UTF-8', 'GBK', $realStr)));               
        }else if ($dataCoding == 3){
            return strtoupper(bin2hex(iconv('UTF-8', 'ISO-8859-1', $realStr)));               
        }else if ($dataCoding == 8){
            return strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $realStr)));   
        }else{
            return strtoupper(bin2hex(iconv('UTF-8', 'ASCII', $realStr)));
        }
    }

    /**
     * 发送手机短信
     * @param unknown $mobile 手机号
     * @param unknown $content 短信内容
     */
    public static function sendSms($mobile,$content) {
        // 参数数组
        $data = [
            'src'       => self::$user, 
            'pwd'       => self::$password, 
            'ServiceID' => 'SEND', //固定，不需要改变
            'dest'      => $mobile, // 你的目的号码【收短信的电话号码】, 必须有值
            'sender'    => '', // 你的原号码,可空【大部分国家原号码带不过去，只有少数国家支持透传，所有一般为空】
            'codec'     => '8', // 编码方式， 与msg中encodeHexStr 对应
            'msg'       => self::encodeHexStr($content) // 编码短信内容
        ];
        $result = '2';//Http::post(self::$uri,$data);
        Log::write($result." >> ".$content,'sms');
        return strpos($result,'-') === true ? false : true;
    }
}
