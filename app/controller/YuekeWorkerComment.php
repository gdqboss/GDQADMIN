<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约课 - 教练评价 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class YuekeWorkerComment extends Common
{
	//评价列表
    public function index(){
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
			$where[] = ['bid','=',bid];
			if(input('param.worker_id')) $where[] = ['worker_id','=',input('param.worker_id')];
			if(input('param.content')) $where[] = ['content','like','%'.input('param.content').'%'];
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			//dump($where);
			$count = 0 + Db::name('yueke_worker_comment')->where($where)->count();
			$data = Db::name('yueke_worker_comment')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$psuser = Db::name('yueke_worker')->field('realname,tel')->where('id',$v['worker_id'])->find();
				$data[$k]['psuser'] = $psuser;
				if($v['bid']>0){
					$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$v['bid'])->find();
				}else{
					$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
				}
				$data[$k]['business'] = $business;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$workerlist = Db::name('yueke_worker')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
		View::assign('workerlist',$workerlist);
		return View::fetch();
    }
	//评价详情
	public function getdetail(){
		$detail= Db::name('yueke_worker_comment')->where('aid',aid)->where('id',input('post.id/d'))->find();
		if($detail['content_pic']) $detail['content_pic'] = explode(',',$detail['content_pic']);
		$member = Db::name('member')->where('aid',aid)->where('id',$detail['mid'])->find();
		if(!$member) $member = ['nickname'=>$detail['nickname'],'headimg'=>$detail['headimg']];
		return json(['status'=>1,'detail'=>$detail,'member'=>$member]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yueke_worker_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog('删除教练评价'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
