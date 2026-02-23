<?php
// JK客户定制


//custom_file(douyin_groupbuy)
// +----------------------------------------------------------------------
// | 抖音- 团购设置
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class DouyinGroupbuySet extends Common
{
    public function initialize(){
        parent::initialize();
    }
    public function index(){
        $info = Db::name('douyin_groupbuy_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$info){
            $inserid = Db::name('douyin_groupbuy_set')->insertGetId(['aid'=>aid,'bid'=>bid]);
            if(!$inserid) showmsg('数据添加出错');
            $info = ['id'=>$inserid,'aid'=>aid,'bid'=>bid];
        }
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        $info['updatetime'] = time();
        //查询是否添加过
        $old = Db::name('douyin_groupbuy_set')->where('client_key',$info['client_key'])->field('id,aid,bid')->find();
        if($old && $old['id'] !=$info['id'] && $old['aid'] != aid){
            return json(['status'=>0,'msg'=>'此client_key已有用户添加过']);
        }
        //查询是否添加过
        $old1 = Db::name('douyin_groupbuy_set')->where('account_id',$info['account_id'])->field('id')->find();
        // if($old1 && $old1['id'] !=$info['id']){
        //     return json(['status'=>0,'msg'=>'此账户已有商户添加过']);
        // }
        // $old2 = Db::name('douyin_groupbuy_set')->where('poi_id',$info['poi_id'])->field('id')->find();
        // if($old2 && $old2['id'] !=$info['id']){
        //     return json(['status'=>0,'msg'=>'此门店已有商户添加过']);
        // }

        $up = Db::name('douyin_groupbuy_set')->where('id',$info['id'])->where('aid',aid)->where('bid',bid)->update($info);
        if(!$up){
            return json(['status'=>0,'msg'=>'保存失败']);
        }
        \app\common\System::plog('抖音团购设置');
        return json(['status'=>1,'msg'=>'保存成功']);
    }
}