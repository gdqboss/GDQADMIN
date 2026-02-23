<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：飞鲸网络
 * 授权域名：4.t.mixin100.cn
 * 授权码：ZnNpaTeHHFnuyMxIYcI
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

// +----------------------------------------------------------------------
// | 自动执行  每分钟执行一次 crontab -e 加入 */1 * * * * curl https://域名/?s=/ApiAuto/index/key/配置文件中的authtoken
// +----------------------------------------------------------------------
namespace app\controller;
use app\BaseController;
use think\facade\Db;
use think\facade\Log;
class ApiTglAuto extends BaseController
{
    public function initialize(){

	}
	public function kaijiang(){
		$aid = input('param.aid');
		$teamid = input('param.teamid');
		Log::write('---------ApiTglAuto kaijiang----------');
		Log::write(input('param.'));
		//die('kaijiang');
		\app\common\Tgl::kaijiang($aid,$teamid);
	}
	public function tongzhi(){
		$aid = input('param.aid');
		$teamid = input('param.teamid');
		Log::write('---------ApiTglAuto tongzhi----------');
		Log::write(input('param.'));
		//die('tongzhi');
		\app\common\Tgl::tongzhi($aid,$teamid);
	}
}