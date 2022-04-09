<?php
namespace app\common\library;

use think\Config;
use think\Hook;
use think\Log;
use fast\Http;

/**
 * 短信验证码类
 */
class Hsms
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
    protected static $uri       = "https://dx.ipyy.net/sms.aspx";
    protected static $account   = "OT00492";    //改为实际账户名
    protected static $password  = "3tmba8w7";   //改为实际短信发送密码
    protected static $extno     = "";


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
        $code = is_null($code) ? mt_rand(100000, 999999) : $code;
        $time = time();
        $ip = request()->ip();
        $sms = \app\common\model\Sms::create(['event' => $event, 'mobile' => $mobile, 'code' => $code, 'ip' => $ip, 'createtime' => $time]);
        $log_msg = '【阿尼农场】';
        if($event=='resetpwd'){
            $log_msg .= '您申请重置登录密码，验证码：'.$code.'。请不要把验证码泄漏给其他人，如非本人请勿操作。 ';
        }else if($event=='resetpay'){                
            $log_msg .= '您申请重置支付密码，验证码：'.$code.'。请不要把验证码泄漏给其他人，如非本人请勿操作。 ';
        }else if($event=='login'){                
            $log_msg .= '您申请登录，验证码：'.$code.'。请不要把验证码泄漏给其他人，如非本人请勿操作。 ';
        }else if($event=='register'){                
            $log_msg .= '您申请注册会员，验证码：'.$code.'。请不要把验证码泄漏给其他人，如非本人请勿操作。 ';
        }else if($event=='secret'){                
            $log_msg .= '您申请绑定谷歌验证,验证码：'.$code.'。请不要把验证码泄漏给其他人,如非本人请勿操作。 ';
        }else if($event=='virtual'){                
            $log_msg .= '你申请的（话费/油卡）充值审核通过，以为你自动充值';
        }else if($event=='order'){
            $log_msg .= '您的订单已发生变化，请登录查看。';
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
        $log_msg = '【我的农场】'.$msg;
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
     * 发送手机短信
     * @param unknown $mobile 手机号
     * @param unknown $content 短信内容
     */
    public static function sendSms($mobile,$content) {
        // 参数数组
        $data = [
            'action'    => 'send',
            'userid'    => '',
            'account'   => self::$account,
            'password'  => self::$password,
            'mobile'    => $mobile,
            'extno'     => self::$extno,
            'content'   => $content,
            'sendtime'  => date("Y-m-d H:i:s"),      
        ];
        $flag = strpos($mobile, '1498888');
        if(Config::get('site.is_sms') && $flag === false){
            $result     = Http::post(self::$uri,$data);
            $postObj    = simplexml_load_string($result);
            $jsonStr    = json_encode($postObj,JSON_UNESCAPED_UNICODE);
            $jsonArray  = json_decode($jsonStr,true);
        }else{
            $jsonStr = '';
            $jsonArray['returnstatus'] = 'Success';
        }
        Log::write($mobile." >> ".$jsonStr,'sms');
        return $jsonArray['returnstatus'] == 'Success' ? true:false;
    }
}
