<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Hsms as Smslib;
use app\common\model\User;
use think\Hook;
use think\Log;

/**
 * 手机短信接口
 * @ApiWeigh   (31)
 */
class Sms extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     * 发送验证码
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="mobile", type="string", description="手机号")
     * @ApiParams   (name="event", type="string", description="事件名称 默认注册：register 修改登录密码： resetpwd 修改支付密码： resetpay")
     */
    public function send()
    {
        if($this->request->isGet()){
            return '';
        }
        $mobile = $this->request->post("mobile");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';
        // Log::write($mobile." >> send".json_encode(input()),'sms');

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号不正确'));
        }
        $last = Smslib::get($mobile, $event);
        if ($last && time() - $last['createtime'] < 60 && $event != 'order') {
            $this->error(__('发送频繁'));
        }
        $wh = [];
        // $wh['event'] = $event;
        $wh['mobile'] = $mobile;
        $ipSendTotal = \app\common\model\Sms::where($wh)->whereTime('createtime', '-1 day')->count();
        if ($ipSendTotal >= 5) {
            $this->error(__('发送频繁'));
        }
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if(!empty($userinfo) && $userinfo->status != 'normal'){
                $this->error('账户已被锁定，无法发送!');
            }
            if(empty($userinfo)){
                $ip = request()->ip();
                $wh = [];
                $wh['ip'] = $ip;
                // $wh['mobile'] = $mobile;
                $num = \app\common\model\Sms::where($wh)->count();
                if($num >= 10){
                    Log::write($mobile." >> 多次请求",'sms');
                    $this->success('发送成功');
                }                
            }
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['resetpay']) ){
                if(!$userinfo){
                    $this->error(__('未注册'));
                }else if($this->auth->mobile != $mobile){
                    $this->error(__('请使用账户绑定的手机号'));
                }                
            } elseif (in_array($event, ['changepwd', 'resetpwd','secret']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        // if (!Hook::get('sms_send')) {
        //     $this->error(__('请在后台插件管理安装短信验证插件'));
        // }
        $ret = Smslib::send($mobile, null, $event);
        if ($ret) {
            $this->success(__('发送成功'));
        } else {
            $this->error(__('发送失败，请稍后重试'));
        }
    }

    /**
     * 检测验证码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     * @param string $captcha 验证码
     */
    public function check()
    {
        $mobile = $this->request->post("mobile");
        $event = $this->request->post("event");
        $event = $event ? $event : 'register';
        $captcha = $this->request->post("captcha");

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号不正确'));
        }
        if ($event) {
            $userinfo = User::getByMobile($mobile);
            if ($event == 'register' && $userinfo) {
                //已被注册
                $this->error(__('已被注册'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Smslib::check($mobile, $captcha, $event);
        if ($ret) {
            $this->success(__('成功'));
        } else {
            $this->error(__('验证码不正确'));
        }
    }
}
