<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 证书 - 学历 custom_file(extend_certificate)
// +----------------------------------------------------------------------
namespace app\controller\extend;
use app\controller\Common;
use think\facade\Db;
use think\facade\View;

class CertificateEducation extends Common
{
   
    public function initialize(){
		parent::initialize();
	}
    //分类列表
    public function index(){
        if(request()->isAjax()){
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $count =  Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->count();
            $data = Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }

	//编辑
	public function edit(){
        if(input('param.id')){
            $info = Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'');
        }
        $pcatelist = Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
        View::assign('info',$info);
        View::assign('pcatelist',$pcatelist);
        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑证书学历'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('certificate_education')->insertGetId($info);
			\app\common\System::plog('添加证书学历'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\common\System::plog('证书学历改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog('证书学历删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
