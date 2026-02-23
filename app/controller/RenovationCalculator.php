<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 装修计算器 custom_file(renovation_calculator)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class RenovationCalculator extends Common
{	
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}

	//设置
	public function set(){
		if(request()->isAjax()){
			$signset = Db::name('renovation_calculator')->where('aid',aid)->find();
			$info = input('post.info/a');

			$citys = input('post.citys/a');
			$areaparam = input('post.areaparam/a');
			$areadata = array();
			foreach($citys as $k=>$city){
				$areadata[] = array(
					'region'=>$city,
					'areaparam'=>$areaparam[$k],
				);
			}
			$info['areadata'] = jsonEncode($areadata);

			Db::name('renovation_calculator')->where('aid',aid)->update($info);

			\app\common\System::plog('装修计算器设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>true]);
		}
		$info = Db::name('renovation_calculator')->where('aid',aid)->find();
		if(!$info){
			Db::name('renovation_calculator')->insert(['aid'=>aid,'guigedata'=>'[{"k":0,"title":"人工/辅材料","color":"#29d981","items":[{"k":0,"title":"泥工工程","gongshi":"[面积]*[类型参数]*[区域参数]*1.2+10"},{"k":1,"title":"木工工程","gongshi":"2"},{"k":2,"title":"油漆工程","gongshi":"3"},{"k":3,"title":"水电工程","gongshi":"4"},{"k":4,"title":"新房拆改/砌墙","gongshi":"5"}]},{"k":1,"title":"主材费","color":"#31b5ff","items":[{"k":0,"title":"瓷砖","gongshi":"6"},{"k":1,"title":"地板","gongshi":"7"},{"k":2,"title":"门","gongshi":"8"},{"k":3,"title":"厨房吊顶","gongshi":"9"},{"k":4,"title":"洁具","gongshi":"10"},{"k":5,"title":"橱柜","gongshi":"11"},{"k":6,"title":"灯具","gongshi":"12"},{"k":7,"title":"开关插座","gongshi":"13"}]},{"k":2,"title":"其他","color":"#795BE6","items":[{"k":0,"title":"保洁","gongshi":"10"},{"k":1,"title":"运输费","gongshi":"11"},{"k":2,"title":"垃圾下楼","gongshi":"12"},{"k":3,"title":"材料上楼","gongshi":"13"},{"k":4,"title":"安装费","gongshi":"14"},{"k":5,"title":"成品保护","gongshi":"15"},{"k":6,"title":"工程管理费","gongshi":"16"},{"k":7,"title":"税金","gongshi":"17"}]}]']);
			$info = Db::name('renovation_calculator')->where('aid',aid)->find();
		}
		View::assign('info',$info);
      
		return View::fetch();
	}
}