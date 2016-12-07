<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/10/1
 */
namespace app\login\controller;

use app\model\Users;
use app\model\Options;
use think\Controller;
use think\Request;
use think\Validate;
use think\Session;
use think\Cookie;
use think\Cache;
use think\Db;
use think\Url;
use think\Lang;

class Index extends Controller
{
    protected $session_prefix;
    public function _initialize()
    {
        $this->session_prefix = 'catfish'.str_replace('/','',Url::build('/'));
        Lang::detect();
    }
    public function index()
    {
        //验证登录
        if(Request::instance()->has('user','post'))
        {
            //验证输入内容
            if(Request::instance()->has('captcha','post'))
            {
                $rule = [
                    'user' => 'require',
                    'pwd' => 'require',
                    'captcha|'.Lang::get('Captcha')=>'require|captcha'
                ];
            }
            else
            {
                $rule = [
                    'user' => 'require',
                    'pwd' => 'require'
                ];
            }
            $msg = [
                'user.require' => Lang::get('The user name must be filled in'),
                'pwd.require' => Lang::get('Password must be filled in')
            ];
            if(Request::instance()->has('captcha','post'))
            {
                $data = [
                    'user' => Request::instance()->post('user'),
                    'pwd' => Request::instance()->post('pwd'),
                    'captcha' => Request::instance()->post('captcha')
                ];
            }
            else
            {
                $data = [
                    'user' => Request::instance()->post('user'),
                    'pwd' => Request::instance()->post('pwd')
                ];
            }
            $validate = new Validate($rule, $msg);
            if(!$validate->check($data))
            {
                $this->error($validate->getError());//验证错误输出
                return false;
            }
            //判断正确
            $users = new Users();
            $user = $users->where('user_login', Request::instance()->post('user'))
                ->find();
            if(empty($user))
            {
                $this->error(Lang::get('Username error'));//错误输出
                return false;
            }
            if($user['user_pass'] != md5(Request::instance()->post('pwd')))
            {
                $this->error(Lang::get('Password error'));//错误输出
                return false;
            }
            if($user['user_status'] == 0)
            {
                $this->error(Lang::get('Account has been disabled, please contact the administrator'));//错误输出
                return false;
            }
            //登录成功
            $user->save([
                'last_login_ip' => get_client_ip(0,true),
                'last_login_time' => date("Y-m-d H:i:s")
            ],['id' => $user['id']]);
            Session::set($this->session_prefix.'user_id',$user['id']);
            Session::set($this->session_prefix.'user',$user['user_login']);
            Session::set($this->session_prefix.'user_type',$user['user_type']);
            if(Request::instance()->post('remember'))
            {
                Cookie::set($this->session_prefix.'user_id',$user['id'],604800);
                Cookie::set($this->session_prefix.'user',$user['user_login'],604800);
                Cookie::set($this->session_prefix.'user_type',$user['user_type'],604800);
            }
        }
        //显示登录页
        if(!Session::has($this->session_prefix.'user_id'))
        {
            $data = Db::name('options')->where('option_name','captcha')->field('option_value')->find();
            $this->assign('yanzheng', $data['option_value']);
            $this->domain();
            $view = $this->fetch();
            return $view;
        }
        elseif(Session::get($this->session_prefix.'user_type') < 7)
        {
            $this->redirect(Url::build('/admin'));
        }
        else
        {
            $this->redirect(Url::build('/user'));
        }
    }
    //ajax登录
    public function denglu()
    {
        if(Request::instance()->post('user') == '')
        {
            return Lang::get('The user name must be filled in');
        }
        if(Request::instance()->post('pwd') == '')
        {
            return Lang::get('Password must be filled in');
        }
        //判断正确
        $users = new Users();
        $user = $users->where('user_login', Request::instance()->post('user'))
            ->find();
        if(empty($user))
        {
            return Lang::get('Username error');
        }
        if($user['user_pass'] != md5(Request::instance()->post('pwd')))
        {
            return Lang::get('Password error');
        }
        if($user['user_status'] == 0)
        {
            return Lang::get('Account has been disabled, please contact the administrator');
        }
        //登录成功
        $user->save([
            'last_login_ip' => get_client_ip(0,true),
            'last_login_time' => date("Y-m-d H:i:s")
        ],['id' => $user['id']]);
        Session::set($this->session_prefix.'user_id',$user['id']);
        Session::set($this->session_prefix.'user',$user['user_login']);
        Session::set($this->session_prefix.'user_type',$user['user_type']);
        return 'ok';
    }
    //注册
    public function register()
    {
        if(Request::instance()->has('user','post'))
        {
            //验证输入内容
            $rule = [
                'user' => 'require',
                'pwd' => 'require',
                'repeat' => 'require',
                'email' => 'require|email'
            ];
            $msg = [
                'user.require' => Lang::get('The user name must be filled in'),
                'pwd.require' => Lang::get('Password must be filled in'),
                'repeat.require' => Lang::get('Confirm password is required'),
                'email.require' => Lang::get('E-mail address is required'),
                'email.email' => Lang::get('The e-mail format is incorrect')
            ];
            $data = [
                'user' => Request::instance()->post('user'),
                'pwd' => Request::instance()->post('pwd'),
                'repeat' => Request::instance()->post('repeat'),
                'email' => Request::instance()->post('email')
            ];
            $validate = new Validate($rule, $msg);
            if(!$validate->check($data))
            {
                $this->error($validate->getError());//验证错误输出
                return false;
            }
            if(Request::instance()->post('pwd') != Request::instance()->post('repeat'))
            {
                $this->error(Lang::get('Confirm the password must be the same as the password'));//验证错误输出
                return false;
            }
            //过滤用户名
            $guolv = Options::get(['option_name' => 'filter']);
            if(strpos($guolv->option_value,Request::instance()->post('user')) !== false)
            {
                $this->error(Lang::get('Please use a different username'));//验证错误输出
                return false;
            }
            $users = new Users;
            $user = $users->where('user_login', Request::instance()->post('user'))
                ->find();
            if(!empty($user))
            {
                $this->error(Lang::get('User name has been registered'));//验证错误输出
                return false;
            }
            $users->data([
                'user_login' => Request::instance()->post('user'),
                'user_pass' => md5(Request::instance()->post('pwd')),
                'user_nicename' => Request::instance()->post('user'),
                'user_email' => Request::instance()->post('email'),
                'last_login_ip' => get_client_ip(0,true),
                'create_time' => date("Y-m-d H:i:s"),
                'user_type' => 7
            ]);
            $users->save();
            $this->success(Lang::get('User registration is successful'), Url::build('/login'));
        }
        $this->domain();
        $view = $this->fetch();
        return $view;
    }
    private function domain()
    {
        $domain = Cache::get('domain');
        if($domain == false)
        {
            $domain = Options::get(['option_name' => 'domain'])->option_value;
            Cache::set('domain',$domain,3600);
        }
        $this->assign('domain', $domain);
    }
}