<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 财务-支出    custom_file(expend)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\Db;
class ApiAdminExpend extends ApiAdmin
{	
	public function initialize(){
		parent::initialize();
	}
	public function index(){
        $clist = Db::name('expend_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
        $cdata = array();
        foreach($clist as $c){
            $cdata[$c['id']] = $c['name'];
        }
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
		if(input('param.keyword')){
			$where[] = ['remark','like','%'.input('param.keyword').'%'];
		}
        if(input('param.ctime') ){
            $ctime = input('param.ctime');
            $where[] = ['createtime','>=',strtotime($ctime[0])];
            $where[] = ['createtime','<',strtotime($ctime[1]) ];
        }
		$datalist = Db::name('expend')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if($datalist){
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->column('id,aid,bid,un','id');
			foreach($datalist as $k => $v){
                $datalist[$k]['cname'] = $cdata[$v['cid']] ? $cdata[$v['cid']] : '未分类';
                if($datalist[$k]['createtime']) $datalist[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
                $datalist[$k]['adminname'] = $admin_user[$v['uid']]['un'] ? $admin_user[$v['uid']]['un'] : '已删除';
			}
			unset($v);
		}else{
			$datalist = [];
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}


    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('expend')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'');
        }
        $clist = Db::name('expend_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
        array_unshift($clist,['id'=>0,'name'=>'请选择分类']);
        $cateArr = Db::name('expend_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->column('name','id');

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['info'] = $info;
        $rdata['cateArr'] = $cateArr;
        $rdata['clist'] = $clist;
        return $this->json($rdata);
    }

    //保存
    public function save(){
        $info = input('post.info/a');
        if(input('param.id')){
            Db::name('expend')->where('aid',aid)->where('bid',bid)->where('id',input('param.id'))->update($info);
            \app\common\System::plog('编辑财务支出'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $info['uid'] = $this->uid;
            $id = Db::name('expend')->insertGetId($info);
            \app\common\System::plog('添加财务支出'.$id);
        }
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
    //删除
    public function del(){
        $id = input('post.id/d');
        Db::name('expend')->where('aid',aid)->where('bid',bid)->where('id','=',$id)->delete();
        \app\common\System::plog('财务支出删除'.$id);
        return $this->json(['status'=>1,'msg'=>'删除成功']);
    }

}