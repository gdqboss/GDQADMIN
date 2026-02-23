<?php
// JK客户定制

//custom_file(map_mark)
// +----------------------------------------------------------------------
// | 地图标注订单
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class MapmarkOrder extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['o.aid','=',aid];
			if(input('cid')){
                $where[] = ['o.cids','like','%'.input('cid').'%'];
            }
            if(input('mid')){
                $where[] = ['o.mid','=',input('mid')];
            }
            $status = input('status');

            if($status!=null && $status!='all'){
                $where[] = ['o.status','=',$status];
            }
			$field = 'o.*,m.nickname';
            $data = Db::name('mapmark_order')
                ->alias('o')
                ->join('member m','o.mid=m.id','left')
                ->where($where)
                ->field($field)
                ->order($order)
                ->select()
                ->toArray();
            if($data){
                foreach($data as $k=>$v){
                    $cnames = Db::name('mapmark_category')->whereIn('id',$v['cids'])->column('name');
                    $data[$k]['map_name'] = implode(',',$cnames);
                }
            }

			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
		}
		$cats = Db::name('mapmark_category')->select()->toArray();
		View::assign('cats',$cats);
		return View::fetch();
    }
	public function edit(){
        $id = input('id');
        $where = [];
        $where[] = ['o.id','=',$id];
        $field = 'o.*,m.nickname';
        $info = Db::name('mapmark_order')
            ->alias('o')
            ->join('member m','o.mid=m.id','left')
            ->where($where)
            ->field($field)
            ->order('id desc')
            ->find();
        $cnames = Db::name('mapmark_category')->whereIn('id',$info['cids'])->column('name');
        $info['map_name'] = implode(',',$cnames);
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        if($info['id']){
            Db::name('mapmark_order')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\common\System::plog('修改订单'.$info['id']);
        }else{
            return json(['status'=>0,'msg'=>'参数错误']);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
	
}
