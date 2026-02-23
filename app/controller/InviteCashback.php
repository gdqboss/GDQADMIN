<?php
// JK客户定制

// custom_file(yx_invite_cashback)
// +----------------------------------------------------------------------
// | 邀请返现
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class InviteCashback extends Common
{
    public function initialize()
    {
        parent::initialize();
        if(bid>0 || !getcustom('yx_invite_cashback')){
            showmsg('无权限操作');
        }
    }
    //列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',0];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('invite_cashback')->where($where)->count();
            $data = Db::name('invite_cashback')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                if($v['starttime'] > time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
                }elseif($v['endtime'] < time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
                }else{
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#5FB878">进行中</button>';
                }
                $data[$k]['starttime'] = date('Y-m-d H:i',$v['starttime']);
                $data[$k]['endtime'] = date('Y-m-d H:i',$v['endtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('invite_cashback')->where('aid',aid)->where('bid',0)->where('id',input('param.id/d'))->find();
            $info['starttime'] = date('Y-m-d H:i:s',$info['starttime']);
            $info['endtime'] = date('Y-m-d H:i:s',$info['endtime']);
        }else{
            $info = array('id'=>'','starttime'=>date('Y-m-d 00:00:00'),'endtime'=>date('Y-m-d 00:00:00',time()+7*86400),'gettj'=>'-1','sort'=>0,'fwtype'=>0,'type'=>1,'tip'=>'满减');
        }
        $info['gettj'] = explode(',',$info['gettj']);
        //推N返一数据
        $info['invite_cashbak_data']  = $info['invite_cashbak_data'] ? json_decode($info['invite_cashbak_data'],true) : [];
        $info['invite_cashbak_count'] = count($info['invite_cashbak_data']);

        View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);

        $categorydata = array();
        if($info && $info['categoryids']){
            $categorydata = Db::name('shop_category')->where('aid',aid)->where('id','in',$info['categoryids'])->order('sort desc,id')->select()->toArray();
        }
        View::assign('categorydata',$categorydata);
        $productdata = array();
        if($info && $info['productids']){
            $productdata = Db::name('shop_product')->where('aid',aid)->where('id','in',$info['productids'])->order(Db::raw('field(id,'.$info['productids'].')'))->select()->toArray();
        }
        View::assign('productdata',$productdata);
        return View::fetch();
    }

    //保存
    public function save(){
        $info = input('post.info/a');
        $info['gettj'] = implode(',',$info['gettj']);
        $info['starttime'] = strtotime($info['starttime']);
        $info['endtime'] = strtotime($info['endtime']);

        //处理邀请返现数据
        $invite_cashbak_data = input('post.invite_cashbak_data/a');
        if($invite_cashbak_data){
            $newdata = [];
            $i = 1;
            foreach($invite_cashbak_data as $ik=>&$iv){
                $iv['ks'] = $i;
                $iv['money']       = round($iv['money'],2);
                $iv['money2']      = round($iv['money2'],2);
                $iv['score']       = intval($iv['score']);
                $iv['score2']      = round($iv['score2'],2);
                $iv['commission']  = round($iv['commission'],2);
                $iv['commission2'] = round($iv['commission2'],2);
                array_push($newdata,$iv);
                $i++;
            }
            unset($iv);
            $info['invite_cashbak_data'] = jsonEncode($newdata);
        }else{
             return json(['status'=>0,'msg'=>'返现设置选项不能为空']);
        }

        if($info['id']){
            Db::name('invite_cashback')->where('aid',aid)->where('bid',0)->where('id',$info['id'])->update($info);
            \app\common\System::plog('修改邀请返现活动'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = 0;
            $info['createtime'] = time();
            $id = Db::name('invite_cashback')->insertGetId($info);
            \app\common\System::plog('添加邀请返现活动'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('invite_cashback')->where('aid',aid)->where('bid',0)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除邀请返现活动'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}