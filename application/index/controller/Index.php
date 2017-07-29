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
        $param = '';
        Hook::add('view_post',$this->plugins);
        Hook::listen('view_post',$param);
        $param = '';
        Hook::add('home_plugin_name',$this->plugins);
        Hook::listen('home_plugin_name',$param);
        if(!empty($param))
        {
            $this->assign('plugin_name', $param);
        }
        //获取幻灯片
        $this->slide();
        //获取友情链接
        $data_links = Cache::get('links');
        if($data_links == false)
        {
            $data_links = Db::name('links')->where('link_location',1)->where('link_status',1)->field('link_url,link_name,link_image,link_target')->order('listorder')->select();
            Cache::set('links',$data_links,3600);
        }
        $this->assign('links', $data_links);//输出友情链接
        $template = $this->receive('index');//主题目录
        $this->assign('pageUrl', $this->getpage());//确定是哪个页面
        if(Request::instance()->isMobile() && is_file(APP_PATH.'../public/'.$template.'/mobile/index.html'))
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/index.html');
        }
        else
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/index.html');
        }
        return $htmls;
    }
    public function article($id = 0)
    {
        if(empty($id) || $id == 'all')
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
            $param = '';
            Hook::add('view_post',$this->plugins);
            Hook::listen('view_post',$param);
            $type = '0,2,3,4,5,6,7,8';
            if(Request::instance()->has('type','get'))
            {
                $tmpType = Request::instance()->get('type');
                Hook::add('get_type',$this->plugins);
                Hook::listen('get_type',$tmpType);
                $type = $tmpType;
            }
            $order = [
                'name' => 'post_modified',
                'way' => 'desc'
            ];
            Hook::add('order_article',$this->plugins);
            Hook::listen('order_article',$order);
            //显示文章列表
            $page = 1;
            if(Request::instance()->has('page','get'))
            {
                $page = Request::instance()->get('page');
            }
            $data = Cache::get('article'.$type.$page);
            if($data == false)
            {
                $data = Db::view('posts','id,post_keywords as guanjianzi,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                    ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                    ->where('post_status','=',1)
                    ->where('post_type','in',$type)
                    ->where('status','=',1)
                    ->where('post_date','<= time',date('Y-m-d H:i:s'))
                    ->order($order['name'].' '.$order['way'])
                    ->paginate($this->everyPageShows,false,[
                        'query' => [
                            'type' => $type
                        ]
                    ]);
                Cache::set('article'.$page,$data,3600);
            }
            $pages = $data->render();
            $pageArr = $data->toArray();
            $data = $this->addLargerPicture($this->addArticleHref($pageArr['data']));
            $pluginName = '';
            if(Request::instance()->has('type','get'))
            {
                $pluginName = Request::instance()->get('type');
            }
            $this->assign('plugin_name', $pluginName);
            $data['lang'] = $this->lang;
            $data['page'] = $page;
            $data['pluginName'] = $pluginName;
            Hook::add('filter_articleList',$this->plugins);
            Hook::listen('filter_articleList',$data);
            unset($data['lang']);
            unset($data['page']);
            unset($data['pluginName']);
            $this->assign('fenlei', $data);
            $this->assign('pages', $pages);
            $this->assign('daohang1', Lang::get('Article list'));
            $template = $this->receive();//主题目录
            $this->assign('pageUrl', $this->getpage());//确定是哪个页面
            $param = [
                'type' => '',
                'template' => ''
            ];
            if(Request::instance()->has('type','get'))
            {
                $tmpType = Request::instance()->get('type');
                Hook::add('get_type',$this->plugins);
                Hook::listen('get_type',$tmpType);
                $param['type'] = $tmpType;
                Hook::add('category_template',$this->plugins);
                Hook::listen('category_template',$param);
            }
            if(Request::instance()->isMobile() && is_file(APP_PATH.'../public/'.$template.'/mobile/category'.$param['template'].'.html'))
            {
                $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/category'.$param['template'].'.html');
            }
            else
            {
                $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/category'.$param['template'].'.html');
            }
            return $htmls;
        }
        else
        {
            if(!is_int($id))
            {
                Hook::add('alias_article',$this->plugins);
                Hook::listen('alias_article',$id);
            }
            //点击加一
            Db::name('posts')
                ->where('id', $id)
                ->setInc('post_hits');
            //文章内容
            $noArticle = false;
            $data = Db::view('posts','id,post_keywords as guanjianzi,post_source as laiyuan,post_content as zhengwen,post_title as biaoti,post_excerpt as zhaiyao,comment_status,post_modified as fabushijian,post_type,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('posts.id',$id)
                ->where('status','=',1)
                ->find();
            $param = [
                'type' => '',
                'pluginName' => ''
            ];
            if(!empty($data))
            {
                if(isset($this->options_spare['timeFormat']) && !empty($this->options_spare['timeFormat']) && isset($data['fabushijian']))
                {
                    $data['fabushijian'] = date($this->options_spare['timeFormat'],strtotime($data['fabushijian']));
                }
                $param['type'] = $data['post_type'];
                Hook::add('plugin_name',$this->plugins);
                Hook::listen('plugin_name',$param);
                if((!isset($this->options_spare['datu']) || $this->options_spare['datu'] != 1) && isset($data['suolvetu']) && !empty($data['suolvetu']))
                {
                    $tuArr = explode('/',$data['suolvetu']);
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
                        $data['datu'] = $datu;
                    }
                }
                if($data['post_type'] == 3)
                {
                    $tuwn = trim(strip_tags($data['zhengwen'],'<img>'));
                    $tuwnArr = explode('<img',$tuwn);
                    $qianyan = $tuwnArr[0];
                    $xctu = [];
                    foreach($tuwnArr as $key => $val)
                    {
                        if($key > 0)
                        {
                            $tmptu = explode('/>',$val);
                            preg_match('/ src="(.*?)"/i',str_replace("'",'"',$tmptu[0]),$tusrc);
                            $xctu[] = [
                                'href' => $tusrc[1],
                                'shuoming' => trim($tmptu[1])
                            ];
                        }
                    }
                    $data['xiangce'] = [
                        'qianyan' => $qianyan,
                        'tu' => $xctu
                    ];
                }
                if($data['post_type'] == 8)
                {
                    $tmpArr = explode('<p>',$data['zhengwen']);
                    foreach($tmpArr as $key => $val)
                    {
                        if(stripos($val,'</p>') !== false)
                        {
                            $tmpArr[$key] = '<p>' . $val;
                        }
                    }
                    $tmpStr = '';
                    $fenyeArr[] = '';
                    foreach($tmpArr as $key => $val)
                    {
                        $tmpStr .= $val;
                        if(strlen($tmpStr) >= 3000)
                        {
                            $fenyeArr[] = $tmpStr;
                            $tmpStr = '';
                        }
                    }
                    if(!empty($tmpStr))
                    {
                        $fenyeArr[] = $tmpStr;
                    }
                    unset($fenyeArr[0]);
                    $data['fenye'] = $fenyeArr;
                }
                $data['lang'] = $this->lang;
                $data['pluginName'] = $param['pluginName'];
                Hook::add('filter_article',$this->plugins);
                Hook::listen('filter_article',$data);
                unset($data['pluginName']);
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
            }
            else
            {
                $this->assign('neirong', null);
                $noArticle = true;
            }
            $this->assign('plugin_name', $param['pluginName']);
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
            $xh = 0;
            $post_type = 0;
            if(isset($data['id']))
            {
                $xh = $data['id'];
            }
            if(isset($data['post_type']))
            {
                $post_type = $data['post_type'];
            }
            $this->params = [
                'id' => $xh,
                'title' => $data['biaoti'],
                'post_type' =>$post_type
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
            $param = '';
            Hook::add('view_post',$this->plugins);
            Hook::listen('view_post',$param);
            //前后内容
            $previous = Db::name('posts')->where('id','<',$id)->where('post_type',$post_type)->where('post_date','<= time',date('Y-m-d H:i:s'))->field('id,post_title as biaoti')->order('id desc')->find();
            if(!empty($previous))
            {
                $previous['href'] = Url::build('/article/'.$previous['id']);
                Hook::add('url_article_previous',$this->plugins);
                Hook::listen('url_article_previous',$previous['href']);
                $previous['lang'] = $this->lang;
                Hook::add('filter_prevArticle',$this->plugins);
                Hook::listen('filter_prevArticle',$previous);
                unset($previous['lang']);
            }
            $this->assign('previous', $previous);
            $next = Db::name('posts')->where('id','>',$id)->where('post_type',$post_type)->where('post_date','<= time',date('Y-m-d H:i:s'))->field('id,post_title as biaoti')->order('id')->find();
            if(!empty($next))
            {
                $next['href'] = Url::build('/article/'.$next['id']);
                Hook::add('url_article_next',$this->plugins);
                Hook::listen('url_article_next',$next['href']);
                $next['lang'] = $this->lang;
                Hook::add('filter_nextArticle',$this->plugins);
                Hook::listen('filter_nextArticle',$next);
                unset($next['lang']);
            }
            $this->assign('next', $next);
            if(isset($data['comment_status']) && $data['comment_status'] == 1)
            {
                //评论内容
                $pinglun = Db::view('comments','id,createtime as shijian,content as neirong')
                    ->view('users','user_login,user_nicename as nicheng,user_email as email,user_url as url,avatar as touxiang,signature as qianming','users.id=comments.uid')
                    ->where('comments.post_id','=',$id)
                    ->where('comments.status','=',1)
                    ->order('comments.createtime desc')
                    ->paginate($this->everyPageShows);
                $pages = $pinglun->render();
                $pinglun = $pinglun->toArray();
                $this->assign('pinglun', $pinglun['data']);
                $this->assign('pages', $pages);
            }
            $yunxupinglun = 0;
            if(isset($data['comment_status']) && $data['comment_status'] == 1 && $this->notAllowLogin != 1)
            {
                $yunxupinglun = 1;
            }
            $this->assign('yunxupinglun', $yunxupinglun);//是否允许评论
            $template = $this->receive();
            $guanjianzi = '';
            if(isset($data['guanjianzi']))
            {
                $guanjianzi = $data['guanjianzi'];
            }
            $this->assign('keyword', $guanjianzi);
            $zhaiyao = '';
            if(isset($data['zhaiyao']))
            {
                $zhaiyao = $data['zhaiyao'];
            }
            $this->assign('description', $zhaiyao);
            $this->assign('pageUrl', $this->getpage());//确定是哪个页面
            $templateFile = 'article';
            $param = [
                'type' => '',
                'template' => ''
            ];
            if($noArticle == false)
            {
                $param['type'] = $data['post_type'];
                Hook::add('article_template',$this->plugins);
                Hook::listen('article_template',$param);
                switch($data['post_type'])
                {
                    case 2:
                        $templateFile = 'log';
                        break;
                    case 3:
                        $templateFile = 'album';
                        break;
                    case 4:
                        $templateFile = 'video';
                        break;
                    case 5:
                        $templateFile = 'audio';
                        break;
                    case 6:
                        $templateFile = 'link';
                        break;
                    case 7:
                        $templateFile = 'notice';
                        break;
                    case 8:
                        $templateFile = 'paging';
                        break;
                    default:
                        break;
                }
            }
            if(Request::instance()->isMobile() && (is_file(APP_PATH.'../public/'.$template.'/mobile/article'.$param['template'].'.html') || is_file(APP_PATH.'../public/'.$template.'/mobile/'.$templateFile.$param['template'].'.html')))
            {
                if(is_file(APP_PATH.'../public/'.$template.'/mobile/'.$templateFile.$param['template'].'.html'))
                {
                    $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/'.$templateFile.$param['template'].'.html');
                }
                else
                {
                    $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/article'.$param['template'].'.html');
                }
            }
            else
            {
                if(is_file(APP_PATH.'../public/'.$template.'/'.$templateFile.$param['template'].'.html'))
                {
                    $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/'.$templateFile.$param['template'].'.html');
                }
                else
                {
                    $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/article'.$param['template'].'.html');
                }
            }
            return $htmls;
        }
    }
    //评论
    public function pinglun()
    {
        $beipinglunren = Db::name('posts')->where('id',Request::instance()->post('id'))->field('post_author')->find();
        if($beipinglunren['post_author'] != Session::get($this->session_prefix.'user_id'))
        {
            $comment = Db::name('options')->where('option_name','comment')->field('option_value')->find();
            $plzt = 1;
            if($comment['option_value'] == 1)
            {
                $plzt = 0;
            }
            //添加评论
            $data = [
                'post_id' => Request::instance()->post('id'),
                'url' => 'index/Index/article/id/'.Request::instance()->post('id'),
                'uid' => Session::get($this->session_prefix.'user_id'),
                'to_uid' => $beipinglunren['post_author'],
                'createtime' => date("Y-m-d H:i:s"),
                'content' => $this->filterJs(Request::instance()->post('pinglun')),
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
            $param = '';
            Hook::add('comment_post',$this->plugins);
            Hook::listen('comment_post',$param);
        }
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
        $param = '';
        Hook::add('view_post',$this->plugins);
        Hook::listen('view_post',$param);
        if(!is_int($id))
        {
            Hook::add('alias_category',$this->plugins);
            Hook::listen('alias_category',$id);
        }
        //文章分类
        $fenleiming = Db::name('terms')->where('id',$id)->field('id,term_name')->find();
        $fenleiming['lang'] = $this->lang;
        Hook::add('filter_categoryName',$this->plugins);
        Hook::listen('filter_categoryName',$fenleiming);
        unset($fenleiming['lang']);
        $type = '';
        $categoryType = Cache::get('category_type'.$id);
        if($categoryType == false)
        {
            $tmpartid = Db::name('term_relationships')->where('term_id',$id)->field('object_id')->order('tid desc')->limit(1)->find();
            $categoryType = Db::name('posts')->where('id',$tmpartid['object_id'])->field('post_type')->limit(1)->find();
            $categoryType = $categoryType['post_type'];
            Cache::set('category_type'.$id,$categoryType,3600);
        }
        $param = [
            'type' => '',
            'pluginName' => ''
        ];
        if(!empty($categoryType))
        {
            $type = ','.$categoryType;
            $param['type'] = $categoryType;
            Hook::add('plugin_name',$this->plugins);
            Hook::listen('plugin_name',$param);
        }
        $this->assign('plugin_name', $param['pluginName']);
        $order = [
            'name' => 'post_modified',
            'way' => 'desc'
        ];
        Hook::add('order_category',$this->plugins);
        Hook::listen('order_category',$order);
        $flm = '';
        if(isset($fenleiming['term_name']))
        {
            $flm = $fenleiming['term_name'];
        }
        $this->assign('daohang1', $flm);//获取分类名
        $page = 1;
        if(Request::instance()->has('page','get'))
        {
            $page = Request::instance()->get('page');
        }
        $data = Cache::get('category'.$id.$page);
        if($data == false)
        {
            $data = Db::view('term_relationships','term_id')
                ->view('posts','id,post_keywords as guanjianzi,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,post_type as type,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan','posts.id=term_relationships.object_id')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('term_id','=',$id)
                ->where('post_status','=',1)
                ->where('post_type','in','0,2,3,4,5,6,7,8'.$type)
                ->where('status','=',1)
                ->where('post_date','<= time',date('Y-m-d H:i:s'))
                ->order('istop desc,'.$order['name'].' '.$order['way'])
                ->paginate($this->everyPageShows);
            Cache::set('category'.$id.$page,$data,3600);
        }
        $pages = $data->render();
        $pageArr = $data->toArray();
        $data = $this->addLargerPicture($this->addArticleHref($pageArr['data']));
        $data['lang'] = $this->lang;
        $data['page'] = $page;
        $data['id'] = $id;
        $data['pluginName'] = $param['pluginName'];
        Hook::add('filter_category',$this->plugins);
        Hook::listen('filter_category',$data);
        unset($data['lang']);
        unset($data['page']);
        unset($data['id']);
        unset($data['pluginName']);
        $this->assign('fenlei', $data);
        $this->assign('pages', $pages);
        $template = $this->receive();//主题目录
        $this->assign('pageUrl', $this->getpage());//确定是哪个页面
        $param = [
            'type' => $categoryType,
            'template' => ''
        ];
        Hook::add('category_template',$this->plugins);
        Hook::listen('category_template',$param);
        if(Request::instance()->isMobile() && is_file(APP_PATH.'../public/'.$template.'/mobile/category'.$param['template'].'.html'))
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/category'.$param['template'].'.html');
        }
        else
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/category'.$param['template'].'.html');
        }
        return $htmls;
    }
    public function page($id)
    {
        if(!is_int($id))
        {
            Hook::add('alias_page',$this->plugins);
            Hook::listen('alias_page',$id);
        }
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
        $this->slide();
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
        $param = '';
        Hook::add('view_post',$this->plugins);
        Hook::listen('view_post',$param);
        $template = $this->receive('page');//主题目录
        $this->assign('keyword', $data['guanjianzi']);
        $this->assign('description', $data['zhaiyao']);
        $this->assign('pageUrl', $this->getpage());
        if(Request::instance()->isMobile() && is_file(APP_PATH.'../public/'.$template.'/mobile/page/'.$data['template']))
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/page/'.$data['template']);
        }
        else
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/page/'.$data['template']);
        }
        return $htmls;
    }
    public function search($word='')
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
        $param = '';
        Hook::add('view_post',$this->plugins);
        Hook::listen('view_post',$param);
        //搜索
        $type = '0,2,3,4,5,6,7,8';
        if(Request::instance()->has('type','get'))
        {
            $tmpType = Request::instance()->get('type');
            Hook::add('get_type',$this->plugins);
            Hook::listen('get_type',$tmpType);
            $type = $tmpType;
        }
        $isDate = false;
        $search_key = trim(Request::instance()->get('keyword'));
        if(substr($search_key,0,4) == 'date')
        {
            $search_key = substr($search_key,4);
            $search_key = trim(str_replace([':','：'],'',$search_key));
            if(Validate::dateFormat($search_key,'Y-m-d') || Validate::dateFormat($search_key,'Y-m'))
            {
                $isDate = true;
            }
        }
        $search = [
            'lang' => $this->lang,
            'key' => Request::instance()->get('keyword'),
            'ids' => '',
            'isDate' => $isDate
        ];
        Hook::add('search',$this->plugins);
        Hook::listen('search',$search);
        $order = [
            'name' => 'post_modified',
            'way' => 'desc'
        ];
        Hook::add('order_search',$this->plugins);
        Hook::listen('order_search',$order);
        if($isDate == true)
        {
            $search_key_start = $search_key;
            $search_key_end = $search_key;
            if(Validate::dateFormat($search_key,'Y-m'))
            {
                $search_key_start = $search_key . '-01';
                $search_key_end = $search_key . '-31';
            }
            $search_key_start .= ' 00:00:00';
            $search_key_end .= ' 23:59:59';
            $data = Db::view('posts','id,post_keywords as guanjianzi,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('post_status','=',1)
                ->where('post_type','in',$type)
                ->where('status','=',1)
                ->where('post_date','<= time',date('Y-m-d H:i:s'))
                ->where('post_modified', 'between time', [$search_key_start, $search_key_end])
                ->order($order['name'].' '.$order['way'])
                ->paginate($this->everyPageShows,false,[
                    'query' => [
                        'keyword' => Request::instance()->get('keyword'),
                        'type' => $type
                    ]
                ]);
        }
        else
        {
            $data = Db::view('posts','id,post_keywords as guanjianzi,post_title as biaoti,post_excerpt as zhaiyao,post_modified as fabushijian,comment_count,thumbnail as suolvetu,post_hits as yuedu,post_like as zan')
                ->view('users','user_login,user_nicename as nicheng','users.id=posts.post_author')
                ->where('post_status','=',1)
                ->where('post_type','in',$type)
                ->where('status','=',1)
                ->where('post_date','<= time',date('Y-m-d H:i:s'))
                ->where('post_keywords|post_title|post_excerpt','like','%'.Request::instance()->get('keyword').'%')
                ->whereOr('id','in',$search['ids'])
                ->order($order['name'].' '.$order['way'])
                ->paginate($this->everyPageShows,false,[
                    'query' => [
                        'keyword' => Request::instance()->get('keyword'),
                        'type' => $type
                    ]
                ]);
        }
        $pages = $data->render();
        $pageArr = $data->toArray();
        $data = $this->addLargerPicture($this->addArticleHref($pageArr['data']));
        if(count($data) == 0)
        {
            $this->assign('sousuo', Lang::get('No search found'));
        }
        $pluginName = '';
        if(Request::instance()->has('type','get'))
        {
            $pluginName = Request::instance()->get('type');
        }
        $this->assign('plugin_name', $pluginName);
        $data['lang'] = $this->lang;
        $data['pluginName'] = $pluginName;
        Hook::add('filter_search',$this->plugins);
        Hook::listen('filter_search',$data);
        unset($data['lang']);
        unset($data['pluginName']);
        $this->assign('fenlei', $data);
        $this->assign('pages', $pages);
        $this->assign('daohang1', Lang::get('Search'));
        $template = $this->receive();//主题目录
        $this->assign('pageUrl', $this->getpage());//确定是哪个页面
        $param = [
            'type' => '',
            'template' => ''
        ];
        if(Request::instance()->has('type','get'))
        {
            $tmpType = Request::instance()->get('type');
            Hook::add('get_type',$this->plugins);
            Hook::listen('get_type',$tmpType);
            $param['type'] = $tmpType;
            Hook::add('category_template',$this->plugins);
            Hook::listen('category_template',$param);
        }
        if(Request::instance()->isMobile() && is_file(APP_PATH.'../public/'.$template.'/mobile/category'.$param['template'].'.html'))
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/mobile/category'.$param['template'].'.html');
        }
        else
        {
            $htmls = $this->fetch(APP_PATH.'../public/'.$template.'/category'.$param['template'].'.html');
        }
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
            $phpSelf = Url::build('/index');
            Hook::add('url_menu',$this->plugins);
            Hook::listen('url_menu',$phpSelf);
            return $phpSelf;
        }
        else
        {
            $phpSelf = str_replace('/index.php','',$_SERVER['PHP_SELF']);
            Hook::add('url_menu',$this->plugins);
            Hook::listen('url_menu',$phpSelf);
            return $phpSelf;
        }
    }
    public function sitemap()
    {
        $domain = Cache::get('domain');
        if($domain == false)
        {
            $domain = Db::name('options')->where('option_name','domain')->field('option_value')->find();
            $domain = $domain['option_value'];
            Cache::set('domain',$domain,3600);
        }
        $root = '';
        $dm = Url::build('/');
        if(strpos($dm,'/index.php') !== false)
        {
            $root = 'index.php/';
        }
        $domain = rtrim(trim($domain . $root),'/');
        $sm = Db::name('posts')->where('post_status','=',1)->where('post_type',['=',0],['=',2],['=',3],['=',4],['=',5],['=',6],['=',7],['=',8],'or')->where('status','=',1)->where('post_date','<= time',date('Y-m-d H:i:s'))->field('id,post_modified')->order('post_modified desc')->limit(50000)->select();
        if(!empty($sm))
        {
            foreach($sm as $key => $val)
            {
                $sm[$key]['post_modified'] = date('Y-m-d',strtotime($val['post_modified']));
                $sm[$key]['href'] = '/article/' . $val['id'] . '.html';
            }
        }
        Hook::add('filter_sitemap',$this->plugins);
        Hook::listen('filter_sitemap',$sm);
        $str = '<?xml version="1.0" encoding="UTF-8"?>';
        $str .= '<urlset>';
        foreach($sm as $key => $val)
        {
            $str .= '<url>';
            $str .= '<loc>' . $domain . $val['href'] . '</loc>';
            $str .= '<lastmod>' . $val['post_modified'] . '</lastmod>';
            $str .= '<changefreq>daily</changefreq>';
            $str .= '<priority>1.0</priority>';
            $str .= '</url>';
        }
        $str .= '</urlset>';
        file_put_contents(APP_PATH . '../sitemap.xml',$str);
        header("Content-type: text/xml");
        echo $str;
    }
}
