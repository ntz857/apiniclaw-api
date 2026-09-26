<?php

namespace app\api\controller;

use Throwable;
use ba\Captcha;
use ba\ClickCaptcha;
use ba\Random;
use think\facade\Config;
use think\facade\Validate;
use app\common\facade\Token;
use app\common\controller\Frontend;
use app\common\library\Auth as UserAuth;
use app\common\library\Email;
use app\common\model\User as UserModel;
use app\api\validate\User as UserValidate;

class User extends Frontend
{
    protected array $noNeedLogin = ['checkIn', 'logout', 'emailCheckIn'];

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 会员签入(登录和注册)
     * @throws Throwable
     */
    public function checkIn(): void
    {
        $openMemberCenter = Config::get('buildadmin.open_member_center');
        if (!$openMemberCenter) {
            $this->error(__('Member center disabled'));
        }

        // 检查登录态
        if ($this->auth->isLogin()) {
            $this->success(__('You have already logged in. There is no need to log in again~'), [
                'type' => $this->auth::LOGGED_IN
            ], $this->auth::LOGIN_RESPONSE_CODE);
        }

        $userLoginCaptchaSwitch = Config::get('buildadmin.user_login_captcha');

        if ($this->request->isPost()) {
            $params = $this->request->post(['tab', 'email', 'mobile', 'username', 'password', 'keep', 'captcha', 'captchaId', 'captchaInfo', 'registerType']);

            // 提前检查 tab ，然后将以 tab 值作为数据验证场景
            if (!in_array($params['tab'] ?? '', ['login', 'register'])) {
                $this->error(__('Unknown operation'));
            }

            $validate = new UserValidate();
            try {
                $validate->scene($params['tab'])->check($params);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }

            if ($params['tab'] == 'login') {
                if ($userLoginCaptchaSwitch) {
                    $captchaObj = new ClickCaptcha();
                    if (!$captchaObj->check($params['captchaId'], $params['captchaInfo'])) {
                        $this->error(__('Captcha error'));
                    }
                }
                $res = $this->auth->login($params['username'], $params['password'], !empty($params['keep']));
            } elseif ($params['tab'] == 'register') {
                $captchaObj = new Captcha();
                if (!$captchaObj->check($params['captcha'], $params[$params['registerType']] . 'user_register')) {
                    $this->error(__('Please enter the correct verification code'));
                }
                $res = $this->auth->register($params['username'], $params['password'], $params['mobile'], $params['email']);
            }

            if (isset($res) && $res === true) {
                $this->success(__('Login succeeded!'), [
                    'userInfo'  => $this->auth->getUserInfo(),
                    'routePath' => '/user'
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: __('Check in failed, please try again or contact the website administrator~');
                $this->error($msg);
            }
        }

        $this->success('', [
            'userLoginCaptchaSwitch'  => $userLoginCaptchaSwitch,
            'accountVerificationType' => get_account_verification_type()
        ]);
    }

    /**
     * 桌面端邮箱验证码登录。tab=send 发码，tab=login 校验并签发会员令牌。
     * 邮箱不存在时自动注册。未配置发信时，仅在调试模式把验证码放进 data.debug_code。
     * @throws Throwable
     */
    public function emailCheckIn(): void
    {
        if (!Config::get('buildadmin.open_member_center')) {
            $this->error(__('Member center disabled'));
        }
        if (!$this->request->isPost()) {
            $this->error(__('Unknown operation'));
        }

        $tab   = $this->request->post('tab/s', '');
        $email = strtolower(trim($this->request->post('email/s', '')));
        if (!Validate::is($email, 'email')) {
            $this->error(__('email format error'));
        }

        $captchaId = $email . 'user_email_login';
        $captcha   = new Captcha();

        if ($tab === 'send') {
            $existing = $captcha->getCaptchaData($captchaId);
            if ($existing && time() - $existing['create_time'] < 60) {
                $this->error(__('Frequent email sending'));
            }
            $code = $captcha->create($captchaId);
            $mail = new Email();
            $data = [];
            if ($mail->configured) {
                try {
                    $mail->isSMTP();
                    $mail->addAddress($email);
                    $mail->isHTML();
                    $mail->setSubject(__('user_email_verify') . '-' . get_sys_config('site_name'));
                    $mail->Body = __('Your verification code is: %s', [$code]);
                    $mail->send();
                } catch (Throwable) {
                    $this->error($mail->ErrorInfo ?: __('Mail sending service unavailable'));
                }
            } elseif (env('app_debug')) {
                $data['debug_code'] = $code;
            } else {
                $this->error(__('Mail sending service unavailable'));
            }
            $this->success(__('Mail sent successfully~'), $data);
        }

        if ($tab !== 'login') {
            $this->error(__('Unknown operation'));
        }

        $code = trim($this->request->post('captcha/s', ''));
        if (!$captcha->check($code, $captchaId)) {
            $this->error(__('Please enter the correct verification code'));
        }

        $user = UserModel::where('email', $email)->find();
        if ($user) {
            if ($user->status == 'disable') {
                $this->error(__('Account disabled'));
            }
            $ok = $this->auth->direct((int)$user->id);
        } else {
            $username = 'u' . substr(md5($email . Random::uuid()), 0, 10);
            $ok       = $this->auth->register($username, Random::build('alnum', 16), '', $email);
        }
        if (!$ok) {
            $this->error($this->auth->getError() ?: __('Check in failed, please try again or contact the website administrator~'));
        }

        $this->success(__('Login succeeded!'), [
            'userInfo' => $this->auth->getUserInfo(),
        ]);
    }

    public function logout(): void
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refreshToken/s', '');
            if ($refreshToken) {
                $tokenData = Token::get($refreshToken);
                if ($tokenData && $tokenData['user_id'] == $this->auth->id && $tokenData['type'] == UserAuth::TOKEN_TYPE . '-refresh') {
                    Token::delete($refreshToken);
                }
            }
            $this->auth->logout();
            $this->success();
        }
    }
}
