<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：lee网络
 * 授权域名：cs.400110.cn
 * 授权码：lee------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

//custom_file(member_tag) 
// +----------------------------------------------------------------------
// | 会员标签
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class MemberTag extends Common
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
				$order = 'sort,id';
			}
			$where = [['aid','=',aid]];
			$count = 0 + Db::name('member_tag')->where($where)->count();
			$data = Db::name('member_tag')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$memberlevel = Db::name('member_level')->where('aid',aid)->column('name','id');
			foreach($data as $k=>$v){
				$tj = array();
				if($v['regdatestatus']==1) $tj[]='注册会员时间'.$v['mindays'].'至'.$v['maxdays'].'天';
				if($v['levelstatus']==1) $tj[]='会员等级为'.$memberlevel[$v['levelid']];
				if($v['buystatus']==1) $tj[]='消费次数大于'.$v['buynum'].'次';
				if($v['buymoneystatus']==1) $tj[]='消费金额大于'.$v['buymoney'].'元';
				if($v['prostatus']==1) $tj[]='购买商品['.Db::name('shop_product')->where('id',$v['productids'])->value('name').']';
				 $i = 1;
				 foreach($tj as $key => $item) {
					if($i == 1) {
						$data[$k]['uptj'] .= $item;
					} else {
						$realtion = ' 或 <br/>';
						if($v['condition'] == 'and') {
							$realtion = ' 且 <br/>';
						}
						$data[$k]['uptj'] .= $realtion.$item;
					}
					$i++;
				}
                $data[$k]['member_tag_pic'] = false;
                }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('member_tag')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$productdata = array();
			if($info && $info['productids']){
				$productdata = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id','in',$info['productids'])->order('sort desc,id')->select()->toArray();
			}
		}else{
			$info = [];
		}
        $member_tag_pic = false;
        View::assign('level',$member_tag_pic);
		$level = Db::name('member_level')->where('aid',aid)->select()->toArray();
		View::assign('level',$level);
		View::assign('productdata',$productdata);
		View::assign('info',$info);
        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');

		if($info['id']){
			Db::name('member_tag')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\common\System::plog('编辑会员标签'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('member_tag')->insertGetId($info);
			\app\common\System::plog('添加会员标签'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('member_tag')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog('删除会员标签'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
