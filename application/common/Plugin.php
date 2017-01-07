<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/11/6
 */
namespace app\common;

use app\admin\controller\Tree;
use think\Db;
use think\Config;
use think\Cache;
use think\Session;
use think\Url;

class Plugin
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
    protected function domain()
    {
        $domain = Cache::get('domain');
        if($domain == false)
        {
            $domain = Db::name('options')->where('option_name','domain')->field('option_value')->find();
            $domain = $domain['option_value'];
            Cache::set('domain',$domain,3600);
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
            // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
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
    //获取用户id
    protected function userID()
    {
        $session_prefix = 'catfish'.str_replace('/','',Url::build('/'));
        return Session::get($session_prefix.'user_id');
    }
    //获取用户名
    protected function user()
    {
        $session_prefix = 'catfish'.str_replace('/','',Url::build('/'));
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
    protected function import($path)
    {
        $path = str_replace('\\','/',$path);
        $pathinfo = pathinfo($path);
        $pluginName = basename(dirname(get_class($this)));
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
}