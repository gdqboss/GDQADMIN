<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 图标设定
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class DesignerIcon extends Common
{
    public function index(){
        $type = input('param.type') ? input('param.type') : $this->platform[0];

        $pages_list = [];
        $pages = Db::name('designerpage')->where('aid',aid)->where('bid',bid)->select()->toArray();
        foreach($pages as $pv){
            $content = $pv['content'] && json_decode($pv['content'],true) ? json_decode($pv['content'],true) : [];
            if(!$content || !is_array($content)) continue;
            foreach($content as $mod){
                $modid = isset($mod['id'])?$mod['id']:'';
                $modtemp = isset($mod['temp'])?$mod['temp']:'';
                if(isset($mod['data']) && is_array($mod['data'])){
                    foreach($mod['data'] as $idx=>$item){
                        $text = isset($item['text'])?$item['text']:'';
                        $iconKey = '';$iconUrl = '';$linkKey = '';$linkUrl='';
                        if(isset($item['iconPath'])){$iconKey='iconPath';$iconUrl=$item['iconPath'];}
                        if(isset($item['selectedIconPath'])){$iconKey=$iconKey?:'selectedIconPath';}
                        if(isset($item['imgurl'])){$iconKey=$iconKey?:'imgurl';$iconUrl=$item['imgurl'];}
                        if(isset($item['logo_icon'])){$iconKey=$iconKey?:'logo_icon';$iconUrl=$item['logo_icon'];}
                        if(isset($item['hrefurl'])){$linkKey='hrefurl';$linkUrl=$item['hrefurl'];}
                        if(isset($item['pagePath'])){$linkKey=$linkKey?:'pagePath';$linkUrl=$item['pagePath'];}
                        if($iconKey){
                            $pages_list[] = [
                                'page_id'=>$pv['id'],
                                'page_name'=>$pv['name'],
                                'module_id'=>$modid,
                                'module_temp'=>$modtemp,
                                'item_index'=>$idx,
                                'text'=>$text,
                                'iconKey'=>$iconKey,
                                'iconUrl'=>$iconUrl,
                                'linkKey'=>$linkKey,
                                'linkUrl'=>$linkUrl,
                            ];
                        }
                    }
                }
                if(isset($mod['params']) && is_array($mod['params'])){
                    $iconKey = '';$iconUrl='';
                    if(isset($mod['params']['logo_icon'])){$iconKey='logo_icon';$iconUrl=$mod['params']['logo_icon'];}
                    if($iconKey){
                        $pages_list[] = [
                            'page_id'=>$pv['id'],
                            'page_name'=>$pv['name'],
                            'module_id'=>$modid,
                            'module_temp'=>$modtemp,
                            'item_index'=>-1,
                            'text'=>'',
                            'iconKey'=>$iconKey,
                            'iconUrl'=>$iconUrl,
                            'linkKey'=>'',
                            'linkUrl'=>'',
                        ];
                    }
                }
            }
        }

        $mobile_icons_list = [];
        $mobile_info = Db::name('designer_mobile')->where('aid',aid)->find();
        if(!$mobile_info){
            $admin_icon_defaults = [
                // 基础功能图标
                'saoyisao'    => PRE_URL.'/static/img/admin/saoyisao.png',
                'setup'       => PRE_URL.'/static/img/admin/setup.png',
                'jieshiicon'  => PRE_URL.'/static/img/admin/jieshiicon.png',
                'titletips'   => PRE_URL.'/static/img/admin/titletips.png',
                'jiantou'     => PRE_URL.'/static/img/admin/jiantou.png',
                'right_black' => PRE_URL.'/static/img/location/right-black.png',
                
                // 菜单图标
                'menu1'       => PRE_URL.'/static/img/admin/menu1.png',
                'menu2'       => PRE_URL.'/static/img/admin/menu2.png',
                'menu3'       => PRE_URL.'/static/img/admin/menu3.png',
                'menu4'       => PRE_URL.'/static/img/admin/menu4.png',
                'menu5'       => PRE_URL.'/static/img/admin/menu5.png',
                
                // 底部导航图标
                'member'      => PRE_URL.'/static/img/admin/member.png',
                'member2'     => PRE_URL.'/static/img/admin/member2.png',
                'zixun'       => PRE_URL.'/static/img/admin/zixun.png',
                'zixun2'      => PRE_URL.'/static/img/admin/zixun2.png',
                'finance'     => PRE_URL.'/static/img/admin/finance.png',
                'finance2'    => PRE_URL.'/static/img/admin/finance2.png',
                'my'          => PRE_URL.'/static/img/admin/my.png',
                'my2'         => PRE_URL.'/static/img/admin/my2.png',
                
                // 订单状态图标
                'order1'      => PRE_URL.'/static/img/admin/order1.png',
                'order2'      => PRE_URL.'/static/img/admin/order2.png',
                'order3'      => PRE_URL.'/static/img/admin/order3.png',
                'order4'      => PRE_URL.'/static/img/admin/order4.png',
                
                // 财务相关图标
                'financenbg1' => PRE_URL.'/static/img/admin/financenbg1.png',
                'financenbg2' => PRE_URL.'/static/img/admin/financenbg2.png',
                'financenbg3' => PRE_URL.'/static/img/admin/financenbg3.png',
                'financenbg4' => PRE_URL.'/static/img/admin/financenbg4.png',
                'financenbg5' => PRE_URL.'/static/img/admin/financenbg5.png',
                'financenbg6' => PRE_URL.'/static/img/admin/financenbg6.png',
                'financenbg7' => PRE_URL.'/static/img/admin/financenbg7.png',
                'financenbg8' => PRE_URL.'/static/img/admin/financenbg8.png',
                'financenbg9' => PRE_URL.'/static/img/admin/financenbg9.png',
                'financejiantou' => PRE_URL.'/static/img/admin/financejiantou.png',
                
                // 门店餐厅图标
                'fork'        => PRE_URL.'/static/img/admin/fork.png',
                'dish'        => PRE_URL.'/static/img/admin/dish.png',
                'money'       => PRE_URL.'/static/img/admin/money.png',
                'change'      => PRE_URL.'/static/img/admin/change.png',
                'clean'       => PRE_URL.'/static/img/admin/clean.png',
                'close'       => PRE_URL.'/static/img/admin/close.png',
                'start'       => PRE_URL.'/static/img/admin/start.png',
                'pause'       => PRE_URL.'/static/img/admin/pause.png',
                
                // 分享与海报图标
                'share'       => PRE_URL.'/static/img/admin/share.png',
                'share_poster' => PRE_URL.'/static/img/admin/share_poster.png',
                'share_wechat' => PRE_URL.'/static/img/admin/share_wechat.png',
                'share_friends' => PRE_URL.'/static/img/admin/share_friends.png',
                'share_qq'    => PRE_URL.'/static/img/admin/share_qq.png',
                'share_weibo' => PRE_URL.'/static/img/admin/share_weibo.png',
                'poster_generate' => PRE_URL.'/static/img/admin/poster_generate.png',
                'poster_save' => PRE_URL.'/static/img/admin/poster_save.png',
                'share_link'  => PRE_URL.'/static/img/admin/share_link.png',
                
                // 其他功能图标
                'wm1'         => PRE_URL.'/static/img/admin/wm1.png',
                'wm2'         => PRE_URL.'/static/img/admin/wm2.png',
                'wm5'         => PRE_URL.'/static/img/admin/wm5.png',
                'wm7'         => PRE_URL.'/static/img/admin/wm7.png',
                'dismendian'  => PRE_URL.'/static/img/admin/dismendian.png',
                'dishm6'      => PRE_URL.'/static/img/admin/dishm6.png',
                'dishm8'      => PRE_URL.'/static/img/admin/dishm8.png',
                'goback'      => PRE_URL.'/static/img/admin/goback.png',
            ];
            $init_data = ['bgimg' => PRE_URL.'/static/img/admin/headbgimg.png'];
            foreach($admin_icon_defaults as $k=>$v){ $init_data[$k] = $v; }
            $mobile_info = array(
                'aid'=>aid,
                'updatetime'=>time(),
                'data'=>jsonEncode($init_data)
            );
            $id = Db::name('designer_mobile')->insertGetId($mobile_info);
            $mobile_info['id'] = $id;
        }
        $mobile_data = [];
        if($mobile_info && isset($mobile_info['data'])){
            $tmp = json_decode($mobile_info['data'], true);
            if(is_array($tmp)) $mobile_data = $tmp;
        }
        $admin_icon_defaults = [
            // 基础功能图标
            'saoyisao'    => PRE_URL.'/static/img/admin/saoyisao.png',
            'setup'       => PRE_URL.'/static/img/admin/setup.png',
            'jieshiicon'  => PRE_URL.'/static/img/admin/jieshiicon.png',
            'titletips'   => PRE_URL.'/static/img/admin/titletips.png',
            'jiantou'     => PRE_URL.'/static/img/admin/jiantou.png',
            'right_black' => PRE_URL.'/static/img/location/right-black.png',
            'financejiantou' => PRE_URL.'/static/img/admin/financejiantou.png',
            
            // 菜单图标
            'menu1'       => PRE_URL.'/static/img/admin/menu1.png',
            'menu2'       => PRE_URL.'/static/img/admin/menu2.png',
            'menu3'       => PRE_URL.'/static/img/admin/menu3.png',
            'menu4'       => PRE_URL.'/static/img/admin/menu4.png',
            'menu5'       => PRE_URL.'/static/img/admin/menu5.png',
            
            // 底部导航图标
            'member'      => PRE_URL.'/static/img/admin/member.png',
            'member2'     => PRE_URL.'/static/img/admin/member2.png',
            'zixun'       => PRE_URL.'/static/img/admin/zixun.png',
            'zixun2'      => PRE_URL.'/static/img/admin/zixun2.png',
            'finance'     => PRE_URL.'/static/img/admin/finance.png',
            'finance2'    => PRE_URL.'/static/img/admin/finance2.png',
            'my'          => PRE_URL.'/static/img/admin/my.png',
            'my2'         => PRE_URL.'/static/img/admin/my2.png',
            
            // 财务相关图标
            'financenbg1' => PRE_URL.'/static/img/admin/financenbg1.png',
            'financenbg2' => PRE_URL.'/static/img/admin/financenbg2.png',
            'financenbg3' => PRE_URL.'/static/img/admin/financenbg3.png',
            'financenbg4' => PRE_URL.'/static/img/admin/financenbg4.png',
            'financenbg5' => PRE_URL.'/static/img/admin/financenbg5.png',
            'financenbg6' => PRE_URL.'/static/img/admin/financenbg6.png',
            'financenbg7' => PRE_URL.'/static/img/admin/financenbg7.png',
            'financenbg8' => PRE_URL.'/static/img/admin/financenbg8.png',
            'financenbg9' => PRE_URL.'/static/img/admin/financenbg9.png',
            'financenbg10' => PRE_URL.'/static/img/admin/financenbg10.png',
            
            // 分享与海报图标
            'share'       => PRE_URL.'/static/img/admin/share.png',
            'share_poster' => PRE_URL.'/static/img/admin/share_poster.png',
            'share_wechat' => PRE_URL.'/static/img/admin/share_wechat.png',
            'share_friends' => PRE_URL.'/static/img/admin/share_friends.png',
            'share_qq'    => PRE_URL.'/static/img/admin/share_qq.png',
            'share_weibo' => PRE_URL.'/static/img/admin/share_weibo.png',
            'poster_generate' => PRE_URL.'/static/img/admin/poster_generate.png',
            'poster_save' => PRE_URL.'/static/img/admin/poster_save.png',
            'share_link'  => PRE_URL.'/static/img/admin/share_link.png',
            
            // 其他功能图标
            'wm1'         => PRE_URL.'/static/img/admin/wm1.png',
            'wm2'         => PRE_URL.'/static/img/admin/wm2.png',
            'wm5'         => PRE_URL.'/static/img/admin/wm5.png',
            'wm7'         => PRE_URL.'/static/img/admin/wm7.png',
            'dismendian'  => PRE_URL.'/static/img/admin/dismendian.png',
            'dishm6'      => PRE_URL.'/static/img/admin/dishm6.png',
            'dishm8'      => PRE_URL.'/static/img/admin/dishm8.png',
            'goback'      => PRE_URL.'/static/img/admin/goback.png',
        ];
        $needs_update = false;
        foreach($admin_icon_defaults as $k=>$defurl){
            if(!isset($mobile_data[$k]) || !$mobile_data[$k]){
                $mobile_data[$k] = $defurl;
                $needs_update = true;
            }
            $url = isset($mobile_data[$k]) && $mobile_data[$k] ? $mobile_data[$k] : $defurl;
            $mobile_icons_list[] = [
                'key' => $k,
                'url' => $url,
            ];
        }
        if($needs_update){
            Db::name('designer_mobile')->where('aid',aid)->update([
                'data' => jsonEncode($mobile_data),
                'updatetime' => time(),
            ]);
        }

        View::assign('type',$type);
        View::assign('pages_list_json', jsonEncode($pages_list));
        View::assign('mobile_icons_list_json', jsonEncode($mobile_icons_list));
        return View::fetch();
    }

    public function save(){
        $type = input('param.type') ? input('param.type') : $this->platform[0];
        $payload = input('post.info/a');

        if(isset($payload['mobile_icons_list'])){
            $mobile_info = Db::name('designer_mobile')->where('aid',aid)->find();
            if($mobile_info){
                $mobile_data = json_decode($mobile_info['data'], true);
                foreach($payload['mobile_icons_list'] as $entry){
                    if(isset($entry['key']) && isset($entry['url'])){
                        $mobile_data[$entry['key']] = $entry['url'];
                    }
                }
                Db::name('designer_mobile')->where('aid',aid)->update([
                    'data' => jsonEncode($mobile_data),
                    'updatetime' => time(),
                ]);
            }
        }

        if(isset($payload['pages_list'])){
            $group = [];
            foreach($payload['pages_list'] as $entry){
                $pid = $entry['page_id'];
                if(!isset($group[$pid])) $group[$pid] = [];
                $group[$pid][] = $entry;
            }
            foreach($group as $pid=>$list){
                $page = Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$pid)->find();
                if(!$page) continue;
                $content = $page['content'] && json_decode($page['content'],true) ? json_decode($page['content'],true) : [];
                foreach($list as $e){
                    foreach($content as &$mod){
                        if((isset($mod['id'])?$mod['id']:'') != $e['module_id']) continue;
                        if($e['item_index'] >= 0){
                            if(isset($mod['data'][$e['item_index']])){
                                if(isset($e['iconKey']) && isset($e['iconUrl'])) $mod['data'][$e['item_index']][$e['iconKey']] = $e['iconUrl'];
                                if(isset($e['linkKey']) && isset($e['linkUrl']) && $e['linkKey']) $mod['data'][$e['item_index']][$e['linkKey']] = $e['linkUrl'];
                                if(isset($e['text'])) $mod['data'][$e['item_index']]['text'] = $e['text'];
                            }
                        }else{
                            if(isset($mod['params']) && isset($e['iconKey']) && isset($e['iconUrl'])){
                                $mod['params'][$e['iconKey']] = $e['iconUrl'];
                            }
                        }
                    }
                    unset($mod);
                }
                Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$pid)->update([
                    'content'=>jsonEncode($content),
                    'updatetime'=>time(),
                ]);
            }
        }

        \app\common\System::plog('图标设定保存');
        return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index').'/type/'.$type]);
    }
}
