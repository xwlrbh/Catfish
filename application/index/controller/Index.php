<?php
/**
 * Project: Catfish.
 * Author: A.J
 * Date: 2016/9/29
 */
namespace app\index\controller;

use app\index\controller\Common;
use think\Db;
use think\Cache;
use think\Url;
use think\Request;
use think\Session;
use think\Validate;
use think\Hook;
use think\Lang;

class Index extends Common
{
    public function index()
    {
        Hook::add('home_top',$this->plugins);
        Hook::add('home_mid',$this->plugins);
        Hook::add('home_bottom',$this->plugins);
        Hook::add('home_extend',$this->plugins);
        Hook::add('home_side_top',$this->plugins);
        Hook::add('home_side_mid',$this->plugins);
        Hook::add('home_side_bottom',$this->plugins);
        Hook::listen('home_top',$this->params);
        Hook::listen('home_mid',$this->params);
        Hook::listen('home_bottom',$this->params);
        Hook::listen('home_extend',$this->params);
        Hook::listen('home_side_top',$this->params);
        Hook::listen('home_side_mid',$this->params);
        Hook::listen('home_side_bottom',$this->params);
        if(isset($this->params['home_top']))
        {
            $this->assign('home_top', $this->params['home_top']);
        }
        if(isset($this->params['home_mid']))
        {
            $this->assign('home_mid', $this->params['home_mid']);
        }
        if(isset($this->params['home_bottom']))
        {
            $this->assign('home_bottom', $this->params['home_bottom']);
        }
        if(isset($this->params['home_extend']))
        {
            $this->assign('home_extend', $this->params['home_extend']);
        }
        if(isset($this->params['home_side_top']))
        {
            $this->assign('home_side_top', $this->params['home_side_top']);
        }
        if(isset($this->params['home_side_mid']))
        {
            $this->assign('home_side_mid', $this->params['home_side_mid']);
        }
        if(isset($this->params['home_side_bottom']))
        {
            $this->assign('home_side_bottom', $this->params['home_side_bottom']);
        }

        //获取幻灯片
        $data_slide = Cache::get('slide');
        if($data_slide == false)
        {
            $data_slide = Db::name('slide')->where('slide_status',1)->order('listorder')->select();
            Cache::set('slide',$data_slide,3600);
        }
        $this->assign('slide', $data_slide);//输出幻灯片
        //获取友情链接
        $data_links = Cache::get('links');
        if($data_links == false)
        {
            $data_links = Db::name('links')->where('link_location',1)->where('link_status',1)->field('link_url,link_name,link_image,link_target')->order('listorder')->select();
            Cache::set('links',$data_links,3600);
        }
        $this->assign('links', $data_links);//输出友情链接
        $template = $this->receive();//主题目录
        $this->assign('pageUrl', $this->getpage());//确定是哪个页面
        $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/index.html');
        return $htmls;
    }
    public function article($id = 0)
    {
        if($id == 0)
        {
            Hook::add('article_list_top',$this->plugins);
            Hook::add('article_list_mid',$this->plugins);
            Hook::add('article_list_bottom',$this->plugins);
            Hook::add('article_list_side_top',$this->plugins);
            Hook::add('article_list_side_mid',$this->plugins);
            Hook::add('article_list_side_bottom',$this->plugins);
            Hook::listen('article_list_top',$this->params);
            Hook::listen('article_list_mid',$this->params);
            Hook::listen('article_list_bottom',$this->params);
            Hook::listen('article_list_side_top',$this->params);
            Hook::listen('article_list_side_mid',$this->params);
            Hook::listen('article_list_side_bottom',$this->params);
            if(isset($this->params['article_list_top']))
            {
                $this->assign('article_list_top', $this->params['article_list_top']);
            }
            if(isset($this->params['article_list_mid']))
            {
                $this->assign('article_list_mid', $this->params['article_list_mid']);
            }
            if(isset($this->params['article_list_bottom']))
            {
                $this->assign('article_list_bottom', $this->params['article_list_bottom']);
            }
            if(isset($this->params['article_list_side_top']))
            {
                $this->assign('article_list_side_top', $this->params['article_list_side_top']);
            }
            if(isset($this->params['article_list_side_mid']))
            {
                $this->assign('article_list_side_mid', $this->params['article_list_side_mid']);
            }
            if(isset($this->params['article_list_side_bottom']))
            {
                $this->assign('article_list_side_bottom', $this->params['article_list_side_bottom']);
            }

            //显示文章列表
            $page = 1;
            if(Request::instance()->has('page','get'))
            {
                $page = Request::instance()->get('page');
            }
            $data = Cache::get('article'.$page);
            if($data == false)
            {
                $data = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                    ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                    ->where('post_status','=',1)
                    ->where('post_type','=',0)
                    ->where('status','=',1)
                    ->order('post_modified desc')
                    ->paginate(10);
                Cache::set('article'.$page,$data,3600);
            }
            $pages = $data->render();
            $pageArr = $data->toArray();
            $data = $this->addArticleHref($pageArr['data']);
            $data['lang'] = $this->lang;
            $data['page'] = $page;
            Hook::add('filter_articleList',$this->plugins);
            Hook::listen('filter_articleList',$data);
            unset($data['lang']);
            unset($data['page']);
            $this->assign('fenlei', $data);
            $this->assign('pages', $pages);
            $this->assign('daohang1', Lang::get('Article list'));
            $template = $this->receive();//主题目录
            $this->assign('pageUrl', $this->getpage());//确定是哪个页面
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/category.html');
            return $htmls;
        }
        else
        {
            //点击加一
            Db::name('posts')
                ->where('id', $id)
                ->setInc('post_hits');
            //文章内容
            $data = Db::view('posts','id,post_keywords as guanjianzi,post_source as laiyuan,post_content as zhengwen,post_title as biaoti,post_excerpt as zhaiyao,comment_status,post_modified as fabushijian,post_type,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('posts.id',$id)
                ->find();
            $data['lang'] = $this->lang;
            Hook::add('filter_article',$this->plugins);
            Hook::listen('filter_article',$data);
            unset($data['lang']);
            Hook::add('read',$this->plugins);
            $params = [
                'title' => $data['biaoti'],
                'content' => $data['zhengwen']
            ];
            Hook::listen('read',$params);
            $data['biaoti'] = $params['title'];
            $data['zhengwen'] = $params['content'];
            $this->assign('neirong', $data);

            Hook::add('article_top',$this->plugins);
            Hook::add('article_mid',$this->plugins);
            Hook::add('article_bottom',$this->plugins);
            Hook::add('article_extend',$this->plugins);
            Hook::add('article_side_top',$this->plugins);
            Hook::add('article_side_mid',$this->plugins);
            Hook::add('article_side_bottom',$this->plugins);
            Hook::add('comment_top',$this->plugins);
            Hook::add('comment_mid',$this->plugins);
            Hook::add('comment_bottom',$this->plugins);
            $this->params = [
                'id' => $data['id'],
                'post_type' =>$data['post_type']
            ];
            Hook::listen('article_top',$this->params);
            Hook::listen('article_mid',$this->params);
            Hook::listen('article_bottom',$this->params);
            Hook::listen('article_extend',$this->params);
            Hook::listen('article_side_top',$this->params);
            Hook::listen('article_side_mid',$this->params);
            Hook::listen('article_side_bottom',$this->params);
            Hook::listen('comment_top',$this->params);
            Hook::listen('comment_mid',$this->params);
            Hook::listen('comment_bottom',$this->params);
            if(isset($this->params['article_top']))
            {
                $this->assign('article_top', $this->params['article_top']);
            }
            if(isset($this->params['article_mid']))
            {
                $this->assign('article_mid', $this->params['article_mid']);
            }
            if(isset($this->params['article_bottom']))
            {
                $this->assign('article_bottom', $this->params['article_bottom']);
            }
            if(isset($this->params['article_extend']))
            {
                $this->assign('article_extend', $this->params['article_extend']);
            }
            if(isset($this->params['article_side_top']))
            {
                $this->assign('article_side_top', $this->params['article_side_top']);
            }
            if(isset($this->params['article_side_mid']))
            {
                $this->assign('article_side_mid', $this->params['article_side_mid']);
            }
            if(isset($this->params['article_side_bottom']))
            {
                $this->assign('article_side_bottom', $this->params['article_side_bottom']);
            }
            if(isset($this->params['comment_top']))
            {
                $this->assign('comment_top', $this->params['comment_top']);
            }
            if(isset($this->params['comment_mid']))
            {
                $this->assign('comment_mid', $this->params['comment_mid']);
            }
            if(isset($this->params['comment_bottom']))
            {
                $this->assign('comment_bottom', $this->params['comment_bottom']);
            }

            //前后内容
            $previous = Db::name('posts')->where('id','<',$id)->where('post_type',0)->field('id,post_title as biaoti')->order('id desc')->find();
            if(!empty($previous))
            {
                $previous['href'] = '/article/'.$previous['id'];
                $previous['lang'] = $this->lang;
                Hook::add('filter_prevArticle',$this->plugins);
                Hook::listen('filter_prevArticle',$previous);
                unset($previous['lang']);
            }
            $this->assign('previous', $previous);
            $next = Db::name('posts')->where('id','>',$id)->where('post_type',0)->field('id,post_title as biaoti')->order('id')->find();
            if(!empty($next))
            {
                $next['href'] = '/article/'.$next['id'];
                $next['lang'] = $this->lang;
                Hook::add('filter_nextArticle',$this->plugins);
                Hook::listen('filter_nextArticle',$next);
                unset($next['lang']);
            }
            $this->assign('next', $next);
            if($data['comment_status'] == 1)
            {
                //评论内容
                $pinglun = Db::view('comments','id,createtime as shijian,content as neirong')
                    ->view('users','user_login,user_nicename as nicheng,user_email as email,user_url as url,avatar as touxiang,signature as qianming','users.id=comments.uid')
                    ->where('comments.post_id','=',$id)
                    ->where('comments.status','=',1)
                    ->order('comments.createtime desc')
                    ->paginate(10);
                $this->assign('pinglun', $pinglun);
            }
            $this->assign('yunxupinglun', $data['comment_status']);//是否允许评论
            $template = $this->receive();
            $this->assign('keyword', $data['guanjianzi']);
            $this->assign('description', $data['zhaiyao']);
            $this->assign('pageUrl', $this->getpage());//确定是哪个页面
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/article.html');
            return $htmls;
        }
    }
    //评论
    public function pinglun()
    {
        $comment = Db::name('options')->where('option_name','comment')->field('option_value')->find();
        $plzt = 1;
        if($comment['option_value'] == 1)
        {
            $plzt = 0;
        }
        $beipinglunren = Db::name('posts')->where('id',Request::instance()->post('id'))->field('post_author')->find();
        //添加评论
        $data = [
            'post_id' => Request::instance()->post('id'),
            'url' => 'index/Index/article/id/'.Request::instance()->post('id'),
            'uid' => Session::get($this->session_prefix.'user_id'),
            'to_uid' => $beipinglunren['post_author'],
            'createtime' => date("Y-m-d H:i:s"),
            'content' => Request::instance()->post('pinglun'),
            'status' => $plzt
        ];
        Db::name('comments')->insert($data);
        //修改评论信息
        Db::name('posts')
            ->where('id', Request::instance()->post('id'))
            ->update([
                'post_comment' => date("Y-m-d H:i:s"),
                'comment_count' => ['exp','comment_count+1']
            ]);
    }
    //点赞
    public function zan()
    {
        //赞加一
        Db::name('posts')
            ->where('id', Request::instance()->post('id'))
            ->setInc('post_like');
        return false;
    }
    //收藏
    public function shoucang()
    {
        $data = Db::name('user_favorites')->where('uid',Session::get($this->session_prefix.'user_id'))->where('object_id',Request::instance()->post('id'))->field('id')->find();
        if(empty($data))
        {
            $postdata = Db::name('posts')->where('id',Request::instance()->post('id'))->field('id,post_title,post_excerpt')->find();
            $data = [
                'uid' => Session::get($this->session_prefix.'user_id'),
                'title' => $postdata['post_title'],
                'url' => 'index/Index/article/id/'.Request::instance()->post('id'),
                'description' => $postdata['post_excerpt'],
                'object_id' => Request::instance()->post('id'),
                'createtime' => date("Y-m-d H:i:s")
            ];
            Db::name('user_favorites')->insert($data);
        }
        return true;
    }
    public function category($id)
    {
        Hook::add('category_top',$this->plugins);
        Hook::add('category_mid',$this->plugins);
        Hook::add('category_bottom',$this->plugins);
        Hook::add('category_side_top',$this->plugins);
        Hook::add('category_side_mid',$this->plugins);
        Hook::add('category_side_bottom',$this->plugins);
        Hook::listen('category_top',$this->params);
        Hook::listen('category_mid',$this->params);
        Hook::listen('category_bottom',$this->params);
        Hook::listen('category_side_top',$this->params);
        Hook::listen('category_side_mid',$this->params);
        Hook::listen('category_side_bottom',$this->params);
        if(isset($this->params['category_top']))
        {
            $this->assign('category_top', $this->params['category_top']);
        }
        if(isset($this->params['category_mid']))
        {
            $this->assign('category_mid', $this->params['category_mid']);
        }
        if(isset($this->params['category_bottom']))
        {
            $this->assign('category_bottom', $this->params['category_bottom']);
        }
        if(isset($this->params['category_side_top']))
        {
            $this->assign('category_side_top', $this->params['category_side_top']);
        }
        if(isset($this->params['category_side_mid']))
        {
            $this->assign('category_side_mid', $this->params['category_side_mid']);
        }
        if(isset($this->params['category_side_bottom']))
        {
            $this->assign('category_side_bottom', $this->params['category_side_bottom']);
        }

        //文章分类
        $fenleiming = Db::name('terms')->where('id',$id)->field('id,term_name')->find();
        $fenleiming['lang'] = $this->lang;
        Hook::add('filter_categoryName',$this->plugins);
        Hook::listen('filter_categoryName',$fenleiming);
        unset($fenleiming['lang']);
        $this->assign('daohang1', $fenleiming['term_name']);//获取分类名
        $page = 1;
        if(Request::instance()->has('page','get'))
        {
            $page = Request::instance()->get('page');
        }
        $data = Cache::get('category'.$id.$page);
        if($data == false)
        {
            $data = Db::view('term_relationships','term_id')
                ->view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan','posts.id=term_relationships.object_id')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('term_id','=',$id)
                ->where('post_status','=',1)
                ->where('post_type','=',0)
                ->where('status','=',1)
                ->order('istop desc,post_modified desc')
                ->paginate(10);
            Cache::set('category'.$id.$page,$data,3600);
        }
        $pages = $data->render();
        $pageArr = $data->toArray();
        $data = $this->addArticleHref($pageArr['data']);
        $data['lang'] = $this->lang;
        $data['page'] = $page;
        Hook::add('filter_category',$this->plugins);
        Hook::listen('filter_category',$data);
        unset($data['lang']);
        unset($data['page']);
        $this->assign('fenlei', $data);
        $this->assign('pages', $pages);
        $template = $this->receive();//主题目录
        $this->assign('pageUrl', $this->getpage());//确定是哪个页面
        $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/category.html');
        return $htmls;
    }
    public function page($id)
    {
        //页面
        $data = Db::name('posts')
            ->where('id',$id)
            ->field('id,post_keywords as guanjianzi,post_content as zhengwen,post_title as biaoti,post_excerpt as zhaiyao,thumbnail as suolvetu,template')
            ->find();
        $data['lang'] = $this->lang;
        Hook::add('filter_page',$this->plugins);
        Hook::listen('filter_page',$data);
        unset($data['lang']);
        $this->assign('page', $data);

        Hook::add('page_top',$this->plugins);
        Hook::add('page_mid',$this->plugins);
        Hook::add('page_bottom',$this->plugins);
        Hook::add('page_extend',$this->plugins);
        Hook::add('page_side_top',$this->plugins);
        Hook::add('page_side_mid',$this->plugins);
        Hook::add('page_side_bottom',$this->plugins);
        $this->params = [
            'id' => $data['id'],
            'template' => $data['template']
        ];
        Hook::listen('page_top',$this->params);
        Hook::listen('page_mid',$this->params);
        Hook::listen('page_bottom',$this->params);
        Hook::listen('page_extend',$this->params);
        Hook::listen('page_side_top',$this->params);
        Hook::listen('page_side_mid',$this->params);
        Hook::listen('page_side_bottom',$this->params);
        if(isset($this->params['page_top']))
        {
            $this->assign('page_top', $this->params['page_top']);
        }
        if(isset($this->params['page_mid']))
        {
            $this->assign('page_mid', $this->params['page_mid']);
        }
        if(isset($this->params['page_bottom']))
        {
            $this->assign('page_bottom', $this->params['page_bottom']);
        }
        if(isset($this->params['page_extend']))
        {
            $this->assign('page_extend', $this->params['page_extend']);
        }
        if(isset($this->params['page_side_top']))
        {
            $this->assign('page_side_top', $this->params['page_side_top']);
        }
        if(isset($this->params['page_side_mid']))
        {
            $this->assign('page_side_mid', $this->params['page_side_mid']);
        }
        if(isset($this->params['page_side_bottom']))
        {
            $this->assign('page_side_bottom', $this->params['page_side_bottom']);
        }

        $template = $this->receive();//主题目录
        $this->assign('keyword', $data['guanjianzi']);
        $this->assign('description', $data['zhaiyao']);
        $this->assign('pageUrl', $this->getpage());
        $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/page/'.$data['template']);
        return $htmls;
    }
    public function search()
    {
        Hook::add('search_top',$this->plugins);
        Hook::add('search_mid',$this->plugins);
        Hook::add('search_bottom',$this->plugins);
        Hook::add('search_side_top',$this->plugins);
        Hook::add('search_side_mid',$this->plugins);
        Hook::add('search_side_bottom',$this->plugins);
        $this->params = [
            'keyword' => Request::instance()->get('keyword')
        ];
        Hook::listen('search_top',$this->params);
        Hook::listen('search_mid',$this->params);
        Hook::listen('search_bottom',$this->params);
        Hook::listen('search_side_top',$this->params);
        Hook::listen('search_side_mid',$this->params);
        Hook::listen('search_side_bottom',$this->params);
        if(isset($this->params['search_top']))
        {
            $this->assign('search_top', $this->params['search_top']);
        }
        if(isset($this->params['search_mid']))
        {
            $this->assign('search_mid', $this->params['search_mid']);
        }
        if(isset($this->params['search_bottom']))
        {
            $this->assign('search_bottom', $this->params['search_bottom']);
        }
        if(isset($this->params['search_side_top']))
        {
            $this->assign('search_side_top', $this->params['search_side_top']);
        }
        if(isset($this->params['search_side_mid']))
        {
            $this->assign('search_side_mid', $this->params['search_side_mid']);
        }
        if(isset($this->params['search_side_bottom']))
        {
            $this->assign('search_side_bottom', $this->params['search_side_bottom']);
        }

        //搜索
        $search = [
            'lang' => $this->lang,
            'key' => Request::instance()->get('keyword'),
            'ids' => ''
        ];
        Hook::add('search',$this->plugins);
        Hook::listen('search',$search);
        $data = Db::view('posts','id,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
            ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
            ->where('post_status','=',1)
            ->where('post_type','=',0)
            ->where('status','=',1)
            ->where('post_keywords|post_title|post_excerpt','like','%'.Request::instance()->get('keyword').'%')
            ->whereOr('id','in',$search['ids'])
            ->order('post_modified desc')
            ->paginate(10,false,[
                'query' => [
                    'keyword' => Request::instance()->get('keyword')
                ]
            ]);
        $pages = $data->render();
        $pageArr = $data->toArray();
        $data = $this->addArticleHref($pageArr['data']);
        if(count($data) == 0)
        {
            $this->assign('sousuo', Lang::get('No search found'));
        }
        $data['lang'] = $this->lang;
        Hook::add('filter_search',$this->plugins);
        Hook::listen('filter_search',$data);
        unset($data['lang']);
        $this->assign('fenlei', $data);
        $this->assign('pages', $pages);
        $this->assign('daohang1', Lang::get('Search'));
        $template = $this->receive();//主题目录
        $this->assign('pageUrl', $this->getpage());//确定是哪个页面
        $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/category.html');
        return $htmls;
    }
    public function liuyan()
    {
        //留言
        //验证输入内容
        $rule = [
            'neirong' => 'require',
            'youxiang' => 'email'
        ];
        $msg = [
            'neirong.require' => Lang::get('Message content must be filled out'),
            'youxiang.email' => Lang::get('The e-mail format is incorrect')
        ];
        $data = [
            'neirong' => Request::instance()->post('neirong'),
            'youxiang' => Request::instance()->post('youxiang')
        ];
        $validate = new Validate($rule, $msg);
        if(!$validate->check($data))
        {
            echo $validate->getError();//验证错误输出
            exit;
        }
        $data = [
            'full_name' => htmlspecialchars(Request::instance()->post('xingming')),
            'email' => htmlspecialchars(Request::instance()->post('youxiang')),
            'title' => htmlspecialchars(Request::instance()->post('biaoti')),
            'msg' => htmlspecialchars(Request::instance()->post('neirong')),
            'createtime' => date("Y-m-d H:i:s")
        ];
        Db::name('guestbook')->insert($data);
        return 'ok';
    }
    private function getpage()
    {
        if($_SERVER['PHP_SELF'] == '/index.php')
        {
            return Url::build('index');
        }
        else
        {
            return str_replace('/index.php','',$_SERVER['PHP_SELF']);
        }
    }
}
