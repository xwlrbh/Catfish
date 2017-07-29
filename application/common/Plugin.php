<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/11/6
 */
namespace app\common;

use think\Controller;
use app\admin\controller\Tree;
use think\Db;
use think\Config;
use think\Cache;
use think\Session;
use think\Url;
use think\Validate;

class Plugin extends Controller
{
    protected function get($key)
    {
        $re = Db::name('options')->where('option_name','p_'.$key)->field('option_value')->find();
        if(isset($re['option_value']))
        {
            return $re['option_value'];
        }
        else
        {
            return '';
        }
    }
    protected function set($key,$value,$protection = false)
    {
        $re = Db::name('options')->where('option_name','p_'.$key)->field('option_value')->find();
        if(empty($re))
        {
            $data = [
                'option_name' => 'p_'.$key,
                'option_value' => $value,
                'autoload' => 0
            ];
            Db::name('options')->insert($data);
        }
        else
        {
            if($protection == false)
            {
                Db::name('options')
                    ->where('option_name', 'p_'.$key)
                    ->update(['option_value' => $value]);
            }
        }
    }
    protected function delete($key)
    {
        Db::name('options')->where('option_name', 'p_'.$key)->delete();
    }
    public function run(&$params)
    {
    }
    protected function domain($check_pseudo_static = false)
    {
        $domain = Cache::get('domain');
        if($domain == false)
        {
            $domain = Db::name('options')->where('option_name','domain')->field('option_value')->find();
            $domain = $domain['option_value'];
            Cache::set('domain',$domain,3600);
        }
        if($check_pseudo_static == true)
        {
            $root = '';
            $dm = Url::build('/');
            if(strpos($dm,'/index.php') !== false)
            {
                $root = 'index.php/';
            }
            $domain = $domain . $root;
        }
        return $domain;
    }
    protected function upload($width = 0, $height = 0)
    {
        //$width，$height不等于0时，生成缩略图
        $file = request()->file('file');
        $validate = [
            'ext' => 'jpg,png,gif,jpeg'
        ];
        $file->validate($validate);
        $info = $file->move(ROOT_PATH . 'data' . DS . 'uploads');
        if($info){
            if($width > 0 && $height >0)
            {
                //生成缩略图
                $image = \think\Image::open(ROOT_PATH . 'data' . DS . 'uploads' . DS . $info->getSaveName());
                $tuwidth = $image->width();
                $tuheight = $image->height();
                if($tuwidth > $width || $tuheight > $height)
                {
                    @$image->thumb($width, $height)->save(ROOT_PATH . 'data' . DS . 'uploads' . DS . $info->getSaveName());
                }
            }
            echo $info->getSaveName();
        }else{
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }
    //获取分类
    protected function category()
    {
        $data = Db::name('terms')->field('id,term_name,parent_id')->select();
        if(is_array($data) && count($data) > 0)
        {
            $r = Tree::makeTreeForHtml($data);
            foreach($r as $key => $val){
                $r[$key]['level'] = str_repeat('&#12288;',$val['level']);
            }
            return $r;
        }
        else
        {
            return [];
        }
    }
    protected function subcategory($subcategoryName = '', $category = [], $self = true)
    {
        if(empty($subcategoryName))
        {
            return $category;
        }
        $delstart = true;
        $level = '';
        foreach((array)$category as $key => $val)
        {
            if($val['term_name'] != $subcategoryName && $delstart == true)
            {
                unset($category[$key]);
                continue;
            }
            if($val['term_name'] == $subcategoryName && $delstart == true)
            {
                $delstart = false;
                $level = $val['level'];
                if($self == false)
                {
                    unset($category[$key]);
                }
                continue;
            }
            if($delstart == false && strlen($val['level']) > strlen($level))
            {
                continue;
            }
            if($delstart == false && strlen($val['level']) <= strlen($level))
            {
                $delstart = true;
                $level = '';
                if($val['term_name'] != $subcategoryName)
                {
                    unset($category[$key]);
                    continue;
                }
                else
                {
                    $delstart = false;
                    $level = $val['level'];
                    if($self == false)
                    {
                        unset($category[$key]);
                    }
                    continue;
                }
            }
        }
        return $category;
    }
    //获取用户id
    protected function userID()
    {
        $session_prefix = 'catfish'.str_replace(['/','.',' ','-'],['','?','*','|'],Url::build('/'));
        return Session::get($session_prefix.'user_id');
    }
    //获取用户名
    protected function user()
    {
        $session_prefix = 'catfish'.str_replace(['/','.',' ','-'],['','?','*','|'],Url::build('/'));
        return Session::get($session_prefix.'user');
    }
    protected function prefix()
    {
        //返回表前缀
        return Config::get('database.prefix');
    }
    protected function execute($statement)
    {
        //执行语句
        if(strtolower(substr(ltrim($statement),0,6)) == 'select' || strtolower(substr(ltrim($statement),0,4)) == 'show')
        {
            try{
                return Db::query($statement);
            }catch(\Exception $e){
                return false;
            }
        }
        else
        {
            try{
                return Db::execute($statement);
            }catch(\Exception $e){
                return false;
            }
        }
    }
    //给标签添加数据
    protected function add(&$params,$label,$data)
    {
        if(isset($params[$label]))
        {
            $params[$label] .= $data;
        }
        else
        {
            $params[$label] = $data;
        }
    }
    protected function addTop(&$params,$label,$data)
    {
        if(isset($params[$label]))
        {
            $params[$label] = $data . $params[$label];
        }
        else
        {
            $params[$label] = $data;
        }
    }
    protected function addArray(&$params,$label,$array)
    {
        $params[$label][$array['name']] = $array;
    }
    protected function import($path)
    {
        $path = str_replace('\\','/',$path);
        $pathinfo = pathinfo($path);
        $pluginName = get_class($this);
        $pluginName = str_replace('\\','/',$pluginName);
        $pluginName = trim($pluginName,'/');
        $pluginNameArr = explode('/',$pluginName);
        $pluginNameArr = array_slice($pluginNameArr,-2,1);
        $pluginName = $pluginNameArr[0];
        $path = substr($path,0,1) == '/' ? substr($path,1) : $path;
        $file = APP_PATH.'plugins/'.$pluginName.'/'.$path;
        if(is_file($file))
        {
            $content = file_get_contents($file);
            if($pathinfo['extension'] == 'js')
            {
                return '<script type="text/javascript"> ' . $content . ' </script>';
            }
            elseif($pathinfo['extension'] == 'css')
            {
                return '<style type="text/css"> ' . $content . ' </style>';
            }
            elseif(in_array($pathinfo['extension'],['jpeg','jpg','png','gif']))
            {
                $domain = $this->domain();
                $domain = substr($domain,-1,1) == '/' ? substr($domain,0,strlen($domain)-1) : $domain;
                return $domain . Url::build('/multimedia') . '?path='.urlencode($pluginName.'/'.$path).'&ext='.$pathinfo['extension'].'&media=image';
            }
            else
            {
                return '';
            }
        }
        else
        {
            return '';
        }
    }
    protected function siteName()
    {
        $siteName = Cache::get('plugin_siteName');
        if($siteName == false)
        {
            $siteName = Db::name('options')->where('option_name','title')->field('option_value')->find();
            $siteName = $siteName['option_value'];
            Cache::set('plugin_siteName',$siteName,3600);
        }
        return $siteName;
    }
    protected function binding($view,$name,&$params,$title = '')
    {
        if(isset($params['name']) && $params['name'] == $name)
        {
            $params['view'] = $view;
            if(!empty($title))
            {
                $params['title'] = $title;
            }
        }
    }
    protected function delfile($url)
    {
        $url = str_replace('\\','/',$url);
        $weizhi = strripos($url,'data/');
        if($weizhi === false)
        {
            return false;
        }
        else
        {
            $path = substr($url,$weizhi);
            if(is_file(APP_PATH.'../'.$path))
            {
                if(@unlink(APP_PATH.'../'.$path))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
            else
            {
                return true;
            }
        }
    }
    protected function verify($rule, $msg = [])
    {
        $validate = new Validate($rule, $msg);
        return $validate;
    }
    protected function uhref($label,$isTop = false)
    {
        if($isTop == true)
        {
            return Url::build('/user/Index/plugint/name/'.$label);
        }
        else
        {
            return Url::build('/user/Index/plugin/name/'.$label);
        }
    }
    protected function bindUser($userInfo)
    {
        $re = Db::name('users')->where('user_login',$userInfo['uname'])->field('id')->find();
        if(empty($re))
        {
            $eml = '';
            if(isset($userInfo['email']))
            {
                $eml = $userInfo['email'];
            }
            $pwd = '';
            if(isset($userInfo['password']))
            {
                $pwd = $userInfo['password'];
            }
            $data = [
                'user_login' => $userInfo['uname'],
                'user_pass' => md5($pwd),
                'user_nicename' => $userInfo['uname'],
                'user_email' => $eml,
                'last_login_ip' => get_client_ip(0,true),
                'create_time' => date("Y-m-d H:i:s"),
                'user_type' => 7
            ];
            $reid = Db::name('users')->insertGetId($data);
            return $reid;
        }
        else
        {
            return $re['id'];
        }
    }
    protected function thirdPartyLogin($userInfo)
    {
        try{
            Db::name('users')
                ->where('id', $userInfo['uid'])
                ->update([
                    'last_login_ip' => get_client_ip(0,true),
                    'last_login_time' => date("Y-m-d H:i:s")
                ]);
        }catch(\Exception $e)
        {
            return false;
        }
        $session_prefix = 'catfish'.str_replace(['/','.',' ','-'],['','?','*','|'],Url::build('/'));
        Session::set($session_prefix.'user_id',$userInfo['uid']);
        Session::set($session_prefix.'user',$userInfo['uname']);
        Session::set($session_prefix.'user_type',7);
        return true;
    }
    protected function recurseCopy($src,$dst){
        $dir=opendir($src);
        @mkdir($dst);
        while(false!==($file=readdir($dir))){
            if(($file!='.' )&&($file!='..')){
                if(is_dir($src.'/'.$file)){
                    $this->recurseCopy($src.'/'.$file,$dst.'/'.$file);
                }
                else{
                    copy($src.'/'.$file,$dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }
}