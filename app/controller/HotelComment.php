<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店价
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class HotelComment extends Common
{
	public $text;
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
	}
	//评价列表
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
			$where[] = ['bid','=',bid];
			if(input('param.content')) $where[] = ['content','like','%'.input('param.content').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
	
			$count = 0 + Db::name('hotel_comment')->where($where)->count();
			$data = Db::name('hotel_comment')->where($where)->page($page,$limit)->order($order)->select()->toArray();
	
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('text',$this->text);
        //$this->defaultSet();
		return View::fetch();
    }
	//评价审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$list = Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st]);
			$proComment = Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('hotelid',$v['hotelid'])->where('status',1)->avg('score');
			$comment_num = Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('hotelid',$v['hotelid'])->where('status',1)->count();
			if($comment_num==0) $proComment = 5;
			$haonum = Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('hotelid',$v['hotelid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($comment_num > 0){
				$haopercent = $haonum/$comment_num*100;
			}else{
				$haopercent = 100;
			}
			Db::name('hotel')->where('aid',aid)->where('bid',bid)->where('id',$v['hotelid'])->update(['comment_score'=>$proComment,'comment_num'=>$comment_num,'comment_haopercent'=>$haopercent]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//评价详情
	public function getdetail(){
		$detail= Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('id',$_POST['id'])->find();
		if($detail['content_pic']) $detail['content_pic'] = explode(',',$detail['content_pic']);
		$member = Db::name('member')->where('aid',aid)->where('id',$detail['mid'])->find();
		if(!$member) $member = ['nickname'=>$detail['nickname'],'headimg'=>$detail['headimg']];
		return json(['status'=>1,'detail'=>$detail,'member'=>$member]);
	}
	//评价回复
	public function reply(){
		$id = input('post.id/d');
		Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['reply_content'=>$_POST['content'],'reply_time'=>time()]);
		\app\common\System::plog($this->text['酒店'].'评价回复'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('hotel_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\common\System::plog($this->text['酒店'].'评价删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    public function edit()
    {
        
		$info['createtime'] = date('Y-m-d H:i:s');
		if(input('param.id')){
			$info = Db::name('hotel_comment')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$info['createtime'] = date('Y-m-d H:i:s',$info['createtime']);
		}
		View::assign('info',$info);
		return View::fetch();
	
    }
    function defaultSet(){
        $set = Db::name('hotel')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('hotel')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
