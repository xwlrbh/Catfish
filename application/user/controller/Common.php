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
use think\Hook;
use think\Request;

class Common extends Controller
{
    protected $session_prefix;
    protected $lang;
    protected $params = [];
    protected $plugins = [];
    protected $options_spare;
    public function _initialize()
    {
        $this->session_prefix = 'catfish'.str_replace(['/','.',' ','-'],['','?','*','|'],Url::build('/'));
        $this->options_spare = $this->optionsSpare();
        $this->lang = Lang::detect();
        $this->lang = $this->filterLanguages($this->lang);
        Lang::load(APP_PATH . 'user/lang/'.$this->lang.'.php');
        if(isset($this->options_spare['guanbi']) && $this->options_spare['guanbi'] == 1)
        {
            $this->closeWeb();
            exit();
        }
    }
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
    protected function getUser()
    {
        return Session::get($this->session_prefix.'user');
    }
    protected function receive()
    {
        $data_options = Cache::get('options');
        if($data_options == false)
        {
            $data_options = Db::name('options')->where('autoload',1)->field('option_name,option_value')->select();
            Cache::set('options',$data_options,3600);
        }
        $version = Config::get('version');
        $this->assign('catfish', '<a href="http://www.'.$version['official'].'/" target="_blank" id="catfish">'.$version['name'].'&nbsp;'.$version['number'].'</a>&nbsp;&nbsp;');
        foreach($data_options as $key => $val)
        {
            if($val['option_name'] == 'copyright' || $val['option_name'] == 'statistics')
            {
                $this->assign($val['option_name'], unserialize($val['option_value']));
            }
            else if($val['option_name'] == 'pageSettings')
            {
                ;
            }
            else
            {
                $this->assign($val['option_name'], $val['option_value']);
            }
        }
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
        $this->assign('menu', $menu);
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
        $this->getPlugins();
        Hook::add('user_menu_append',$this->plugins);
        Hook::listen('user_menu_append',$this->params);
        if(isset($this->params['user_menu_append']))
        {
            $this->assign('user_menu_append', $this->params['user_menu_append']);
        }
        Hook::add('user_menu_top',$this->plugins);
        Hook::listen('user_menu_top',$this->params);
        if(isset($this->params['user_menu_top']))
        {
            $this->assign('user_menu_top', $this->params['user_menu_top']);
        }
        Lang::load(APP_PATH . '../public/common/html/404/lang/'.$this->lang.'.php');
    }
    private function filterLanguages($parameter)
    {
        $param = strtolower($parameter);
        if($param == 'zh')
        {
            Lang::range('zh-cn');
            return 'zh-cn';
        }
        else if(stripos($param,'zh') === false)
        {
            $paramsub = substr($param,0,2);
            switch($paramsub)
            {
                case 'de':
                    Lang::range('de-de');
                    return 'de-de';
                    break;
                case 'fr':
                    Lang::range('fr-fr');
                    return 'fr-fr';
                    break;
                case 'ja':
                    Lang::range('ja-jp');
                    return 'ja-jp';
                    break;
                case 'ko':
                    Lang::range('ko-kr');
                    return 'ko-kr';
                    break;
                case 'ru':
                    Lang::range('ru-ru');
                    return 'ru-ru';
                    break;
                default:
                    return $param;
            }
        }
        else
        {
            return $param;
        }
    }
    private function getPlugins()
    {
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
    }
    private function checkUrl($params)
    {
        foreach($params as $key => $val)
        {
            if(substr($val['href'],0,4) == 'http' || $this->doNothing($val['href']))
            {
                $params[$key]['zidingyi'] = '1';
            }
            else
            {
                $params[$key]['href'] = str_replace(['/index/Index','/id'],'',$val['href']);
            }
            if(isset($val['children']))
            {
                $params[$key]['children'] = $this->checkUrl($val['children']);
            }
        }
        return $params;
    }
    protected function doNothing($param)
    {
        $param = strtolower(trim($param));
        if(substr($param,0,1)=='#')
        {
            return true;
        }
        if(substr($param,0,10)=='javascript')
        {
            $param = str_replace(' ','',$param);
            if($param == 'javascript:;' || $param == 'javascript:void(0)' || $param == 'javascript:void(0);')
            {
                return true;
            }
        }
        return false;
    }
    protected function isLegalPicture($picture)
    {
        $pathinfo = pathinfo($picture);
        if(isset($pathinfo["extension"]))
        {
            if(in_array($pathinfo["extension"],['jpeg','jpg','png','gif']) && stripos($pathinfo['dirname'],'/data/') !== false)
            {
                return true;
            }
        }
        return false;
    }
    protected function optionsSpare()
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
        return $options_spare;
    }
    private function closeWeb()
    {
        Session::delete($this->session_prefix.'user_id');
        Session::delete($this->session_prefix.'user');
        Session::delete($this->session_prefix.'user_type');
        Lang::load(APP_PATH . '../public/common/html/close/lang/'.$this->lang.'.php');
        $template = $this->receive();
        if(Request::instance()->isMobile() && is_file(APP_PATH.'../public/'.$template.'/mobile/close.html'))
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/close.html');
        }
        elseif(is_file(APP_PATH.'../public/'.$template.'/close.html'))
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/close.html');
        }
        else
        {
            $htmls = $this->fetch(APP_PATH.'../public/common/html/close/index.html');
        }
        echo $htmls;
    }
}