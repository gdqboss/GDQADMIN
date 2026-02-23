<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约课 - 课程类型 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class YuekeCategory extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
		$set = Db::name('yueke_set')->where('aid',aid)->where('bid',bid)->find();
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			$where[] = ['pid','=',0];
			if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('yueke_category')->where($where)->count();
			$data = [];
			$cate0 = Db::name('yueke_category')->where($where)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['deep'] = 0;
				$data[] = $c0;
				$cate1 = Db::name('yueke_category')->where('aid',aid)->where('bid',bid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
					}else{
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
					}
					$c1['deep'] = 1;
					$data[] = $c1;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('set',$set);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yueke_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('yueke_category')->where('aid',aid)->where('bid',bid)->where('pid',0)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
		View::assign('pcatelist',$pcatelist);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('yueke_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑约课类型'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('yueke_category')->insertGetId($info);
			\app\common\System::plog('添加约课类型'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('yueke_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\common\System::plog('约课类型改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yueke_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog('约课类型删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('yueke_set')->where('bid',bid)->where('aid',aid)->find();
        if(!$set){
            Db::name('yueke_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
