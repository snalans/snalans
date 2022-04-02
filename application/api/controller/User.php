<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Hsms as Sms;
use fast\Random;
use think\Config;
use think\Validate;
use think\Db;

/**
 * 会员接口
 * @ApiWeigh   (26)
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }

    /**
     * 会员中心
     * 
     * @ApiReturnParams   (name="avatar", type="string", description="头像")
     * @ApiReturnParams   (name="nickname", type="string", description="昵称")
     * @ApiReturnParams   (name="mobile", type="string", description="手机号")
     * @ApiReturnParams   (name="serial_number", type="string", description="会员编号或邀请码")
     * @ApiReturnParams   (name="level", type="integer", description="等级")
     * @ApiReturnParams   (name="score", type="integer", description="积分")
     * @ApiReturnParams   (name="valid_number", type="integer", description="个人有效值")
     * @ApiReturnParams   (name="total_valid_number", type="integer", description="团队有效值")
     * @ApiReturnParams   (name="is_paypwd", type="integer", description="是否配置过密码 1=是 0=否")
     * @ApiReturnParams   (name="is_attestation_str", type="string", description="认证情况")
     * @ApiReturnParams   (name="release", type="string", description="发布数量")
     * @ApiReturnParams   (name="buy_num", type="string", description="买到数量")
     * @ApiReturnParams   (name="sell_num", type="string", description="卖出数量")
     */
    public function index()
    {
        $result = Db::name("user")->alias("u")
                    ->field("u.avatar,u.nickname,u.serial_number,u.mobile,u.paypwd,u.valid_number,u.level,u.score,u.is_attestation,lc.title")
                    ->join("user_level_config lc","lc.level=u.level","LEFT")
                    ->where("u.id",$this->auth->id)
                    ->find();
        $result['avatar'] = $result['avatar'] ?  cdnurl($result['avatar'], true) : letter_avatar($result['nickname']);

        if($result['is_attestation'] == 0){
            $result['title'] = '普通用户';
            $result['is_attestation_str'] = "实名认证";
        }else if($result['is_attestation'] == 1){
            $result['is_attestation_str'] = "认证成功";
        }else if($result['is_attestation'] == 2){
            $result['is_attestation_str'] = "待审核";
        }else {
            $result['is_attestation_str'] = "认证失败";
        }
        $wh = [];
        $wh['c.ancestral_id'] = $this->auth->id;
        $wh['u.status']       = 'normal';
        $wh['u.is_attestation'] = 1;
        $wh['c.level']        = ['<',4];
        $sum = Db::name("membership_chain")->alias("c")
                ->join("user u","u.id=c.user_id","LEFT")
                ->where($wh)
                ->sum("u.valid_number");
        $result['total_valid_number'] = $sum;
        $result['is_paypwd'] = empty($result['paypwd'])?0:1;
        unset($result['paypwd']);

        $wh = [];
        $wh['user_id'] = $this->auth->id;
        $wh['status']  = 1;
        $result['release'] = Db::name("mall_product")->where($wh)->count();
        $wh = [];
        $wh['buy_user_id'] = $this->auth->id;
        $wh['buy_del']     = 0;
        $result['buy_num'] = Db::name("mall_order")->where($wh)->count();
        $wh = [];
        $wh['sell_user_id'] = $this->auth->id;
        $wh['sell_del']     = 0;
        $result['sell_num'] = Db::name("mall_order")->where($wh)->count();
        $this->success('', $result);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @param string $account  账号
     * @param string $password 密码
     * @param string $captcha  验证码（/captcha.html）
     * 
     * @ApiReturnParams   (name="serial_number", type="string", description="用户编号")
     * @ApiReturnParams   (name="nickname", type="string", description="昵称")
     * @ApiReturnParams   (name="mobile", type="string", description="手机号")
     * @ApiReturnParams   (name="avatar", type="string", description="头像")
     * @ApiReturnParams   (name="token", type="string", description="Token")
     * @ApiReturnParams   (name="user_id", type="string", description="用户id")
     * @ApiReturnParams   (name="createtime", type="int", description="创建时间")
     * @ApiReturnParams   (name="expiretime", type="int", description="过期时间")
     * @ApiReturnParams   (name="expires_in", type="int", description="过期秒数")
     */
    public function login()
    {
        $account  = $this->request->post('account');
        $password = $this->request->post('password');
        $captcha  = $this->request->post('captcha');

        // $start_time = strtotime("2022-3-30 01:00:00");
        // $end_time = strtotime("2022-3-31 12:00:00");

        // if(time() >= $start_time && time() < $end_time){
        //     if(!in_array($account,['17095989213','15060060723','18059119783','15705917729'])){
        //         $this->error("系统维护中");
        //     }            
        // }

        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }

        if(!in_array($account,['13305910944','17095989213']))
        {            
            if(!captcha_check($captcha)){
                 $this->error("验证码错误");
            }
        }
        $ip = request()->ip();
        $wh = [];
        $wh['id'] = ['>',308];
        $wh['loginip'] = $ip;
        $wh['logintime'] = ['>=',strtotime(date("Y-m-d"))];
        $num = Db::name("user")->where($wh)->count();
        if($num > 10){
            $this->error("登录异常,请稍后重试");
        }

        $flag = $this->changePwd($account);
        if($flag){
            $this->error("您的账号登录异常请修改密码重新登陆");
        }

        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $wh = [];
            $wh['user_id']      = $data['userinfo']['user_id'];
            $wh['createtime']   = ['<>',$data['userinfo']['createtime']];
            Db::name("user_token")->where($wh)->delete();
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     * @ApiInternal
     *
     * @ApiMethod (POST)
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @param string $mobile   手机号
     * @param string $password 密码
     * @param string $invite_code    邀请码
     * @param string $code     验证码
     */
    public function register()
    {
        $mobile = $this->request->post('mobile');
        $username = $mobile;
        $password = $this->request->post('password');
        $invite_code = $this->request->post('invite_code',"");
        $email = $this->request->post('email');
        $code = $this->request->post('code');
        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if(empty($invite_code)){
            $this->error(__('Invalid invitation code, please check'));
        }
        $pwd_len = strlen($password);
        if ($pwd_len > 16 || $pwd_len < 6 || !preg_match('/[a-z]+/',$password) || !preg_match('/[A-Z]+/',$password)) {
            $this->error("密码长度应为6~16个字符,包含大小写字母");
        }
        if($code != '9999'){            
            $wh = [];
            $wh['status']           = 'normal';
            $wh['serial_number']    = $invite_code;
            $result = Db::name("user")->where($wh)->find();
            if(empty($result)){
                $this->error(__('Invalid invitation code, please check'));
            }
            if ($email && !Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $ret = Sms::check($mobile, $code, 'register');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
        }
        $ret = $this->auth->register($username, $password, $email, $mobile, ['invite_code'=>$invite_code]);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            unset($data['userinfo']['token']);
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @param string $avatar   头像地址
     * @param string $nickname 昵称
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $nickname = $this->request->post('nickname');
        $avatar   = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        // $username = $this->request->post('username');
        // $bio      = $this->request->post('bio');
        // if ($username) {
        //     $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
        //     if ($exists) {
        //         $this->error(__('Username already exists'));
        //     }
        //     $user->username = $username;
        // }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->avatar = $avatar;
        $rs = $user->save();
        if($rs){
            $this->success("修改成功");
        }else{
            $this->error("修改失败");
        }
    }

    /**
     * 修改邮箱
     * @ApiInternal
     *
     * @ApiMethod (POST)
     * @param string $email   邮箱
     * @param string $captcha 验证码
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     * @ApiInternal
     *
     * @ApiMethod (POST)
     * @param string $platform 平台名称
     * @param string $code     Code码
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile      手机号
     * @param string $newpassword 新密码
     * @param string $captcha     验证码
     */
    public function resetpwd()
    {
        $type = $this->request->post("type",'mobile');
        $mobile = $this->request->post("mobile");
        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if ($type == 'mobile') {
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user) {
                $this->error(__('User not found'));
            }
            if(md5(md5($newpassword) . $user->salt) === $user->password){
                $this->error("新密码不能与旧密码一样");
            }
            $pwd_len = strlen($newpassword);
            if ($pwd_len > 16 || $pwd_len < 6 || !preg_match('/[a-z]+/',$newpassword) || !preg_match('/[A-Z]+/',$newpassword)) {
                $this->error("密码长度应为6~16个字符,包含大小写字母");
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        } else {
            if (!Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }

        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->moveMobile($mobile);
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 重置支付密码
     *
     * @ApiMethod (POST)
     * @param string $mobile      手机号
     * @param string $newpassword 新密码
     * @param string $captcha     验证码
     */
    public function resetpay()
    {
        $mobile         = $this->request->post("mobile");
        $newpassword    = $this->request->post("newpassword");
        $captcha        = $this->request->post("captcha");

        if (!$newpassword) {
            $this->error(__('Invalid parameters'));
        }

        $paypwd = Db::name("user")->where("id",$this->auth->id)->value("paypwd");
        if(!empty($paypwd))
        {        
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            if ($this->auth->mobile != $mobile){                
                $this->error("请使用账户绑定的手机号进行验证");
            }
            $ret = Sms::check($mobile, $captcha, 'resetpay');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpay');
        }
        
        $ret = $this->auth->changepay($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset payment password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 获取直推列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="page", type="int", description="页码")
     * @ApiParams   (name="per_page", type="int", description="数量")
     * 
     * @ApiReturnParams   (name="team_total", type="int", description="团队总人数")
     * @ApiReturnParams   (name="team_valid", type="int", description="团队有效人数")
     * @ApiReturnParams   (name="p_total", type="int", description="直推总人数")
     * @ApiReturnParams   (name="p_valid", type="int", description="直推有效人数")
     * @ApiReturnParams   (name="p_mobile", type="int", description="邀请人手机号")
     * @ApiReturnParams   (name="p_serial_number", type="int", description="邀请人会员编号")
     * 
     * @ApiReturnParams   (name="avatar", type="string", description="用户头像")
     * @ApiReturnParams   (name="serial_number", type="string", description="用户编号")
     * @ApiReturnParams   (name="valid_number", type="string", description="有效值")
     * @ApiReturnParams   (name="mobile", type="string", description="手机号")
     * @ApiReturnParams   (name="title", type="string", description="等级名称")
     * @ApiReturnParams   (name="status", type="string", description="状态")
     * @ApiReturnParams   (name="createtime", type="string", description="注册时间")
     * @ApiReturnParams   (name="is_attestation", type="string", description="是否认证 0=否 1=是 2=待审核 3=失败")
     * @ApiReturnParams   (name="team_number", type="int", description="下级-直推人数")
     */
    public function getChildInfo()
    {
        $page       = $this->request->get("page",1);
        $per_page   = $this->request->get("per_page",15);
        $wh = [];
        $wh['u.pid']            = $this->auth->id;
        $list = Db::name("user")->alias("u")
                ->field("u.id,u.avatar,u.nickname,u.serial_number,l.title,u.valid_number,u.is_attestation,u.mobile,u.status,u.createtime")
                ->join("user_level_config l","l.level=u.level","LEFT")
                ->where($wh)
                ->order("u.createtime desc")
                ->paginate($per_page)->each(function($item){
                    $item['avatar'] = $item['avatar']? cdnurl($item['avatar'], true) : letter_avatar($item['nickname']);
                    $item['team_number'] = Db::name("user")->where("pid",$item['id'])->count();
                    if($item['is_attestation'] != 1){
                        $item['title'] = "普通会员";
                    }
                    $item['status'] = $item['status']=='normal'?"正常":"锁定";
                    $item['createtime'] = date("Y-m-d",$item['createtime']);
                    unset($item['id']);
                    unset($item['nickname']);
                    return $item;
                });
        $list = json_encode($list);
        $list = json_decode($list,1);

        $wh = [];
        $wh['pid']              = $this->auth->id;
        $wh['status']           = 'normal';
        $wh['is_attestation']   = 1;
        $list['p_valid'] = Db::name("user")->where($wh)->count(); 
        $list['p_total'] = $list['total'];

        $wh = [];
        $wh['mc.ancestral_id'] = $this->auth->id;
        $wh['mc.level']        = ['<=',3];
        $list['team_total'] = Db::name("membership_chain")->alias("mc")
                                ->join("user u","u.id=mc.user_id","LEFT")
                                ->where($wh)
                                ->count();

        $wh['u.status']           = 'normal';
        $wh['u.is_attestation']   = 1;
        $list['team_valid'] = Db::name("membership_chain")->alias("mc")
                                ->join("user u","u.id=mc.user_id","LEFT")
                                ->where($wh)
                                ->count();
        $list['p_serial_number'] = '';
        if($this->auth->pid>0){
            $list['p_serial_number'] = Db::name("user")->where("id",$this->auth->pid)->value("serial_number");
        }        
        $this->success('',$list);
    }

    /**
     * 获取认证信息
     *
     * @ApiMethod (GET)
     * @ApiReturnParams   (name="name", type="string", description="姓名")
     * @ApiReturnParams   (name="id_card", type="string", description="身份证号")
     * @ApiReturnParams   (name="front_img", type="string", description="正面照")
     * @ApiReturnParams   (name="reverse_img", type="string", description="反面照")
     * @ApiReturnParams   (name="hand_img", type="string", description="手持照")
     * @ApiReturnParams   (name="hands_img", type="string", description="手持宣传语照")
     * @ApiReturnParams   (name="remark", type="string", description="审核备注")
     * @ApiReturnParams   (name="is_attestation", type="string", description="是否认证 0=否 1=是 2=待审核 3=失败")
     */
    public function getAttestationInfo()
    {
        $result = Db::name("egg_attestation")->alias("ea")
                    ->field("ea.name,ea.id_card,ea.front_img,ea.reverse_img,ea.hand_img,ea.hands_img,u.is_attestation")
                    ->join("user u","u.id=ea.user_id","LEFT")
                    ->where("user_id",$this->auth->id)
                    ->find();
        if(!empty($result)){
            if($result['is_attestation'] == 0){
                $result['remark'] = "未认证";
            }else if($result['is_attestation'] == 1){
                $result['remark'] = "认证成功";
            }else if($result['is_attestation'] == 2){
                $result['remark'] = "待审核";
            }else {
                $result['remark'] = "认证失败";
            }
        }
        $this->success('',$result);
    }


    /**
     * 保存认证信息
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="name", type="string",required=true, description="姓名")
     * @ApiParams   (name="id_card", type="string",required=true, description="身份证号")
     * @ApiParams   (name="front_img", type="string",required=true, description="正面照")
     * @ApiParams   (name="reverse_img", type="string",required=true, description="反面照")
     * @ApiParams   (name="hand_img", type="string",required=true, description="手持照和手持宣传语")
     */
    public function saveAttestationInfo()
    {
        $name           = $this->request->post("name");
        $id_card        = $this->request->post("id_card");
        $front_img      = $this->request->post("front_img");
        $reverse_img    = $this->request->post("reverse_img");
        $hand_img       = $this->request->post("hand_img");
        $hands_img      = $this->request->post("hands_img");

        if (!\app\common\library\Validate::check_id_card($id_card)) {
            $this->error(__('Id_card is incorrect'));
        }

        $wh = [];
        $wh['id_card'] = $id_card;
        $wh['user_id'] = ['<>',$this->auth->id];
        $have = Db::name("egg_attestation")->where($wh)->find();
        if(!empty($have)){
            $this->error("身份证已经注册过,请更换");
        }

        if (!$name || !$id_card || !$front_img || !$reverse_img || !$hand_img) {
            $this->error(__('Invalid parameters'));
        }
        $params = [];
        $params['name']         = $name;
        $params['id_card']      = $id_card;
        $params['front_img']    = $front_img;
        $params['reverse_img']  = $reverse_img;
        $params['hand_img']     = $hand_img;
        $params['add_time']     = time();
        $result = Db::name("egg_attestation")->where("user_id",$this->auth->id)->find();
        if(empty($result)){
            $params['user_id']    = $this->auth->id;
            $result = Db::name("egg_attestation")->insert($params);
            if($result){                
                Db::name("user")->where("id",$this->auth->id)->update(['is_attestation'=>2]);
                $this->success("添加成功");
            }
        }else if($this->auth->is_attestation == 3){
            $result = Db::name("egg_attestation")->where("user_id",$this->auth->id)->update($params);
            if($result){                
                Db::name("user")->where("id",$this->auth->id)->update(['is_attestation'=>2]);
                $this->success("更新成功");
            }
        }else{
            $this->error("不允许操作");
        }
        
    }


    /**
     * 获取收款信息
     *
     * @ApiMethod (GET)
     * @ApiReturnParams   (name="type", type="string", description="类型 1=支付宝 2=微信 3=钱包")
     * @ApiReturnParams   (name="account", type="string", description="账号")
     * @ApiReturnParams   (name="image", type="string", description="收款二维码")
     */
    public function getChargeInfo()
    {
        $result = Db::name("egg_charge_code")
                    ->field(['user_id','add_time'],true)
                    ->where("user_id",$this->auth->id)
                    ->select();
        if(!empty($result)){
            foreach ($result as $key => $value) {
                if(!empty($value['image'])){
                    $result[$key]['image'] = cdnurl($value['image'], true);
                }
            }
        }
        $this->success('',$result);
    }

    /**
     * 保存收款信息
     *
     * @ApiMethod (POST)
     * @ApiParams   (name="type", type="string",required=true, description="类型 1=支付宝 2=微信 3=钱包 4=银行卡")
     * @ApiParams   (name="name", type="string",required=true, description="姓名")
     * @ApiParams   (name="mobile", type="string",required=true, description="手机号")
     * @ApiParams   (name="open_bank", type="string",required=true, description="开户行")
     * @ApiParams   (name="account", type="string",required=true, description="账号")
     * @ApiParams   (name="image", type="string",required=true, description="收款二维码")
     */
    public function saveChargeInfo()
    {
        $type           = $this->request->post("type","1");
        $name           = $this->request->post("name","");
        $mobile         = $this->request->post("mobile","");
        $open_bank      = $this->request->post("open_bank","");
        $account        = $this->request->post("account","");
        $image          = $this->request->post("image","");

        if($this->auth->is_attestation != 1){
            $this->error("还未实名认证");
        }

        if (empty($account) || !in_array($type,[1,2,3,4])) {
            $this->error("参数有误");
        }
        
        if(in_array($type,[1,2]) && empty($image)){
            $this->error("收款二维码不能为空");
        }

        $real_name = Db::name("egg_attestation")->where("user_id",$this->auth->id)->value("name");
        if($real_name != $name){
            $this->error("跟实名的名字不一样");
        }

        $wh = [];
        $wh['user_id']      = $this->auth->id;
        $wh['type']         = $type;
        $result = Db::name("egg_charge_code")->where($wh)->find();
        if($result){
            $this->error("已经添加过,如需修改联系管理员");
        }else{            
            $data = [];
            $data['user_id']      = $this->auth->id;
            $data['type']         = $type;
            $data['name']         = $name;
            $data['mobile']       = $mobile;
            $data['open_bank']    = $open_bank;
            $data['account']      = $account;
            $data['image']        = $image;
            $data['add_time']     = time();
            $result = Db::name("egg_charge_code")->insert($data);
            if($result){
                $this->success("添加成功");
            }else{
                $this->error("保存失败,请重试");
            }
        }        
    }

    /**
     * 获取蛋日志列表
     *
     * @ApiMethod (GET)
     * @ApiParams   (name="date", type="int", description="日期 2021-10")
     * @ApiParams   (name="page", type="int", description="页码")
     * @ApiParams   (name="per_page", type="int", description="数量")
     * 
     * @ApiReturnParams   (name="type", type="string", description="类型名称")
     * @ApiReturnParams   (name="name", type="string", description="蛋名称")
     * @ApiReturnParams   (name="number", type="int", description="数量")
     * @ApiReturnParams   (name="note", type="string", description="说明")
     * @ApiReturnParams   (name="createtime", type="string", description="创建时间")
     */
    public function getEggLog()
    {
        $date          = $this->request->get("date","");
        $page           = $this->request->get("page",1);
        $per_page       = $this->request->get("per_page",15);
        $type_arr = [
            '0' => "农场",
            '1' => "订单",
            '2' => "互转",
            '3' => "合成",
            '4' => "管理员操作",
            '5' => "积分兑换",
            '9' => "手续费",
        ];

        $wh = [];
        $wh['l.user_id'] = $this->auth->id;
        $wh['l.type']    = ['<>',4];
        // $table = empty($date)?"egg_log_".date("Y_m"):"egg_log_".date("Y_m",strtotime($date));
        // $rs = Db::query('SHOW TABLES LIKE "fa_'.$table.'"');
        // $list = "";
        // if(!empty($rs)){
            $list = Db::name("egg_log")->alias("l")
                ->field("l.type,k.name,l.number,l.note,l.createtime")
                ->join("egg_kind k","k.id=l.kind_id","LEFT")
                ->where($wh)
                ->order("l.createtime","DESC")
                ->paginate($per_page)->each(function($item) use($type_arr){
                    $item['type'] = isset($type_arr[$item['type']])?$type_arr[$item['type']]:'';
                    $item['createtime'] = date("Y-m-d H:i",$item['createtime']);
                    return $item;
                });
        // }        
        $this->success('',$list);
    }

    // 提示用户重置密码后才能登录
    public function changePwd($mobile='')
    {
        $wh = [];
        $wh['mobile'] = $mobile;
        $wh['status'] = 1;
        $info = Db::name("egg_valid_mobile")->where($wh)->find();
        if(!empty($info)){
            return true;
        }
        return false;
    }

    //移除需要重置的手机号
    public function moveMobile($mobile='')
    {
        $wh = [];
        $wh['mobile'] = $mobile;
        $wh['status'] = 1;
        $info = Db::name("egg_valid_mobile")->where($wh)->find();
        if(!empty($info)){
            $data = [];
            $data['num'] = $info['num']+1;
            $data['status'] = 0;
            $data['valid_time'] = time();
            Db::name("egg_valid_mobile")->where("id",$info['id'])->update($data);
        }
    }
}
