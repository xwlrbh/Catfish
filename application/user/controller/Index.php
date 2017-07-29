<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/10/9
 */
namespace app\user\controller;

use app\user\controller\Common;
use think\Controller;
use think\Session;
use think\Request;
use think\Db;
use think\Validate;
use think\Lang;
use think\Hook;

class Index extends Common
{
    public function index()
    {
        $this->checkUser();
        $this->receive();
        $this->assign('active', '');
        $view = $this->fetch();
        return $view;
    }
    //修改资料
    public function ziliao()
    {
        $this->checkUser();
        if(Request::instance()->isPost())
        {
            $xingbie = Request::instance()->post('xingbie');
            $data = ['user_nicename' => htmlspecialchars(Request::instance()->post('nicheng')), 'user_url' => htmlspecialchars(Request::instance()->post('gerenwangzhi')), 'sex' => intval($xingbie), 'birthday' => htmlspecialchars(Request::instance()->post('shengri')), 'signature' => htmlspecialchars(Request::instance()->post('qianming'))];
            Db::name('users')
                ->where('id', Session::get($this->session_prefix.'user_id'))
                ->update($data);
        }
        $this->receive();
        $this->assign('active', 'ziliao');
        $view = $this->fetch();
        return $view;
    }
    //修改密码
    public function gaimima()
    {
        $this->checkUser();
        if(Request::instance()->isPost())
        {
            //验证输入内容
            $rule = [
                'yuanmima' => 'require',
                'xinmima' => 'require',
                'cfxinmima' => 'require'
            ];
            $msg = [
                'yuanmima.require' => Lang::get('The original password must be filled in'),
                'xinmima.require' => Lang::get('The new password must be filled in'),
                'cfxinmima.require' => Lang::get('Confirm the new password must be filled out')
            ];
            $data = [
                'yuanmima' => Request::instance()->post('yuanmima'),
                'xinmima' => Request::instance()->post('xinmima'),
                'cfxinmima' => Request::instance()->post('cfxinmima')
            ];
            $validate = new Validate($rule, $msg);
            if(!$validate->check($data))
            {
                $this->error($validate->getError());//验证错误输出
                return false;
            }
            if(Request::instance()->post('xinmima') != Request::instance()->post('cfxinmima'))
            {
                $this->error(Lang::get('Confirm the password must be the same as the password'));//验证错误输出
                return false;
            }
            $pwd = Db::name('users')->where('id',Session::get($this->session_prefix.'user_id'))->field('user_pass')->find();
            if(md5(Request::instance()->post('yuanmima')) != $pwd['user_pass'])
            {
                $this->error(Lang::get('The original password is wrong'));//验证错误输出
                return false;
            }
            $data = ['user_pass' => md5(Request::instance()->post('xinmima'))];
            Db::name('users')
                ->where('id', Session::get($this->session_prefix.'user_id'))
                ->update($data);
        }
        $this->receive();
        $this->assign('active', 'gaimima');
        $view = $this->fetch();
        return $view;
    }
    //编辑头像
    public function touxiang()
    {
        $this->checkUser();
        if(Request::instance()->isPost())
        {
            //验证输入内容
            $rule = [
                'avatar' => 'require'
            ];
            $msg = [
                'avatar.require' => Lang::get('Please upload your avatar')
            ];
            $data = [
                'avatar' => Request::instance()->post('avatar')
            ];
            $validate = new Validate($rule, $msg);
            if(!$validate->check($data))
            {
                $this->error($validate->getError());//验证错误输出
                return false;
            }
            if($this->isLegalPicture(Request::instance()->post('avatar')))
            {
                $avatar = Db::name('users')
                    ->where('id', Session::get($this->session_prefix.'user_id'))
                    ->field('avatar')
                    ->find();
                $yuming = Db::name('options')->where('option_name','domain')->field('option_value')->find();
                //删除原图
                if(Request::instance()->post('avatar') != $avatar['avatar'] && $this->isLegalPicture($avatar['avatar']))
                {
                    $yfile = str_replace($yuming['option_value'],'',$avatar['avatar']);
                    if(!empty($yfile)){
                        $yfile = substr($yfile,0,1)=='/' ? substr($yfile,1) : $yfile;
                        $yfile = str_replace("/", DS, $yfile);
                        @unlink(APP_PATH . '..'. DS . $yfile);
                    }
                }
                $data = ['avatar' => Request::instance()->post('avatar')];
                Db::name('users')
                    ->where('id', Session::get($this->session_prefix.'user_id'))
                    ->update($data);
            }
        }
        $this->receive();
        $this->assign('active', 'touxiang');
        $view = $this->fetch();
        return $view;
    }
    //上传头像
    public function uploadhead()
    {
        $file = request()->file('file');
        $validate = [
            'ext' => 'jpg,png,gif,jpeg'
        ];
        $file->validate($validate);
        $info = $file->move(ROOT_PATH . 'data' . DS . 'uploads');
        if($info){
            //生成缩略图
            $image = \think\Image::open(ROOT_PATH . 'data' . DS . 'uploads' . DS . $info->getSaveName());
            $width = $image->width();
            $height = $image->height();
            if($width > 300 || $height > 300)
            {
                @$image->thumb(300, 300,\think\Image::THUMB_CENTER)->save(ROOT_PATH . 'data' . DS . 'uploads' . DS . $info->getSaveName());
            }
            echo $info->getSaveName();
        }
        else{
            echo $file->getError();
        }
    }
    //我的收藏
    public function shoucang()
    {
        $this->checkUser();
        $data = Db::name('user_favorites')->where('uid',Session::get($this->session_prefix.'user_id'))->order('createtime desc')->paginate(10);
        $this->assign('data', $data);
        $this->receive();
        $this->assign('active', 'shoucang');
        $view = $this->fetch();
        return $view;
    }
    //删除收藏
    public function removeshoucang()
    {
        Db::name('user_favorites')->where('id',Request::instance()->post('id'))->where('uid',Session::get($this->session_prefix.'user_id'))->delete();
        return true;
    }
    //我的评论
    public function pinglun()
    {
        $this->checkUser();
        $data = Db::name('comments')
            ->where('uid',Session::get($this->session_prefix.'user_id'))
            ->order('createtime desc')
            ->paginate(10);
        $this->assign('data', $data);
        $this->receive();
        $this->assign('active', 'pinglun');
        $view = $this->fetch();
        return $view;
    }
    //删除评论
    public function removepinglun()
    {
        Db::name('comments')->where('id',Request::instance()->post('id'))->where('uid',Session::get($this->session_prefix.'user_id'))->delete();
        return true;
    }
    public function plugin($name)
    {
        $this->checkUser();
        $this->receive();
        Hook::add('user_post_append',$this->plugins);
        if(Request::instance()->isPost())
        {
            $params = [];
            Hook::listen('user_post_append',$params);
        }
        Hook::add('user_view_append',$this->plugins);
        $params = [
            'name' => $name,
            'view' => ''
        ];
        Hook::listen('user_view_append',$params);
        $title = '';
        if(isset($this->params['user_menu_append'][$name]['title']))
        {
            $title = $this->params['user_menu_append'][$name]['title'];
        }
        else if(isset($params['title']))
        {
            $title = $params['title'];
        }
        $this->assign('menuTitle', $title);
        $this->assign('data', $params['view']);
        $this->assign('active', $name);
        $this->assign('isTop', '0');
        $view = $this->fetch();
        return $view;
    }
    public function plugint($name)
    {
        $this->checkUser();
        $this->receive();
        Hook::add('user_post_top',$this->plugins);
        if(Request::instance()->isPost())
        {
            $params = [];
            Hook::listen('user_post_top',$params);
        }
        Hook::add('user_view_top',$this->plugins);
        $params = [
            'name' => $name,
            'view' => ''
        ];
        Hook::listen('user_view_top',$params);
        $title = '';
        if(isset($this->params['user_menu_top'][$name]['title']))
        {
            $title = $this->params['user_menu_top'][$name]['title'];
        }
        else if(isset($params['title']))
        {
            $title = $params['title'];
        }
        $this->assign('menuTitle', $title);
        $this->assign('data', $params['view']);
        $this->assign('active', $name);
        $this->assign('isTop', '1');
        $view = $this->fetch(APP_PATH.'user/view/index/plugin.html');
        return $view;
    }
}