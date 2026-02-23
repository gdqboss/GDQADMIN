<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：飞鲸(泉州)网络科技有限公司
 * 授权域名：www.ottz.cn
 * 授权码：ZnNpaTeHHFnuyMxIYcI
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

//custom_file(yx_queue_free)
namespace app\controller\yingxiao;
use app\controller\ApiAdmin;
use think\facade\Db;

class ApiAdminQueueFree extends ApiAdmin
{
    public function index(){
        $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();

        $where = [];
        $where[] = ['queue_free.aid','=',aid];
        if(bid == 0){
            if($set['queue_type_business'] != 1){
                $where[] = ['queue_free.bid','=',bid];
            }
        }else{
            $where[] = ['queue_free.bid','=',bid];
        }
        $pernum = 20;
        $pagenum = input('post.pagenum');
        $status = input('post.status');
        $time = time();
        if($status == 0){
            $where[] = ['queue_free.status','=',$status];
        }elseif($status == 1){
            $where[] = ['queue_free.status','=',$status];
        }
        $where[] = ['quit_queue','=',0];
        if(input('param.keyword')){
            $where[] = ['m.nickname|m.tel|queue_free.mid|queue_free.queue_no','like','%'.trim(input('param.keyword')).'%'];
        }
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('queue_free')->alias('queue_free')
            ->join('member m','m.id = queue_free.mid')
            ->field('m.nickname,m.tel,queue_free.*')
            ->where($where)->page($pagenum,$pernum)->order('queue_free.id desc')->select()->toArray();

        foreach($datalist as &$v) {
            if ($v['status'] == 0) {
                $v['statusLabel'] = '排队中';
            } elseif ($v['status'] == 1) {
                $v['statusLabel'] = '已完成';
            }
            if($set['queue_type_business'] != 1 && $v['bid']>0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
                $v['queue_noLabel'] = 'S'.$v['queue_no'];
            }else{
                $v['bname'] = Db::name('admin_set')->where('aid',aid)->value('name');
                $v['queue_noLabel'] = 'P'.$v['queue_no'];
            }
            if($v['bid']>0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
            }else{
                $v['bname'] = Db::name('admin_set')->where('aid',aid)->value('name');
            }
            $v['createtimeFormat'] = date('Y-m-d H:i:s',$v['createtime']);
            $member = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->field('nickname,tel')->find();
            $v['nickname'] =  $member['nickname'];
            $v['tel'] =  $member['tel'];
            $show_changeno = 0;
            $v['show_changeno'] =  $show_changeno;
        }
        $rdata = [];
        $rdata['status']   = 1;
        $rdata['datalist'] = $datalist;
        $set = [];
        $rdata['set'] = $set;
        return $this->json($rdata);
    }
    //退出排队
    public function quit_queue(){
        $id = input('param.id');
        Db::name('queue_free')->where('id',$id)->where('aid',aid)->update(['quit_queue' =>1]);
        \app\common\System::plog('排队免单退出，ID:'.$id);
        return  $this->json(['status'=>1,'msg'=>'退出成功']);
    }
    //更改排队号 
    public function changeno(){
        }
    public function editmoney(){
        }
    public function getQueueFreeSet(){
        $activity_time_custom = getcustom('yx_queue_free_activity_time');
        }
    public function saveQueueFreeSet(){
        $activity_time_custom = getcustom('yx_queue_free_activity_time');
        }
}