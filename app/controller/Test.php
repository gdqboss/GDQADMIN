<?php
// JK客户定制

// +----------------------------------------------------------------------
// | test
// +----------------------------------------------------------------------
namespace app\controller;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class Test extends BaseController
{
	public function index(){
		$s = 'Aa在县城中心zZ';
		$rs = preg_match("/^[\x41-\x5a\x61-\x7a\x80-\xff]+$/", $s);
		var_dump($rs);
	}
}