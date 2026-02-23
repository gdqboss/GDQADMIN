<?php
// JK客户定制

//custom_file(yx_business_miandan)
// +----------------------------------------------------------------------
// | 免单订单
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class BusinessMiandanOrder extends Common
{
    public function initialize(){
        parent::initialize();
    }
    //订单列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
			
			if(input('param.orderids')) $where[] = ['id','in',input('param.orderids')];
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.mid/d')) $where[] = ['mid','=',input('param.mid/d')];
			if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){

				$where[] = ['status','=',input('param.status')];
				
			}
			$count = 0 + Db::name('business_miandan_order')->where($where)->count();
			$list = Db::name('business_miandan_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['goodsdata'] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['proname'].'</div>'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['ggname'].'X'.$vo['num'].'</div>'.
						'<div style="padding-top:0px;color:#f60;font-size:12px">购买价￥'.$vo['product_price'].'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
    }
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			
		}
		$list = Db::name('business_miandan_order')->where($where)->order($order)->select()->toArray();
		$title = array('订单号','下单人','商品名称','总价','备注','下单时间','订单状态');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$status='';
			if($vo['status']==0){
				$status = '未使用';
			}elseif($vo['status']==1){
				$status = '已核销';
			}
            $data1 = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				$vo['totalprice'],
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
			];

			$data[] = $data1;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
}
