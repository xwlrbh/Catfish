<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/10/13
 */
namespace app\index\controller;

use app\admin\controller\Tree;
use think\Controller;
use think\Session;
use think\Cookie;
use think\Config;
use think\Db;
use think\Cache;
use think\Url;
use think\Request;
use think\Hook;
use think\Lang;

class Common extends Controller
{
    protected $plugins = [];
    protected $params = [];
    protected $session_prefix;
    protected $lang;
    public function _initialize()
    {
        if(!is_file(APP_PATH . 'install.lock')){
            if($this->is_rewrite())
            {
                $this->redirect(Url::build('/install'));
            }
            else
            {
                $this->redirect(Url::build('/index.php/install'));
            }
            exit();
        }
        $dm = Url::build('/');
        if(strpos($dm,'/index.php') ===false)
        {
            if($this->is_rewrite() == false)
            {
                $options_spare = Cache::get('options_spare');
                if($options_spare == false)
                {
                    $options_spare = Db::name('options')->where('option_name','spare')->field('option_value')->find();
                    $options_spare = $options_spare['option_value'];
                    if(!empty($options_spare))
                    {
                        $options_spare = unserialize($options_spare);
                    }
                    Cache::set('options_spare',$options_spare,3600);
                }
                if(!isset($options_spare['rewrite']) || $options_spare['rewrite'] == 0)
                {
                    $this->redirect(Url::build('/').'index.php');
                }
            }
        }
        $this->lang = Lang::detect();
        $this->session_prefix = 'catfish'.str_replace('/','',Url::build('/'));
        $plugins = Cache::get('plugins');
        if($plugins == false)
        {
            $plugins = Db::name('options')->where('option_name','plugins')->field('option_value')->find();
            if(!empty($plugins))
            {
                $plugins = unserialize($plugins['option_value']);
            }
            else
            {
                $plugins = [];
            }
            Cache::set('plugins',$plugins,3600);
        }
        if(!empty($plugins))
        {
            foreach($plugins as $key => $val)
            {
                $pluginFile = APP_PATH.'plugins/'.$val.'/'.ucfirst($val).'.php';
                if(is_file($pluginFile))
                {
                    $plugins[$key] = 'app\\plugins\\'.$val.'\\'.ucfirst($val);
                    Lang::load(APP_PATH . 'plugins/'.$val.'/lang/'.$this->lang.'.php');
                }
                else
                {
                    unset($plugins[$key]);
                }
            }
            $this->plugins = $plugins;
        }
        $template = Cache::get('template');
        if($template == false)
        {
            $template = Db::name('options')->where('option_name','template')->field('option_value')->find();
            Cache::set('template',$template,3600);
        }
        Lang::load(APP_PATH . '../public/'.$template['option_value'].'/lang/'.$this->lang.'.php');
    }
    protected function login()
    {
        //获取登录状态\
        $login = '';
        if(Session::has($this->session_prefix.'user'))
        {
            $login = Session::get($this->session_prefix.'user');
        }
        return $login;
    }
    //进入用户中心
    public function userCenter()
    {
        if(Session::get($this->session_prefix.'user_type') >= 7)
        {
            $this->redirect(Url::build('/user'));
        }
        else
        {
            $this->redirect(Url::build('/admin'));
        }
    }
    //退出
    public function quit()
    {
        Session::delete($this->session_prefix.'user_id');
        Session::delete($this->session_prefix.'user');
        Session::delete($this->session_prefix.'user_type');
        Cookie::delete($this->session_prefix.'user_id');
        Cookie::delete($this->session_prefix.'user');
        Cookie::delete($this->session_prefix.'user_p');
        $this->redirect(Url::build('/'));
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
        $this->assign('catfish', '<a href="http://www.'.$version['official'].'/" target="_blank" id="catfish">'.$version['name'].'&nbsp;'.$version['number'].'</a>&nbsp;&nbsp;');
        $template = 'default';
        $pageSettings = '';
        foreach($data_options as $key => $val)
        {
            //获取主题
            if($val['option_name'] == 'template')
            {
                $template = $val['option_value'];
            }
            //获取页面设置
            if($val['option_name'] == 'pageSettings')
            {
                $pageSettings = unserialize($val['option_value']);
            }
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
                    $submenu = $this->checkUrl(Tree::makeTree($submenu));
                }
                $menu['menu'.$start] = $submenu;
                $start++;
            }
            Cache::set('menu',$menu,3600);
        }
        $menu['lang'] = $this->lang;
        Hook::add('filter_menu',$this->plugins);
        Hook::listen('filter_menu',$menu);
        unset($menu['lang']);
        $this->assign('menu', $menu);
        //获取混合内容
        $page = 1;
        if(Request::instance()->has('page','get'))
        {
            $page = Request::instance()->get('page');
        }
        $hunhe = Cache::get('hunhe'.$page);
        if($hunhe == false)
        {
            $start = 1;
            $hunhe =[];
            foreach($pageSettings['hunhe'] as $key => $val)
            {
                if($val['shuliang'] == 0)
                {
                    $val['shuliang'] = 10000;
                }
                $aord = 'desc';
                if($val['fangshi'] == 'id')
                {
                    $aord = 'asc';
                }
                $data = '';
                if($val['fenlei'] == 0)
                {
                    $data = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                        ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                        ->where('post_status','=',1)
                        ->where('post_type','=',0)
                        ->where('status','=',1)
                        ->order($val['fangshi'].' '.$aord)
                        ->paginate($val['shuliang']);
                }
                else
                {
                    $data = Db::view('term_relationships','term_id')
                        ->view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan','posts.id=term_relationships.object_id')
                        ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                        ->where('term_id','=',$val['fenlei'])
                        ->where('post_status','=',1)
                        ->where('post_type','=',0)
                        ->where('status','=',1)
                        ->order($val['fangshi'].' '.$aord)
                        ->paginate($val['shuliang']);
                }
                $pages = $data->render();
                $pageArr = $data->toArray();
                $hunhe['hunhe'.$start] = [
                    'biaoti' => $val['biaoti'],
                    'neirong' => $this->addArticleHref($pageArr['data']),
                    'pages' => $pages
                ];
                $start++;
            }
            Cache::set('hunhe'.$page,$hunhe,3600);
        }
        $hunhe['lang'] = $this->lang;
        $hunhe['page'] = $page;
        Hook::add('filter_hunhe',$this->plugins);
        Hook::listen('filter_hunhe',$hunhe);
        unset($hunhe['lang']);
        unset($hunhe['page']);
        $this->assign('hunhe', $hunhe);
        //获取图文内容
        $page = 1;
        if(Request::instance()->has('page','get'))
        {
            $page = Request::instance()->get('page');
        }
        $tuwen = Cache::get('tuwen'.$page);
        if($tuwen == false)
        {
            $start = 1;
            $tuwen =[];
            foreach($pageSettings['tuwen'] as $key => $val)
            {
                if($val['shuliang'] == 0)
                {
                    $val['shuliang'] = 10000;
                }
                $aord = 'desc';
                if($val['fangshi'] == 'id')
                {
                    $aord = 'asc';
                }
                $data = '';
                if($val['fenlei'] == 0)
                {
                    $data = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                        ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                        ->where('post_status','=',1)
                        ->where('post_type','=',0)
                        ->where('status','=',1)
                        ->where('thumbnail','neq','')
                        ->order($val['fangshi'].' '.$aord)
                        ->paginate($val['shuliang']);
                }
                else
                {
                    $data = Db::view('term_relationships','term_id')
                        ->view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan','posts.id=term_relationships.object_id')
                        ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                        ->where('term_id','=',$val['fenlei'])
                        ->where('post_status','=',1)
                        ->where('post_type','=',0)
                        ->where('status','=',1)
                        ->where('thumbnail','neq','')
                        ->order($val['fangshi'].' '.$aord)
                        ->paginate($val['shuliang']);
                }
                $pages = $data->render();
                $pageArr = $data->toArray();
                $tuwen['tuwen'.$start] = [
                    'biaoti' => $val['biaoti'],
                    'neirong' => $this->addArticleHref($pageArr['data']),
                    'pages' => $pages
                ];
                $start++;
            }
            Cache::set('tuwen'.$page,$tuwen,3600);
        }
        $tuwen['lang'] = $this->lang;
        $tuwen['page'] = $page;
        Hook::add('filter_tuwen',$this->plugins);
        Hook::listen('filter_tuwen',$tuwen);
        unset($tuwen['lang']);
        unset($tuwen['page']);
        $this->assign('tuwen', $tuwen);
        //获取推荐
        $tuijian = Cache::get('tuijian');
        if($tuijian == false)
        {
            $tuijian = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('post_status','=',1)
                ->where('post_type','=',0)
                ->where('status','=',1)
                ->where('recommended','=',1)
                ->order('post_modified desc')
                ->limit(10)
                ->select();
            $tuijian = $this->addArticleHref($tuijian);
            Cache::set('tuijian',$tuijian,3600);
        }
        $tuijian['lang'] = $this->lang;
        Hook::add('filter_tuijian',$this->plugins);
        Hook::listen('filter_tuijian',$tuijian);
        unset($tuijian['lang']);
        $this->assign('tuijian', $tuijian);
        //获取登录状态
        $this->assign('login', $this->login());
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
        //侦听
        Hook::add('top',$this->plugins);
        Hook::add('mid',$this->plugins);
        Hook::add('bottom',$this->plugins);
        Hook::add('side_top',$this->plugins);
        Hook::add('side_mid',$this->plugins);
        Hook::add('side_bottom',$this->plugins);
        Hook::listen('top',$this->params);
        Hook::listen('mid',$this->params);
        Hook::listen('bottom',$this->params);
        Hook::listen('side_top',$this->params);
        Hook::listen('side_mid',$this->params);
        Hook::listen('side_bottom',$this->params);
        if(isset($this->params['top']))
        {
            $this->assign('top', $this->params['top']);
        }
        if(isset($this->params['mid']))
        {
            $this->assign('mid', $this->params['mid']);
        }
        if(isset($this->params['bottom']))
        {
            $this->assign('bottom', $this->params['bottom']);
        }
        if(isset($this->params['side_top']))
        {
            $this->assign('side_top', $this->params['side_top']);
        }
        if(isset($this->params['side_mid']))
        {
            $this->assign('side_mid', $this->params['side_mid']);
        }
        if(isset($this->params['side_bottom']))
        {
            $this->assign('side_bottom', $this->params['side_bottom']);
        }

        return $template;
    }
    private function checkUrl($params)
    {
        foreach($params as $key => $val)
        {
            $params[$key]['href'] = str_replace(['/index/Index','/id'],'',$val['href']);
            if(isset($val['children']))
            {
                $params[$key]['children'] = $this->checkUrl($val['children']);
            }
        }
        return $params;
    }
    protected function addArticleHref($params)
    {
        foreach($params as $key => $val)
        {
            $params[$key]['href'] = '/article/'.$val['id'];
        }
        return $params;
    }
}