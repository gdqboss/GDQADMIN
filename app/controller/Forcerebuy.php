<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 强制复购 custom_file(forcerebuy)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Forcerebuy extends Common
{
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
            $where[] = ['bid','=',bid];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('forcerebuy')->where($where)->count();
            $data = Db::name('forcerebuy')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){

            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('forcerebuy')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'','name'=>'强制复购','type'=>0,'daytype'=>0,'wfgtype'=>0,'wfgtxtips'=>'对不起！你需要复购，才能解冻','gettj'=>'-1','fwtype'=>0,'status'=>1);
        }
        $info['gettj'] = explode(',',$info['gettj']);

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

        if($info['id']){
            Db::name('forcerebuy')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\common\System::plog('修改强制复购活动'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('forcerebuy')->insertGetId($info);
            \app\common\System::plog('添加强制复购活动'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('forcerebuy')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除强制复购活动'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

	//佣金冻结记录
    public function record(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['commission_isfreeze','=',1];
            if(input('param.mid')) $where[] = ['id','=',input('param.mid')];
            if(input('param.nickname')) $where[] = ['nickname|tel|realname|card_code','like','%'.input('param.nickname').'%'];
            $count = 0 + Db::name('member')->where($where)->count();
            $data = Db::name('member')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){

            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
	//解冻
	public function unfreeze(){
		$ids = input('post.ids/a');
		Db::name('member')->where('aid',aid)->where('id','in',$ids)->update(['commission_isfreeze'=>0]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
}