<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 证书 - 列表 custom_file(extend_certificate)
// +----------------------------------------------------------------------
namespace app\controller\extend;
use app\controller\Common;
use think\facade\Db;
use think\facade\View;

class CertificateList extends Common
{
   
    public function initialize(){
		parent::initialize();
        $this->defaultSet();
	}
    //列表
    public function index(){
        if(request()->isAjax()){
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.cid')){
                $where[] = Db::raw("find_in_set(".input('param.cid').",cid)");;
            }
            if(input('param.job_id')){
               
                $where[] =  Db::raw("find_in_set(".input('param.job_id').",job_id)");
            }
            if(input('param.ischecked') !=''){
                $where[] = ['ischecked','=',input('param.ischecked')];
            }
//            dump(input('param.name'));
            if(input('param.name')){
                $where[] = ['name|tel|school','like','%'.input('param.name').'%'];
            }
            if(input('param.education')){
                $where[] = ['education','=',input('param.education')];
            }
            $count = Db::name('certificate_list')->where($where)->count();
            $data = Db::name('certificate_list')->where($where)->order($order)->select()->toArray();
//            dd(Db::getLastSql());
            foreach($data as $key=>$val){
                $job = Db::name('certificate_job')->where('aid',aid)->where('id', 'in',$val['job_id'])->column('name');
                $data[$key]['job_name'] = $job?implode(',',$job):'暂无';
                $category = Db::name('certificate_category')->where('id','in',$val['cid'])->column('name');
                $data[$key]['cname'] = $category?implode(',',$category) :'暂无';
                $data[$key]['certificate_pic'] = explode(',',$val['certificate_pic']);
                $education_name = Db::name('certificate_education')->where('id', $data[$key]['education'])->value('name');
                $data[$key]['education'] = $education_name;   
                $data[$key]['isedit'] =1;
                if($this->user['isadmin'] ==0 &&  $val['admin_user'] ==0){
                    $len = mb_strlen($val['name'],'UTF-8');
                    if($len >= 3){
                        $name = mb_substr($val['name'],0,1,'UTF-8').' x x';
                    }elseif ($len ==2){
                        $name = mb_substr($val['name'],0,1,'UTF-8').' x';
                    }
                    $data[$key]['name'] = $name;
                    $data[$key]['tel'] = substr($val['tel'],0,7).'xxxx';
                    $data[$key]['isedit'] =0;
                }
               
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        
        $catelist = Db::name('certificate_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        $joblist = Db::name('certificate_job')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
        $educationlist = Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
        View::assign('catelist',$catelist);
        View::assign('joblist',$joblist);
        View::assign('educationlist',$educationlist);
        
        return View::fetch();
    }
   
	//编辑
	public function edit(){
        if(input('param.id')){
            $info = Db::name('certificate_list')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            $info['gettj'] = explode(',',$info['gettj']);
            $info['cid'] = explode(',',$info['cid']);
            $info['job_id'] = explode(',',$info['job_id']);
        }else{
            $info = array('id'=>'');
        }
        if(input('param.pid')) $info['pid'] = input('param.pid');
        $catelist = Db::name('certificate_category')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
        $joblist = Db::name('certificate_job')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
        View::assign('info',$info);
        View::assign('catelist',$catelist);
        $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
        
        View::assign('levellist',$levellist);
        View::assign('joblist',$joblist);
        $educationlist = Db::name('certificate_education')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
        View::assign('educationlist',$educationlist);
        
        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
        $info['gettj'] = implode(',',$info['gettj']);
		if($info['id']){
			Db::name('certificate_list')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑证书'.$info['id']);
		}else{
		    if($this->user['isadmin'] == 0) {
                $info['admin_user'] = $this->user['id'];
            }
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
          
			$id = Db::name('certificate_list')->insertGetId($info);
			\app\common\System::plog('添加证书'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setcheckst(){
		$st = input('post.st/d');
		$ids = input('post.id/a');
        $reason = input('post.reason');
		Db::name('certificate_list')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['ischecked'=>$st,'check_reason' => $reason]);
		\app\common\System::plog('证书改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('certificate_list')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog('证书删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    public function set()
    {
        if(request()->isAjax()){
            $info = input('post.info/a');
            if($info['gettj'])$info['gettj'] = implode(',',$info['gettj']);
            Db::name('certificate_set')->where('aid',aid)->where('bid',bid)->update($info);
            \app\common\System::plog('证书设置');
            return json(['status'=>1,'msg'=>'操作成功']);
        }
        $set = Db::name('certificate_set')->where('aid',aid)->where('bid',bid)->find();
        View::assign('info',$set);
        $defaultCat = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $defaultCat = $defaultCat ? $defaultCat : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $defaultCat)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);
        return View::fetch();
    }
    public function defaultSet()
    {
        $set = Db::name('certificate_set')->where('aid',aid)->where('bid',bid)->find();
        if(empty($set)){
            Db::name('certificate_set')->insert([
                'aid'=>aid,
                'bid'=>bid,
                'gettj'=>-1,
                'uplevel_text'=>'您还不是我们VIP会员，请尽快升级',
                'uplevel_url'=>'/pagesExt/my/levelup',
                'createtime'=>time()
            ]);
        }
    }
}
