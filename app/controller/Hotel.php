<?php
// JK客户定制

//custom_file(hotel)
// +----------------------------------------------------------------------
// | 酒店 基础设置
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Hotel extends Common
{
    public $text;
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
		$this->set = Db::name('hotel_set')->where('aid',aid)->find();
		$this->text = \app\model\Hotel::gettext(aid);
	}

	//系统设置
	public function index(){
        $info = Db::name('hotel')->where('aid',aid)->where('bid',bid)->find();
		$set= Db::name('hotel_set')->where('aid',aid)->find();
		if(!$info){
			$info=[];
		}
		if(!$set['textset']) {
			$textset = ['酒店'=>'酒店','间'=>'间','服务费'=>'打赏'];
		} else{
			$textset = json_decode($set['textset'],true);
		}   

		$clist = Db::name('hotel_category')->where('aid',aid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
		if($info['ptsheshi']){
			$info['ptsheshi'] = json_decode($info['ptsheshi'],true);
		}else{
			$info['ptsheshi'] = array(['icon'=>'','text'=>'']);
		}
		//var_dump($info);
		if($info['dikou_text']){
			$info['dikou_text'] = json_decode($info['dikou_text'],true);
		}else{
			$info['dikou_text'] =[['dikou_bl'=>'100','dikou_num'=>'1'],['dikou_bl'=>'30','dikou_num'=>'2'],['dikou_bl'=>'10','dikou_num'=>'3']];
		}
		if(!$info['formdata']){
			$info['formdata'] = json_encode([
				['key'=>'input','val1'=>'备注','val2'=>'选填，请输入备注信息','val3'=>'0'],	
			]);
		}
 
		$ptlenth = count($info['ptsheshi']);
		View::assign('clist',$clist);
		View::assign('textset',$textset);
		View::assign('info',$info);
		View::assign('bid',bid);
		View::assign('ptlenth',$ptlenth);

		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$bid = input('post.bid/d');
		$rs = Db::name('hotel')->where('aid',aid)->where('bid',$bid)->find();
		$ptsheshi = array_values(input('post.ptsheshi/a'));
		$info['textset'] = jsonEncode(input('post.textset/a'));
		$info['ptsheshi'] = jsonEncode($ptsheshi);
		if($info['money_dikou']==1){
			$info['dikou_text'] = jsonEncode(input('post.dkbl/a'));
		}else{
			$info['dikou_text']='';
		}
		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k]);
		}
		$info['formdata'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);
		if($rs){
			Db::name('hotel')->where('aid',aid)->where('bid',$bid)->update($info);
		}else{
			$info['aid'] = aid;
			$info['bid'] =  $bid?$bid:0;
			$info['createtime'] = time();
			Db::name('hotel')->insert($info);
		}
		\app\common\System::plog('酒店设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
	//选择酒店分类
	public function choosehotel(){
		//分类
		$clist = Db::name('hotel_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 

		View::assign('clist',$clist);
		return View::fetch();
	}
	//酒店列表
    public function getlist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];

			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('hotel')->where($where)->count();
			$data = Db::name('hotel')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = Db::name('hotel_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}

			foreach($data as $k=>$v){
				$roomlist = Db::name('hotel_room')->where('aid',aid)->where('hotelid',$v['id'])->select()->toArray();
				//查询最低的房型价格
				$where = [];
				$where[] =['hotelid','=',$v['id']];
				$nowtime = time();
				$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
				$room = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
				//echo db('hotel_room_prices')->getlastsql();
				$data[$k]['sell_price'] = $room['sell_price'];
	
				$v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
	
		View::assign('clist',$clist);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);

        $this->defaultSet();
		return View::fetch();
    }

	//获取酒店信息
	public function gethotel(){
		$proid = input('post.proid/d');
		$hotel = Db::name('hotel')->field('id,pic,name,sales,address,tag')->where('aid',aid)->where('id',$proid)->find();
		$hotel['tag'] = $hotel['tag']?explode(',',$hotel['tag']):[];
		//查询最低的房型价格
		$where = [];
		$where[] =['hotelid','=',$hotel['id']];
		$nowtime = time();
		$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
		$room = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
		//echo db('hotel_room_prices')->getlastsql();
		$hotel['sell_price'] = $room['sell_price'];
		return json(['hotel'=>$hotel]);
	}
	//上传
    public function uploadxy(){
		$file = request()->file('file');
		if($file){
			$remote = Db::name('sysset')->where('name','remote')->value('value');
			$remote = json_decode($remote,true);
			try {
			    $upload_type =config('app.upload_type');
				validate(['file'=>['fileExt:'.$upload_type]])->check(['file' => $file]);
                $rinfo = [];
                $rinfo['extension'] = strtolower($file->getOriginalExtension());
                $rinfo['name'] = $file->getOriginalName();
                $rinfo['bsize'] = $file->getSize();
                $filesizeMb = $rinfo['bsize']/1024/1024;
                $rinfo['hash'] = $file->sha1();
				$savename = \think\facade\Filesystem::putFile(''.aid,$file);//上传目录增加aid
				$filepath = 'upload/'.str_replace("\\",'/',$savename);
                $insert = array(
                    'aid' => $this->aid,
                    'bid' => bid,
                    'uid' => $this->uid,
                    'name' => '',
                    'dir' => date('Ymd'),
                    'url' => '',
                    'type' => 'jpg',
                    'width' => '',
                    'height' => '',
                    'bsize' => $rinfo['bsize'],
                    'hash' => $rinfo['hash'],
                    'createtime' => time(),
                    'gid'=> cookie('browser_gid') && cookie('browser_gid')!='-1' ? cookie('browser_gid') : '0'
                );

				$rinfo['url'] = PRE_URL.'/'.$filepath;
				if(!in_array($rinfo['extension'],config('app.upload_type_no_oss_arr')) ){
					$picurl = \app\common\Pic::tolocal($rinfo['url']);
                    if($picurl === false){
                        return json(['status'=>0,'msg'=>'附件设置未配置']);
                    }
                    $rinfo['url'] = $picurl;
                    $insert['name'] = $rinfo['name'];
                    $insert['url'] = $rinfo['url'];
                    $insert['type'] = $rinfo['extension'];
                    $insert['width'] = $rinfo['width'];
                    $insert['height'] = $rinfo['height'];
					$rinfo['id'] = Db::name('admin_upload')->insertGetId($insert);
				}
                \app\common\System::plog('上传预定协议文件：'.$rinfo['url']);
				return json(['status'=>1,'state'=>'SUCCESS','msg'=>'上传成功','url'=>$rinfo['url'],'info'=>$rinfo]);
			} catch (\think\exception\ValidateException $e) {
				return json(['status'=>0,'msg'=>$e->getMessage()]);
			}
		}else{
			$errorNo = $_FILES['file']['error'];
			switch($errorNo) {
				case 1:
					$errmsg = '上传的文件超过了 upload_max_filesize 选项限制的值';break;
				case 2:
					$errmsg = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';break;
				case 3:
					$errmsg = '文件只有部分被上传';break;
				case 4:
					$errmsg = '没有文件被上传';break;
				case 6:
					$errmsg = '找不到临时文件夹';break;
				case 7:
					$errmsg= '文件写入失败';break;
				default:
					$errmsg = '未知上传错误！';
			}
			return json(['status'=>0,'msg'=>$errmsg]);
		}
    }

}