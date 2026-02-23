<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约课 - 教练列表 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class YuekeWorker extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
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
			if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('?param.shstatus') && input('param.shstatus')!=='') $where[] = ['shstatus','=',input('param.shstatus')];
			$count = 0 + Db::name('yueke_worker')->where($where)->count();
			$data = Db::name('yueke_worker')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				$data[$k]['nickname'] = $member['nickname'];
				$data[$k]['mheadimg'] = $member['headimg'];
                $pszCount = Db::name('yueke_order')->where('aid',aid)->where('workerid',$v['id'])->where('status','in','0,1,2')->count();
                $ywcCount = Db::name('yueke_order')->where('aid',aid)->where('workerid',$v['id'])->where('status',3)->count();
                $data[$k]['pszCount'] = $pszCount;
                $data[$k]['ywcCount'] = $ywcCount;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        $this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yueke_worker')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$hasun = Db::name('yueke_worker')->where('aid',aid)->where('id','<>',$info['id'])->where('un',$info['un'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该账号已被占用']);
		}
		if($info['id']){
			$info['pwd'] = md5($info['pwd']);
			Db::name('yueke_worker')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑教练'.$info['id']);
		}else{
			if($info['pwd']!=''){
				$info['pwd'] = md5($info['pwd']);
			}else{
				unset($info['pwd']);
			}
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('yueke_worker')->insertGetId($info);
			\app\common\System::plog('添加教练'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('yueke_worker')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\common\System::plog('教练改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yueke_worker')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog('教练删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('yueke_set')->where('bid',bid)->where('aid',aid)->find();
        if(!$set){
            Db::name('yueke_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }

    //审核
    public function setcheckst(){
        $st = input('post.st/d');
        $id = input('post.id/d');
        $reason = input('post.reason');
        $worker = Db::name('yueke_worker')->where('aid',aid)->where('id',$id)->find();
        if(!$worker) return json(['status'=>0,'msg'=>t('教练').'不存在']);

        $update['shstatus'] = $st;
        if($st == 1){
            $update['status'] = 1;
        }else{
            $update['reason'] = $reason;
        }
        Db::name('yueke_worker')->where('aid',aid)->where('id',$id)->update($update);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}
