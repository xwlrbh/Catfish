<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/10/16
 */
namespace app\user\controller;

use app\admin\controller\Tree;
use think\Controller;
use think\Session;
use think\Cookie;
use think\Url;
use think\Cache;
use think\Db;
use think\Config;
use think\Lang;

class Common extends Controller
{
    protected $session_prefix;
    public function _initialize()
    {
        $this->session_prefix = 'catfish'.str_replace('/','',Url::build('/'));
        Lang::detect();
    }
    //判断登录状态
    protected function checkUser()
    {
        if(!Session::has($this->session_prefix.'user_id') && Cookie::has($this->session_prefix.'user_id') && Cookie::has($this->session_prefix.'user'))
        {
            $cookie_user_p = Cache::get('cookie_user_p');
            if(Cookie::has($this->session_prefix.'user_p') && $cookie_user_p !== false)
            {
                $user = Db::name('users')->where('user_login', Cookie::get($this->session_prefix.'user'))->field('user_pass,user_type')->find();
                if(!empty($user) && md5($cookie_user_p.$user['user_pass']) == Cookie::get($this->session_prefix.'user_p'))
                {
                    Session::set($this->session_prefix.'user_id',Cookie::get($this->session_prefix.'user_id'));
                    Session::set($this->session_prefix.'user',Cookie::get($this->session_prefix.'user'));
                    Session::set($this->session_prefix.'user_type',$user['user_type']);
                }
            }
        }
        if(!Session::has($this->session_prefix.'user_id'))
        {
            $this->redirect(Url::build('/login'));
        }
        if(Session::get($this->session_prefix.'user_type') == 1)
        {
            $this->redirect(Url::build('/admin'));
        }
        $this->assign('login', $this->getUser());
    }
    //获取登录用户名
    protected function getUser()
    {
        return Session::get($this->session_prefix.'user');
    }
    protected function receive()
    {
        //获取配置
        $data_options = Cache::get('options');
        if($data_options == false)
        {
            $data_options = Db::name('options')->where('autoload',1)->field('option_name,option_value')->select();
            Cache::set('options',$data_options,3600);
        }
        $version = Config::get('version');
        $this->assign('catfish', '<a href="http://www.'.$version['official'].'/" target="_blank">'.$version['name'].'&nbsp;'.$version['number'].'</a>&nbsp;&nbsp;');
        foreach($data_options as $key => $val)
        {
            if($val['option_name'] == 'copyright' || $val['option_name'] == 'statistics')
            {
                $this->assign($val['option_name'], unserialize($val['option_value']));
            }
            else
            {
                $this->assign($val['option_name'], $val['option_value']);
            }
        }
        //获取菜单
        $menu = Cache::get('menu');
        if($menu == false)
        {
            $menu = [];
            $menus = Db::name('nav_cat')->field('navcid,nav_name,active')->order('active desc')->select();
            $start = 1;
            foreach($menus as $key => $val)
            {
                $submenu = Db::name('nav')->where('cid',$val['navcid'])->where('status',1)->field('id,parent_id,label,target,href,icon')->order('listorder')->select();
                if(!empty($submenu))
                {
                    $submenu = Tree::makeTree($submenu);
                }
                $menu['menu'.$start] = $submenu;
                $start++;
            }
            Cache::set('menu',$menu,3600);
        }
        $this->assign('menu', $menu);
        //获取当前用户
        $user = Db::name('users')->where('id',Session::get($this->session_prefix.'user_id'))->find();
        $this->assign('user', $user);
        $domain = Cache::get('domain');
        if($domain == false)
        {
            $domain = Db::name('options')->where('option_name','domain')->field('option_value')->find();
            $domain = $domain['option_value'];
            Cache::set('domain',$domain,3600);
        }
        $this->assign('domain', $domain);
        $root = '';
        $dm = Url::build('/');
        if(strpos($dm,'/index.php') !== false)
        {
            $root = 'index.php/';
        }
        $this->assign('root', $root);
    }
}