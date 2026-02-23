<?php


namespace app\controller;

use think\facade\Request;
use think\facade\Db;

class Base extends \app\BaseController
{
	public $aid;
	public $uid;
	public $user;
	public $mdid = 0;
	public $auth_data = "all";
	public $platform = "mp";
	public $xcxaid = 0;
	public function initialize()
	{
		$request = request();
		if ($request->controller() . "/" . $request->action() == "Backstage/index") {
			$this->checkauthkey();
		}
		
		// 权限检查
		$this->checkPermission();
		
		// 记录操作日志
		$this->recordOperationLog();
	}
	public function checkauthkey()
	{
		$domain = $_SERVER["HTTP_HOST"];
		return true;
		$client = new \GuzzleHttp\Client(["timeout" => 5, "verify" => false]);
	
try {
			$response = $client->request("POST", "https://www.jikym.cn/index/install2/checkdomain", ["form_params" => ["domain" => $domain]]);
			$rs = $response->getBody()->getContents();
			$rs = json_decode($rs, true);
			if ($rs && $rs["status"] == 0) {
				exit($rs["msg"]);
			}
		} catch (\Throwable $e) {
		}
	}
	
	// 权限检查
	private function checkPermission()
	{
		$request = Request::instance();
		$controller = $request->controller();
		$action = $request->action();
		$currentRoute = strtolower($controller . '/' . $action);
		
		// 跳过某些不需要权限检查的路由
		$skipRoutes = [
			'backstage/index',
			'login/index',
			'login/login',
			'login/logout',
			'barcodeinventory/save_product',
			'barcodeinventory/index',
			'barcodeinventory/edit',
			'barcodeinventory/inbound',
			'barcodeinventory/outbound',
			'barcodeinventory/del_product',
			'barcodeinventory/get_guige',
			'shopproduct/showchoosestoreproduct',
			'shopproduct/getstoreproductlist',
			'shopproduct/choosestoreproduct'
		];
		
		if (in_array($currentRoute, $skipRoutes)) {
			return true;
		}
		
		// 获取当前用户信息
		$this->uid = session('uid');
		$this->aid = session('aid');
		
		if ($this->uid) {
			$this->user = Db::name('admin_user')->where('id', $this->uid)->find();
			
			// 管理员直接通过
			if ($this->user['isadmin'] > 0) {
				return true;
			}
			
			// 检查用户权限
			if ($this->user['auth_type'] == 1) {
				// 角色权限
				$role = Db::name('admin_role')->where('id', $this->user['role_id'])->find();
				if ($role) {
					$authRules = explode(',', $role['auth_rule']);
					// 检查当前路由是否在权限列表中
					if (!in_array($currentRoute, $authRules)) {
						$this->error('您没有权限访问该页面');
					}
				}
			} else {
				// 自定义权限
				$authRules = explode(',', $this->user['auth_rule']);
				if (!in_array($currentRoute, $authRules)) {
					$this->error('您没有权限访问该页面');
				}
			}
		}
	}
	
	// 记录操作日志
	private function recordOperationLog()
	{
		$request = Request::instance();
		$controller = $request->controller();
		$action = $request->action();
		$currentRoute = $controller . '/' . $action;
		
		// 跳过某些不需要记录的日志
		$skipLogs = [
			'backstage/index',
			'login/index'
		];
		
		if (in_array($currentRoute, $skipLogs)) {
			return;
		}
		
		// 获取当前用户信息
		$uid = session('uid');
		$aid = session('aid');
		
		if ($uid) {
			$data = [
				'aid' => $aid,
				'uid' => $uid,
				'controller' => $controller,
				'action' => $action,
				'route' => $currentRoute,
				'ip' => $request->ip(),
				'create_time' => time(),
				'params' => json_encode($request->param())
			];
			
			// 异步记录日志，避免影响主流程
			$this->asyncLog($data);
		}
	}
	
	// 异步记录日志
	private function asyncLog($data)
	{
		// 简单实现，实际项目中可使用队列
		Db::name('admin_operation_log')->insert($data);
	}
}