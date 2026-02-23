<?php
// JK客户定制

//custom_file(daily_jinju)
//每日金句 - 列表
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class DailyjinjuProduct extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    //列表
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
            if(bid>0){
                $where[] = ['bid','=',bid];
            }else{
                $where[] = ['bid','=',0];
            }
            if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
            if(input('?param.status') && input('param.status')!==''){
                $status = input('param.status');
                $where[] = ['status','=',$status];
            }

            $where[] = ['is_del','=',0];
            $count = 0 + Db::name('dailyjinju_product')->where($where)->count();
            $data  = Db::name('dailyjinju_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        View::assign('bid',bid);
        return View::fetch();
    }
    public function edit(){
        $id = input('param.id')?input('param.id'):0;
        if($id){
            $where = [];
            $where['id']  = $id;
            $where['aid'] = aid;
            $where['bid'] = bid;
            $where['is_del'] = 0;
            $info = Db::name('dailyjinju_product')->where($where)->find();
            if(!$info) showmsg('每日金句不存在');
        }else{
            $info = ['id'=>0,'fabutime'=>time(),'daka_limit_day'=>0];
        }

        View::assign('info',$info);
        View::assign('id',$id);
        return View::fetch();
    }
    public function save(){

        $info = input('post.info/a');

        $id   = $info['id'];
        unset($info['id']);
        $info['fabutime'] = strtotime($info['fabutime']);
        $tourlpic = input('post.tourlpic/a');
		$tourl = input('post.tourl/a');
		
		foreach($tourlpic as $k=>$v){
			if($v){
				$tem = [];
				$tem['kid']=$k;
				$tem['tourlpic']=$v;
				$tem['tourl'] = $tourl[$k];
				$fixed[] = $tem;
			}
			 
		}
        if($fixed){
			$info['tourldata'] = json_encode($fixed,JSON_UNESCAPED_UNICODE);
		}else{
			$info['tourldata'] = '';
		}
        if($id){
            $data = [];
            $data = $info;
            $data['updatetime'] = time();
            $up = Db::name('dailyjinju_product')->where('id',$id)->where('aid',aid)->where('bid',bid)->update($data);
            if(!$up){
                return json(['status'=>0,'msg'=>'保存失败']);
            }
        }else{
            $data = [];
            $data = $info;
            $data['aid']        = aid;
            $data['bid']        = bid;
            $data['createtime'] = time();
            $insert = Db::name('dailyjinju_product')->insert($data);
            if(!$insert){
                return json(['status'=>0,'msg'=>'保存失败']);
            }
        }
        \app\common\System::plog('编辑每日金句');
        return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
    }
    public function del(){
        $ids = input('param.ids');
        Db::name('dailyjinju_product')->where('id','in',$ids)->where('aid',aid)->where('bid',bid)->update(['is_del'=>1,'updatetime'=>time()]);
        \app\common\System::plog('删除每日金句'.input('param.id'));
        return json(['status'=>1,'msg'=>'删除成功','url'=>(string)url('index')]);
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
		Db::name('dailyjinju_product')->where($where)->update(['status'=>$st]);
		\app\common\System::plog('每日金句改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}

    //选择内容
    public function chooseproduct(){
        return View::fetch();
    }

    //复制
    public function copy(){
        $id = input('post.id/d');
        if(empty($id)) {
            return json(['status'=>0,'msg'=>'参数错误']);
        }

        $where = [];
        $where[] = ['id','=',$id];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',0];
        $where[] = ['is_del','=',0];
        $bagdata = Db::name('dailyjinju_product')->where($where)->find();
        if(!$bagdata){
            return json(['status'=>0,'msg'=>'数据不存在']);
        }

        unset($bagdata['id']);
        $bagdata['name'] = '复制-'.$bagdata['name'];
        $copydata = [];
        $copydata = $bagdata;
        $copydata['bid'] = bid;
        $copydata['createtime'] = time();
        $copydata['updatetime'] = time();
        $insertid = Db::name('dailyjinju_product')->insertGetId($copydata);
        if($insertid){
            \app\common\System::plog('复制每日金句'.$id);
            return json(['status'=>1,'msg'=>'操作成功','proid'=>$insertid]);
        }else{
            return json(['status'=>0,'msg'=>'操作失败']);
        }
        
    }

    //获取信息
    public function getproduct(){
        $proid = input('post.proid/d');
        $product = Db::name('dailyjinju_product')->where('id',$proid)->where('aid',aid)->where('bid',bid)->where('is_del',0)->find();
        return json(['product'=>$product]);
    }
}