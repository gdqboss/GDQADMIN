<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 约车-管理 custom_file(car_hailing)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class CarHailingProduct extends Common
{
    public $category = ['1' => '租车','2' => '拼车' ,'3' => '包车'];
    public $category_list = [['id' =>1,'name' =>'租车'],['id' => 2,'name' => '拼车'],['id' =>3,'name' => '包车']];
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
            if(input('?param.cid') && input('param.cid')!=='') $where[] = ['cid','=',input('param.cid')];
			$count = 0 + Db::name('car_hailing_product')->where($where)->count();
			$data = Db::name('car_hailing_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = $this->category_list;
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}

			foreach($data as $k=>$v){
                if($v['cid'] ==2){
                    $data[$k]['unit'] = '/人';
                }
                if($v['cid'] ==1 || $v['cid'] ==3){
                    $data[$k]['unit'] = '/天';
                }
                $cname =  $this->category[$v['cid']];
                
                $data[$k]['cname'] = $cname;
               
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
		$clist = $this->category_list;
		View::assign('clist',$clist);
		return View::fetch();
    }
    
    //选择约车
    public function chooseproduct(){
        if(request()->isAjax()){
            $proid =   input('param.proid/d');
            $info = Db::name('car_hailing_product')->where('aid',aid)->where('id',$proid)->find();
            if(!$info) showmsg('信息不存在');
            return json(['product'=>$info]);
        }
        //分类
        $clist = $this->category_list;
        View::assign('clist',$clist);
        return View::fetch();
    }
	//编辑约车信息
	public function edit(){
		if(input('param.id')){
			$info = Db::name('car_hailing_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('信息不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			$bid = $info['bid'];
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
			$info['cid'] = 1;
		}
		$info['yyzhouqi'] = explode(',',$info['yyzhouqi']);
		$info['couponids'] = explode(',',$info['couponids']);
		$info['yytimeday'] = explode(',',$info['yytimeday']);
		$info['gettj'] = explode(',',$info['gettj']);

        //分类
        $plist = Db::name('car_hailing_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($plist as $k=>$v){
            $plist[$k]['child'] = Db::name('car_hailing_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
        }
		//获取次卡
		$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$bid)->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->where('type',3)->order('sort desc,id')->select()->toArray();
		
		//等级
		$levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
		$childlist = Db::name('car_hailing_category')->where('pid',$info['cid'])->where('status',1)->select();
        //分类
        $clist = $this->category_list;
        
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        View::assign('aglevellist',$aglevellist);

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        //团队分红
        $teamlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
        View::assign('teamlevellist',$teamlevellist);
        if(getcustom('teamfenhong_pingji')){
            $teampjlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhong_pingji_lv','>','0')->order('sort,id')->select()->toArray();
            View::assign('teampjlevellist',$teampjlevellist);
        }
        //股东分红
        $gdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
        View::assign('gdlevellist',$gdlevellist);

        $areafhlevellist = Db::name('member_level')->where('aid',aid)->where('areafenhong','>','0')->select()->toArray();
        View::assign('areafhlevellist',$areafhlevellist);

        View::assign('childlist',$childlist);
		View::assign('couponlist',$couponlist);
		View::assign('plist',$plist);
		View::assign('clist',$clist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		return View::fetch();
	}
	public function getCategory(){
        $pid =input('param.pid/d');
        $childlist = Db::name('car_hailing_category')->where('pid',$pid)->where('status',1)->select();
        return json(['status'=>1,'msg'=>'复制成功','list'=>$childlist]);
    }
	//保存
	public function save(){
		if(input('post.id')){
			$product = Db::name('car_hailing_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('信息不存在');
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
		$data['workerid'] =  0;
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
		$data['car_num'] = $info['car_num'];
		$data['status'] = $info['status'];
		$data['start_time'] = $info['start_time'];
		$data['end_time'] = $info['end_time'];
		$data['gettj'] = implode(',',$info['gettj']);
		$data['gettjtip'] = $info['gettjtip'];
		$data['gettjurl'] = $info['gettjurl'];
		$data['pid'] = $info['pid'];
		$data['is_coupon'] = $info['is_coupon'];
		$data['feature'] = $info['feature'];
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
        $data['commissionset'] = $info['commissionset'];
        $data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
        $data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
        $data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
        if(bid ==0){
            $data['fenhongset'] = $info['fenhongset'];
            $data['gdfenhongset'] = $info['gdfenhongset'];
            $data['gdfenhongdata1'] = jsonEncode(input('post.gdfenhongdata1/a'));
            $data['gdfenhongdata2'] = jsonEncode(input('post.gdfenhongdata2/a'));
            $data['teamfenhongset'] = $info['teamfenhongset'];
            $data['teamfenhongdata1'] = jsonEncode(input('post.teamfenhongdata1/a'));
            $data['teamfenhongdata2'] = jsonEncode(input('post.teamfenhongdata2/a'));
            $data['areafenhongset'] = $info['areafenhongset'];
            $data['areafenhongdata1'] = jsonEncode(input('post.areafenhongdata1/a'));
            $data['areafenhongdata2'] = jsonEncode(input('post.areafenhongdata2/a'));
            if(getcustom('teamfenhong_pingji')){
                $data['teamfenhongpjset'] = $info['teamfenhongpjset'];
                $data['teamfenhongpjdata1'] = jsonEncode(input('post.teamfenhongpjdata1/a'));
                $data['teamfenhongpjdata2'] = jsonEncode(input('post.teamfenhongpjdata2/a'));
            }
        }
		if($product){
			Db::name('car_hailing_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\common\System::plog('约车编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = $bid;
			$proid = Db::name('car_hailing_product')->insertGetId($data);
			\app\common\System::plog('约车编辑'.$proid);
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
		Db::name('car_hailing_product')->where($where)->update(['status'=>$st]);
		\app\common\System::plog('约车改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('car_hailing_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
		$prolist = Db::name('car_hailing_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('car_hailing_product')->where('id',$pro['id'])->delete();
		}
		\app\common\System::plog('约车删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    //复制课程
    public function procopy(){
        $product = Db::name('car_hailing_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
        if(!$product) return json(['status'=>0,'msg'=>'约车不存在,请重新选择']);
        $data = $product;
        $data['name'] = '复制-'.$data['name'];
        unset($data['id']);
        $data['status'] = 0;
        $newproid = Db::name('car_hailing_product')->insertGetId($data);
        \app\common\System::plog('约车复制'.$newproid);
        return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
    }
}
