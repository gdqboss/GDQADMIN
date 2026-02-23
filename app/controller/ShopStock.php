<?php
// JK客户定制

//custom_file(shop_add_stock)
// +----------------------------------------------------------------------
// | 录入库存
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class ShopStock extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//库存记录
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'record.id desc';
			}
			$where = array();
			$where[] = ['record.aid','=',aid];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['record.createtime','<',strtotime($ctime[1]) + 86400];
            }
			$where[] = ['record.stock','>',0];
			if(input('param.proname')) $where[] = ['product.name','like','%'.trim(input('param.proname')).'%'];
			if(input('param.proid')) $where[] = ['product.id','=',trim(input('param.proid'))];
            if(input('param.notice')) {
                //过期预警
                $where[] = ['record.create_date','>',0];
                $where[] = ['record.quality_days','>',0];
                $where[] = ['record.notice_days','>',0];
                $where[] = [Db::raw('record.expire_date - record.notice_days * 86400'),'<=',time()];
            }

			$count = 0 + Db::name('shop_stock_order_goods')->alias('record')->field('product.name,product.pic,record.*')->join('shop_product product','record.proid=product.id')->where($where)->count();
			$data = Db::name('shop_stock_order_goods')->alias('record')->field('product.name,product.pic,record.*')->join('shop_product product','record.proid=product.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();			
			foreach($data as &$d){
				$guige = Db::name('shop_guige')->field('name')->where('id',$d['ggid'])->find();
				$d['ggname'] = $guige['name'];
                $d['create_date'] = $d['create_date'] ? date('Y-m-d', $d['create_date']) : '';
                $d['expire_date'] = $d['expire_date'] ? date('Y-m-d', $d['expire_date']) : '';
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}

	public function add(){
        $custom_stock_cost = false;
        if(getcustom('shop_add_stock_cost'))
            $custom_stock_cost = true;
        View::assign('custom_stock_cost',$custom_stock_cost);
		return View::fetch();
    }
	public function save(){
		$data = input('post.');
		$prodata = explode('-',$data['prodata']);
		$prolist = [];
		foreach($prodata as $key=>$pro){
			$sdata = explode(',',$pro);//商品id,规格id，数量，进货费用
			$product = Db::name('shop_product')->where('aid',aid)->where('id',$sdata[0])->find();

			if(!$product) continue;
			$guige = Db::name('shop_guige')->where('aid',aid)->where('id',$sdata[1])->find();
			if(!$guige) continue;
			$prolist[$key] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2],'totalprice'=>$sdata[3]];
            //进货费用$sdata[3]
		}

        $ordernum = date('YmdHis').rand(1,99);
        $user = Db::name('admin_user')->where('id',$this->uid)->find();
        if(getcustom('shop_add_stock_cost')) $custom_stock_cost = true;
        foreach($prolist as $key=>$v){
            $product = $v['product'];
            $guige = $v['guige'];
            $num = $v['num'];
            $ogdata = [];
            $ogdata['aid'] = aid;
            $ogdata['bid'] = $product['bid'];
            $ogdata['ordernum'] = $ordernum;
            $ogdata['proid'] = $product['id'];
            $ogdata['ggid'] = $guige['id'];
            $ogdata['stock'] = $num;
            $ogdata['afterstock'] = $guige['stock'] + $num;
            $ogdata['uid'] = $this->uid;
            $ogdata['uname'] = $user['un'];
            $ogdata['createtime'] = time();
            if($custom_stock_cost){
                $ogdata['totalprice'] = $v['totalprice'];
                $ogdata['cost_price'] = $v['totalprice'] > 0 ? $v['totalprice'] / $num : null;
                $ogdata['create_date'] = $data['create_date'][$key] ? strtotime($data['create_date'][$key]) : null;
                $ogdata['quality_days'] = $data['quality_days'][$key] ? $data['quality_days'][$key] : null;
                if($ogdata['create_date'] && $ogdata['quality_days'])$ogdata['expire_date'] = $ogdata['create_date'] + $ogdata['quality_days'] * 86400;
                $ogdata['notice_days'] = $data['notice_days'][$key];
            }
            $ogid = Db::name('shop_stock_order_goods')->insertGetId($ogdata);
            Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock+$num")]);
            Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock+$num")]);

        }

		\app\common\System::plog('商城录入库存'.$ogid);

		return json(['status'=>1,'msg'=>'录单成功','url'=>true]);
	}

    //库存管理
    public function manage(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'record.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'record.id desc';
            }
            $where = array();
            $where[] = ['record.aid','=',aid];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['record.createtime','<',strtotime($ctime[1]) + 86400];
            }
            $where[] = ['record.stock','>',0];
            if(input('param.proname')) $where[] = ['product.name','like','%'.trim(input('param.proname')).'%'];
            if(input('param.proid')) $where[] = ['product.id','=',trim(input('param.proid'))];

            $count = 0 + Db::name('shop_stock_order_goods')->alias('record')->field('product.name,product.pic,record.*')->join('shop_product product','record.proid=product.id')->group('record.ggid')->where($where)->count();
            $data = Db::name('shop_stock_order_goods')->alias('record')->field('product.name,product.pic,record.*')->join('shop_product product','record.proid=product.id')->group('record.ggid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as &$d){
                $guige = Db::name('shop_guige')->field('name')->where('id',$d['ggid'])->find();
                $d['ggname'] = $guige['name'];
//                $d['create_date'] = $d['create_date'] ? date('Y-m-d', $d['create_date']) : '';
                //进货成本价。根据这个成本价修改总体成本采用平均加权法如：（之前库存20件货进货成本是10，新增加的为进货价20元，进货数量为30，现在的实际成本为：(20X10+20X30)/50=16元）
                $totalnum = Db::name('shop_stock_order_goods')->where('aid',aid)->where('ggid',$d['ggid'])->sum('stock');
                $gglist = Db::name('shop_stock_order_goods')->where('aid',aid)->where('ggid',$d['ggid'])->select()->toArray();
                $ggtotal = 0;
                if($gglist){
                    foreach ($gglist as $item){
                        $ggtotal += $item['totalprice'];
                    }
                }
                $d['cost_price'] = round($ggtotal / $totalnum,2);
                $d['cost_price_total'] = round($d['cost_price'] * $totalnum,2);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }


}