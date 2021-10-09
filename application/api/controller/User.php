<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Isms as Sms;
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
     * @ApiReturnParams   (name="valid_number", type="integer", description="有效值")
     */
    public function index()
    {
        $result = Db::name("user")->field("avatar,nickname,serial_number,mobile,valid_number,level,score")->where("id",$this->auth->id)->find();
        $this->success('', $result);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @param string $account  账号
     * @param string $password 密码
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
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
        $invite_code = $this->request->post('invite_code');
        $email = $this->request->post('email');
        $code = $this->request->post('code');
        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $wh = [];
        $wh['status']           = 'normal';
        $wh['serial_number']    = $invite_code;
        $result = Db::name("user")->where($wh)->find();
        if(empty($result) || empty($invite_code)){
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
        $ret = $this->auth->register($username, $password, $email, $mobile, ['invite_code'=>$invite_code]);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
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
     * @ApiInternal
     *
     * @ApiMethod (POST)
     * @param string $avatar   头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
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
        $mobile = $this->request->post("mobile");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }

        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if (!$user) {
            $this->error(__('User not found'));
        }
        $ret = Sms::check($mobile, $captcha, 'resetpay');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        Sms::flush($mobile, 'resetpay');
        
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
     * @ApiMethod (POST)
     * @ApiReturnParams   (name="page", type="int", description="页码")
     * @ApiReturnParams   (name="per_page", type="int", description="数量")
     */
    public function getChildInfo()
    {
        $list = Db::name("user")->field("serial_number,level")
                ->where("pid",$this->auth->id)
                ->paginate($per_page??10);
        $this->success('',$list);
    }

    /**
     * 获取认证信息
     *
     * @ApiMethod (POST)
     * @ApiReturnParams   (name="name", type="string", description="姓名")
     * @ApiReturnParams   (name="id_card", type="string", description="身份证号")
     * @ApiReturnParams   (name="front_img", type="string", description="正面照")
     * @ApiReturnParams   (name="reverse_img", type="string", description="反面照")
     * @ApiReturnParams   (name="hand_img", type="string", description="手持照")
     * @ApiReturnParams   (name="hands_img", type="string", description="手持宣传语照")
     * @ApiReturnParams   (name="remark", type="string", description="审核备注")
     */
    public function getAttestationInfo()
    {
        $result = Db::name("egg_attestation")->field(['id','user_id'],true)->where("user_id",$this->auth->id)->find();
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
     * @ApiParams   (name="hand_img", type="string",required=true, description="手持照")
     * @ApiParams   (name="hands_img", type="string",required=true, description="手持宣传语照")
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
        if (!$name || !$id_card || !$front_img || !$reverse_img || !$hand_img || !$hands_img) {
            $this->error(__('Invalid parameters'));
        }
        $params = [];
        $params['name']         = $name;
        $params['id_card']      = $id_card;
        $params['front_img']    = $front_img;
        $params['reverse_img']  = $reverse_img;
        $params['hand_img']     = $hand_img;
        $params['hands_img']    = $hands_img;
        $result = Db::name("egg_attestation")->where("user_id",$this->auth->id)->find();
        if(empty($result)){
            $params['user_id']    = $this->auth->id;
            $result = Db::name("egg_attestation")->insert($params);
            if($result){                
                $this->success("添加成功");
            }
        }else{
            $result = Db::name("egg_attestation")->where("user_id",$this->auth->id)->update($params);
            if($result){                
                $this->success("更新成功");
            }
        }
        $this->error("执行失败");
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
        $month          = $this->request->get("month","");
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

        $table = empty($month)?"egg_log_".date("Y_m"):"egg_log_".date("Y_m",strtotime($date));
        $list = Db::name($table)->alias("l")
                ->field("l.type,k.name,l.number,l.note,l.createtime")
                ->join("egg_kind k","k.id=l.kind_id","LEFT")
                ->where("l.user_id",$this->auth->id)
                ->order("l.createtime","DESC")
                ->paginate($per_page)->each(function($item) use($type_arr){
                    $item['type'] = $type_arr[$item['type']];
                    $item['createtime'] = date("Y-m-d H:i",$item['createtime']);
                    return $item;
                });
        $this->success('',$list);
    }

}
