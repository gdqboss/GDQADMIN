<?php
// JK客户定制

//custom_file(video_spider)
// +----------------------------------------------------------------------
// | 短视频去水印
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class VideoSpiderOrder extends Common
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
            if(input('mid')){
                $where[] = ['o.mid','=',input('mid')];
            }
            $status = input('status');

            if($status!=null && $status!='all'){
                $where[] = ['o.status','=',$status];
            }
			$field = 'o.*,m.nickname';
            $data = Db::name('videospider_order')
                ->alias('o')
                ->join('member m','o.mid=m.id','left')
                ->where($where)
                ->field($field)
                ->order($order)
                ->select()
                ->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
		}
		return View::fetch();
    }
	public function view(){
        $id = input('id');
        $where = [];
        $where[] = ['o.id','=',$id];
        $field = 'o.*,m.nickname';
        $info = Db::name('videospider_order')
            ->alias('o')
            ->join('member m','o.mid=m.id','left')
            ->where($where)
            ->field($field)
            ->order('id desc')
            ->find();
        View::assign('info',$info);
        return View::fetch();
    }
	
}
