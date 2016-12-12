<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/10/2
 */
namespace app\admin\controller;

use think\Controller;
use think\Session;
use think\Cookie;
use think\Debug;
use think\Url;
use think\Cache;
use think\Db;
use think\Lang;

class Common extends Controller
{
    protected $plugins = [];
    protected $session_prefix;
    public function _initialize()
    {
        $this->session_prefix = 'catfish'.str_replace('/','',Url::build('/'));
        $pluginslist = Cache::get('pluginslist');
        if($pluginslist == false)
        {
            $pluginslist = [];
            $plugins = Db::name('options')->where('option_name','plugins')->field('option_value')->find();
            if(!empty($plugins))
            {
                $plugins = unserialize($plugins['option_value']);
                foreach($plugins as $key => $val)
                {
                    $pluginFile = APP_PATH.'plugins/'.$val.'/'.ucfirst($val).'.php';
                    if(!is_file($pluginFile))
                    {
                        unset($plugins[$key]);
                        continue;
                    }
                    $this->plugins[] = 'app\\plugins\\'.$val.'\\'.ucfirst($val);
                    $pluginStr = file_get_contents($pluginFile);
                    if(!preg_match("/public\s+function\s+settings\s*\(/i", $pluginStr))
                    {
                        unset($plugins[$key]);
                    }
                    else
                    {
                        $readme = APP_PATH.'plugins/'.$val.'/readme.txt';
                        if(!is_file($readme))
                        {
                            $readme = APP_PATH.'plugins/'.$val.'/'.ucfirst($val).'.php';
                        }
                        $pluginStr = file_get_contents($readme);
                        $pName = $val;
                        if(preg_match("/(插件名|Plugin Name)\s*(：|:)(.*)/i", $pluginStr ,$matches))
                        {
                            $pName = trim($matches[3]);
                        }
                        $pluginslist[] = [
                            'plugin' => $val,
                            'pname' => $pName
                        ];
                    }
                }
            }
            Cache::set('pluginslist',$pluginslist,3600);
        }
        $this->assign('pluginslist', $pluginslist);
        $this->assign('permissions', Session::get($this->session_prefix.'user_type'));
        $lang = Lang::detect();
        $this->assign('lang', $lang);
    }
    //获取登录用户名
    protected function getUser()
    {
        return Session::get($this->session_prefix.'user');
    }
    //判断登录状态
    protected function checkUser()
    {
        Debug::remark('begin');
        if(!Session::has($this->session_prefix.'user_id') && Cookie::has($this->session_prefix.'user_id') && Cookie::has($this->session_prefix.'user') && Cookie::has($this->session_prefix.'user_type'))
        {
            if(Cookie::has($this->session_prefix.'user_p'))
            {
                $user = Db::name('users')->where('user_login', Cookie::get($this->session_prefix.'user'))->field('user_pass')->find();
                if(!empty($user) && md5($user['user_pass']) == Cookie::get($this->session_prefix.'user_p'))
                {
                    Session::set($this->session_prefix.'user_id',Cookie::get($this->session_prefix.'user_id'));
                    Session::set($this->session_prefix.'user',Cookie::get($this->session_prefix.'user'));
                    Session::set($this->session_prefix.'user_type',Cookie::get($this->session_prefix.'user_type'));
                }
            }
        }
        if(!Session::has($this->session_prefix.'user_id'))
        {
            $this->redirect(Url::build('/login'));
        }
        if(Session::get($this->session_prefix.'user_type') >= 7)
        {
            $this->redirect(Url::build('/user'));
        }
        $this->assign('user', $this->getUser());
    }
    //退出
    public function quit()
    {
        Session::delete($this->session_prefix.'user_id');
        Session::delete($this->session_prefix.'user');
        Session::delete($this->session_prefix.'user_type');
        Cookie::delete($this->session_prefix.'user_id');
        Cookie::delete($this->session_prefix.'user');
        Cookie::delete($this->session_prefix.'user_type');
        $this->redirect(Url::build('/login'));
    }
    protected function is_rewrite()
    {
        if(function_exists('apache_get_modules'))
        {
            $rew = apache_get_modules();
            if(in_array('mod_rewrite', $rew))
            {
                return true;
            }
        }
        return false;
    }
}