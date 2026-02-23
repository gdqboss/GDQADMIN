<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店类型
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelCategory extends Common
{
	public $text;
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
		$this->text = \app\model\Hotel::gettext(aid);
	}
	//分类列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$cate0 = Db::name('hotel_category')->where('aid',aid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['deep'] = 0;
				$data[] = $c0;
			
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
		}
		View::assign('text',$this->text);
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(bid > 0) showmsg('无访问权限');
		if(input('param.id')){
			$info = Db::name('hotel_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','showtj'=>-1);
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('hotel_category')->where('aid',aid)->where('pid',0)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
	
        View::assign('info',$info);
		View::assign('pcatelist',$pcatelist);
		return View::fetch();
	}
	//保存
	public function save(){
		if(bid > 0) showmsg('无访问权限');
		$info = input('post.info/a');
        $info['showtj'] = implode(',',$info['showtj']);
        if(empty($info['showtj'])) $info['showtj'] = -1;

        if($info['id']){
			Db::name('hotel_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑酒店类型'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('hotel_category')->insertGetId($info);
			\app\common\System::plog('添加酒店类型'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		if(bid > 0) showmsg('无访问权限');
		$ids = input('post.ids/a');
		Db::name('hotel_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog('删除酒店类型'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//选择分类弹窗
    public function choosecategory(){
    	$selmore = input('selmore')?true:false;//是否多选
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$cate0 = Db::name('hotel_category')->where($where)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['showname'] = $c0['name'];
				$c0['deep'] = 0;
				$data[] = $c0;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
		}
		View::assign('selmore',$selmore);
		return View::fetch();
    }
}