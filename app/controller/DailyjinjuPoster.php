<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 每日金句 - 每日金句海报 custom_file(daily_jinju)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class DailyjinjuPoster extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','dailyjinju')->where('platform',$type)->order('id')->find();

		if(!$posterset){
			$data_product_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '115px','top' => '455px','type' => 'qrmp','width' => '94px','height' => '94px','size' => '',],
					['left' => '30px','top' => '105px','type' => 'pro_img','width' => '285px','height' => '100px'],
					['left' => '30px','top' => '233px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[每日金句内容]'],
					['left' => '34px','top' => '6px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '6px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#000','content' => '[昵称]'],
					['left' => '216px','top' => '339px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#000','content' => '[平台名称]'],
					['left' => '129px','top' => '63px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#000','content' => '[标题]'],
					['left' => '48px','top' => '372px','type' => 'textarea','width' => '135px','height' => '16px','size' => '14px','color' => '#000','content' => '[感悟]']
				]
			]);
			$data_product_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '115px','top' => '455px','type' => 'qrwx','width' => '94px','height' => '94px','size' => '',],
					['left' => '30px','top' => '105px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '233px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[每日金句内容]'],
					['left' => '34px','top' => '6px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '6px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#000','content' => '[昵称]'],
					['left' => '216px','top' => '339px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#000','content' => '[平台名称]'],
					['left' => '129px','top' => '63px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#000','content' => '[标题]'],
					['left' => '48px','top' => '372px','type' => 'textarea','width' => '135px','height' => '16px','size' => '14px','color' => '#000','content' => '[感悟]']
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'dailyjinju','platform'=>'app','content'=>$data_product_mp]);

			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','dailyjinju')->where('platform',$type)->order('id')->find();
		}


		$posterdata = json_decode($posterset['content'],true);

		$poster_bg = $posterdata['poster_bg'];
		$poster_data = $posterdata['poster_data'];

		View::assign('type',$type);
		View::assign('poster_bg',$poster_bg);
		View::assign('poster_data',$poster_data);
		return View::fetch();
    }
	public function save(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$poster_bg = input('post.poster_bg');
		$poster_data = input('post.poster_data');
		$data_index = ['poster_bg'=>$poster_bg,'poster_data'=>json_decode($poster_data)];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','dailyjinju')->where('platform',$type)->order('id')->find();
		Db::name('admin_set_poster')->where('id',$posterset['id'])->update(['content'=>json_encode($data_index)]);
		if(input('post.clearhistory') == 1){
			Db::name('member_poster')->where('aid',aid)->where('type','dailyjinju')->where('posterid',$posterset['id'])->delete();
			$msg = '保存成功';
		}else{
			$msg ='保存成功';
		}
		\app\common\System::plog('每日金句海报设置');
		return json(['status'=>1,'msg'=>$msg,'url'=>true]);
	}
}