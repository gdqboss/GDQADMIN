<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 活动报名-活动管理 custom_file(huodong_baoming)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class HuodongBaomingList extends Common
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
			if(input('?param.type') && input('param.type')!=='') $where[] = ['type','=',input('param.type')];

			$count = 0 + Db::name('huodong_baoming_product')->where($where)->count();
			$data = Db::name('huodong_baoming_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = Db::name('huodong_baoming_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}

			foreach($data as $k=>$v){
				$gglist = Db::name('huodong_baoming_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
				$ggdata = array();
				foreach($gglist as $gg){
					$ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled">￥'.$gg['sell_price'].'</button>';
				}
				$v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
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
		$clist = Db::name('huodong_baoming_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('huodong_baoming_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}

		$set = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$set){
			Db::name('huodong_baoming_set')->insert(['aid'=>aid,'bid'=>bid]);
		}

		View::assign('clist',$clist);

        $this->defaultSet();
		return View::fetch();
    }
	//编辑活动报名
	public function edit(){
		if(input('param.id')){
			$info = Db::name('huodong_baoming_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$info['timepoint'] = explode(',',$info['timepoint']);
			//修改下活动单位格式 为多单位
			if($info['zhubanfang_name'] && !$info['huodong_danwei']){
				$info['huodong_danwei'] = array(['name'=>$info['zhubanfang_name'],'tel'=>$info['zhubanfang_tel']]);
			}elseif($info['huodong_danwei']){
				$info['huodong_danwei'] = json_decode($info['huodong_danwei'],true);
			}else{
				$info['huodong_danwei'] = [];
			}
			if(!$info) showmsg('活动不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			$bid = $info['bid'];
		}else{
			$bid = bid;
		}
		$set = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',$bid)->find();
		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('huodong_baoming_guige')->where('aid',aid)->where('bid',$bid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('huodong_baoming_guige')->where('aid',aid)->where('bid',$bid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
			}
		}else{
			$info = [];
		}
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->order('sort,id')->select()->toArray();

		//分类
		$clist = Db::name('huodong_baoming_category')->Field('id,name,pid')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$pid = $v['id'];	
			$child = Db::name('huodong_baoming_category')->Field('id,name')->where('aid',aid)->where('bid',$bid)->where('status',1)->where('pid',$pid)->order('sort desc,id')->select()->toArray();
			$clist[$k]['child'] = $child;
		}
		//echo db('huodong_baoming_category')->getlastsql();
        //多商户使用平台服务人员
        $bid = bid;
		$sysset = Db::name('huodong_baoming_set')->where('aid',aid)->find();
        $info['cid'] = explode(',',$info['cid']);
		if(!$info['formdata']){
			$info['formdata'] = json_encode([
				['key'=>'input','val1'=>'备注','val2'=>'选填，请输入备注信息','val3'=>'0','val4'=>'0'],	
			]);
		}
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
		$default_cid = $default_cid ? $default_cid : 0;
		$memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('memberlevel',$memberlevel);
		$info['gettj'] = explode(',',$info['gettj']);
		$info['mianfei_gettj'] = explode(',',$info['mianfei_gettj']);



		View::assign('sysset',$sysset);
		View::assign('clist',$clist);
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('set',$set);

		View::assign('bid',bid);
		return View::fetch();
	}
	//保存活动报名
	public function save(){
		if(input('post.id')){
			$product = Db::name('huodong_baoming_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('活动报名不存在');
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
		$data['cid'] = $info['cid'];
		$data['perlimit'] = $info['perlimit'];
		$data['longitude'] = $info['longitude'];
		$data['latitude'] = $info['latitude'];
		$data['huodong_address'] = $info['huodong_address'];
		if($info['zhubanfang_tel']) $data['zhubanfang_tel'] = $info['zhubanfang_tel'];
		if($info['zhubanfang_name']) $data['zhubanfang_name'] = $info['zhubanfang_name'];
		//z主办单位多个
		if(input('post.hddanwei/a')) $data['huodong_danwei'] =jsonEncode(input('post.hddanwei/a'));

		$data['type'] = $info['type'];
		$data['sellpoint'] = $info['sellpoint'];

		$data['show_member_order'] = $info['show_member_order'];
		if(isset($info['detail_text'])){
			$data['detail_text'] = $info['detail_text'];
		}
		if(isset($info['detail_pics'])){
			$data['detail_pics'] = $info['detail_pics'];
		}		
		$data['start_sales'] = $info['start_sales'];
		$data['start_viewnum'] = $info['start_viewnum'];

		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];

		if(!$product) $data['createtime'] = time();

		$data['start_time'] = $info['start_time'];
		$data['end_time'] = $info['end_time'];
		$data['huodong_start_time'] = $info['huodong_start_time'];
		$data['huodong_end_time'] = $info['huodong_end_time'];
		$data['latitude'] = $info['latitude'];
		$data['longitude'] = $info['longitude'];
		$data['huodong_address'] = $info['huodong_address'];
		$data['zhubanfang_name'] = $info['zhubanfang_name'];
		$data['zhubanfang_tel'] = $info['zhubanfang_tel'];

		$data['gettj'] = implode(',',$info['gettj']);
		$data['mianfei_gettj'] = implode(',',$info['mianfei_gettj']);
		$data['mianfei_memberlevel_open'] = $info['mianfei_memberlevel_open'];
		$data['fanwei'] = $info['fanwei'];
		$data['fanwei_lng'] = $info['fanwei_lng'];
		$data['fanwei_lat'] = $info['fanwei_lat'];
		$data['fanwei_range'] = $info['fanwei_range'];

		$data['stock'] = $info['stock'];
		$data['perlimit'] = $info['perlimit'];

		$data['givescore'] = $info['givescore'];

		$sellprice_field = 'sell_price';
		

		$sell_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;
		$givescore=0;
		//新增积分
		$scoreprice_field = 'score_price';
		foreach(input('post.option/a') as $ks=>$v){
			if($sell_price==0 || $v[$sellprice_field] < $sell_price){
				$sell_price = $v[$sellprice_field];
				//$givescore = $v['givescore'];
			}
			$score_price = $v[$scoreprice_field];
		}
		$data['score_price'] = $score_price;
		$data['sell_price'] = $sell_price;

		$data['is_fufei'] = $info['is_fufei'];
		if($info['is_fufei'] == 0){
			$data['sell_price'] = 0;
			$data['score_price'] = 0;
		}
		//$data['givescore'] = $givescore;
		//多规格 规格项
		$data['guigedata'] = input('post.specs');

		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');	
		$dataval4 = input('post.dataval4/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k],'val4'=>$dataval4[$k]);
		}
		$data['formdata'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);

		if($product){
			Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\common\System::plog('活动报名编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = $bid;
			$proid = Db::name('huodong_baoming_product')->insertGetId($data);
			\app\common\System::plog('活动报名添加'.$proid);
		}
        
        if($product){
            $bid = $product['bid'];
        }else{
            $bid = $info['bid']?:bid;
        }
        $sales = $info['sales']-$info['oldsales'];
        if($sales!=0){
            \app\model\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }
		//多规格
		$newggids = array();
		foreach(input('post.option/a') as $ks=>$v){
			$ggdata = array();
			$ggdata['bid'] = $bid;
			$ggdata['proid'] = $proid;
			$ggdata['ks'] = $ks;
			$ggdata['name'] = $v['name'];
			$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
			$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['danwei'] = $v['danwei'];
			$ggdata['givescore'] = $v['givescore'];
			$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
			$ggdata['score_price'] = $v['score_price'];			
			$guige = Db::name('huodong_baoming_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('huodong_baoming_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('huodong_baoming_guige')->insertGetId($ggdata);
			}
			$newggids[] = $ggid;
		}
		Db::name('huodong_baoming_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
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
		Db::name('huodong_baoming_product')->where($where)->update(['status'=>$st]);
		\app\common\System::plog('活动报名改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
		$prolist = Db::name('huodong_baoming_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('huodong_baoming_product')->where('id',$pro['id'])->delete();
			Db::name('huodong_baoming_guige')->where('proid',$pro['id'])->delete();
		}
		\app\common\System::plog('活动报名删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制活动报名
	public function procopy(){
		$product = Db::name('huodong_baoming_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'活动报名不存在,请重新选择']);
		$gglist = Db::name('huodong_baoming_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('huodong_baoming_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			Db::name('huodong_baoming_guige')->insert($ggdata);
		}
		\app\common\System::plog('活动报名复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//选择活动报名
	public function chooseproduct(){
		//分类
		$clist = Db::name('huodong_baoming_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('huodong_baoming_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取活动报名信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('huodong_baoming_product')->where('aid',aid)->where('bid',bid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('huodong_baoming_guige')->where('aid',aid)->where('bid',bid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
    function defaultSet(){
        $set = Db::name('huodong_baoming_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('huodong_baoming_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
