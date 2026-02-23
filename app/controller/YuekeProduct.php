<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 课程-课程管理 custom_file(yueke)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class YuekeProduct extends Common
{
	//服务列表
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
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('yueke_product')->where($where)->count();
			$data = Db::name('yueke_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = Db::name('yueke_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}

			foreach($data as $k=>$v){
				$v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname']) $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				$data[$k]['worker_realname'] = Db::name('yueke_worker')->where('id',$v['workerid'])->value('realname');
				if($v['status']==2){ //设置上架时间
					if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('yueke_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('yueke_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑课程
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yueke_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('课程不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			$bid = $info['bid'];
			$newgglist = array();
            }else{
			$bid = bid;
		}
		if(!$info){
			$info = [];
			$info['yyzhouqi'] = '1,2,3,4,5,6,0';
			$info['yytimeday'] = '1';
			$info['prehour'] = '4';
			$info['formdata'] = json_encode([
				['key'=>'input','val1'=>'备注','val2'=>'选填，请输入备注信息','val3'=>'0'],	
			]);
			$info['gettj'] = '-1';
		}
        $info['cid'] = explode(',',$info['cid']);
		$info['yyzhouqi'] = explode(',',$info['yyzhouqi']);
		$info['couponids'] = explode(',',$info['couponids']);
		$info['yytimeday'] = explode(',',$info['yytimeday']);
		$info['gettj'] = explode(',',$info['gettj']);

		//分类
		$clist = Db::name('yueke_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('yueke_category')->Field('id,name')->where('aid',aid)->where('bid',$bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			$clist[$k]['child'] = $child;
		}
		//获取次卡
		$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$bid)->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->where('type',3)->order('sort desc,id')->select()->toArray();
		//教练
		$workerlist = Db::name('yueke_worker')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->where('status',1)->select()->toArray();
		//等级
		$levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
        if(!isset($newgglist)){
            $newgglist = array();
        }
        View::assign('couponlist',$couponlist);
		View::assign('clist',$clist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('workerlist',$workerlist);
        View::assign('newgglist',$newgglist);
		return View::fetch();
	}
	//保存课程
	public function save(){
		if(input('post.id')){
			$product = Db::name('yueke_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('课程不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
			$bid = $product['bid'];
		}else{
			$bid = bid;
		}
		$info = input('post.info/a');

		$info['detail'] = \app\common\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		$data['fuwupoint'] = $info['fuwupoint'];
		$data['sellpoint'] = $info['sellpoint'];
		$data['procode'] = $info['procode'];
		$data['cid'] = $info['cid'];
		$data['sort'] = $info['sort'];
		$data['detail'] = $info['detail'];
		$data['starttime'] = $info['starttime'];
		$data['endtime'] = $info['endtime'];
		$data['workerid'] =  $info['workerid'];
		$data['yynum'] = $info['yynum'];
		$data['prehour'] = $info['prehour'];
		if(!$product) $data['createtime'] = time();
		$data['rqtype'] = $info['rqtype'];
		$data['yyzhouqi'] =  implode(',',$info['yyzhouqi']);
		$data['yybegintime'] = $info['yybegintime'];
		$data['yyendtime'] = $info['yyendtime'];
		$data['yytimeday'] = implode(',',$info['timeday']);
		$data['couponids'] = $info['couponids']?$info['couponids']:0;
		$data['sell_price'] = $info['sell_price'];
		$data['status'] = $info['status'];
		$data['start_time'] = $info['start_time'];
		$data['end_time'] = $info['end_time'];
		$data['gettj'] = implode(',',$info['gettj']);
		$data['gettjtip'] = $info['gettjtip'];
		$data['gettjurl'] = $info['gettjurl'];

		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k]);
		}
		$data['formdata'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);

        if($product){
			Db::name('yueke_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\common\System::plog('约课课程编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = $bid;
			$proid = Db::name('yueke_product')->insertGetId($data);
			\app\common\System::plog('约课课程编辑'.$proid);
		}

        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('yueke_product')->where($where)->update(['status'=>$st]);
		\app\common\System::plog('约课课程改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('yueke_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$prolist = Db::name('yueke_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('yueke_product')->where('id',$pro['id'])->delete();
		}

        \app\common\System::plog('约课课程删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制课程
	public function procopy(){
		$product = Db::name('yueke_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'课程不存在,请重新选择']);
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('yueke_product')->insertGetId($data);

        \app\common\System::plog('约课课程复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//选择课程
	public function chooseproduct(){
		//分类
		$clist = Db::name('yueke_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('yueke_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取课程信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('yueke_product')->where('aid',aid)->where('id',$proid)->find();
		return json(['product'=>$product]);
	}
    function defaultSet(){
        $set = Db::name('yueke_set')->where('bid',bid)->where('aid',aid)->find();
        if(!$set){
            Db::name('yueke_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
