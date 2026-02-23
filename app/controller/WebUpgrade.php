<?php

namespace app\controller;

class WebUpgrade extends Base
{
	public function initialize()
	{
		parent::initialize();
		$this->uid = session("BST_ID");
		$this->user = db("admin_user")->where(["id" => $this->uid])->find();
		if (!session("BST_ID") || !$this->user || $this->user["isadmin"] != 2) {
			showmsg("无访问权限");
		}
	}
	public function index()
	{
		$config = (include "config.php");
		$authkey = $config["authkey"];
		$domain = $_SERVER["HTTP_HOST"];
		$rs = request_post("https://www.diandashop.com/index/upgrade2/getversion", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn']);
		$rsdata = json_decode($rs, true);
		if ($rsdata["status"] != 1) {
			showmsg($rs);
		}
		$newversion = $rsdata["version"];
		$myversion = file_get_contents(ROOT_PATH . "version.php");
		\think\facade\View::assign("newversion", $newversion);
		\think\facade\View::assign("myversion", $myversion);
		\think\facade\View::assign("remark", $rsdata["remark"]);
		if ($newversion != $myversion) {
			\think\facade\View::assign("needupgrade", 1);
		} else {
			\think\facade\View::assign("needupgrade", 0);
		}
		return \think\facade\View::fetch();
	}
	public function getfilllist()
	{
		set_time_limit(0);
		ini_set("memory_limit", "-1");
		$config = (include "config.php");
		$authkey = $config["authkey"];
		$domain = $_SERVER["HTTP_HOST"];
		$rs = request_post("https://www.diandashop.com/index/upgrade2/filelist", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn']);
		$rsdata = json_decode($rs, true);
		if ($rsdata["status"] != 1) {
			return $rsdata;
		}
		$filelist = $rsdata["filelist"];
		$newfileArr = [];
		$modifyfileArr = [];
		foreach ($filelist as $k => $file) {
			$fullfile = ROOT_PATH . $file["name"];
			$hash = $file["hash"];
			if (!file_exists($fullfile)) {
				$newfileArr[] = $file["name"];
			} else {
				if (md5_file($fullfile) != $hash) {
					$modifyfileArr[] = $file["name"];
				}
			}
		}
		$allfile = array_merge($newfileArr, $modifyfileArr);
		session("upgradeallfile", $allfile);
		return json(["status" => 1, "newfileArr" => $newfileArr, "modifyfileArr" => $modifyfileArr, "allfile" => $allfile]);
	}
	public function doup()
	{
		set_time_limit(0);
		ini_set("memory_limit", "-1");
		$config = (include "config.php");
		$authkey = $config["authkey"];
		$domain = $_SERVER["HTTP_HOST"];
		$allfile = session("upgradeallfile");
		foreach ($allfile as $k => $file) {
			if ($file != "version.php") {
				$fullfile = ROOT_PATH . $file;
				if (!is_dir(dirname($fullfile))) {
					@mkdir(dirname($fullfile), 511, true);
				}
				$content = request_post("https://www.diandashop.com/index/upgrade2/getfilecontent", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn', "file" => $file]);
				file_put_contents($fullfile, $content);
			}
		}
		require "upgrade.php";
		$content = request_post("https://www.diandashop.com/index/upgrade2/getfilecontent", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn', "file" => "version.php"]);
		file_put_contents("version.php", $content);
		session("upgradeallfile", null);
		request_post("https://www.diandashop.com/index/upgrade2/unlinkdir", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn']);
		\app\common\System::plog("系统升级");
		return json(["status" => 1, "msg" => "升级成功"]);
	}
	public function updatefile()
	{
		$config = (include "config.php");
		$authkey = $config["authkey"];
		$domain = $_SERVER["HTTP_HOST"];
		$file = input("param.filename");
		if ($file != "version.php") {
			$fullfile = ROOT_PATH . $file;
			if (!is_dir(dirname($fullfile))) {
				@mkdir(dirname($fullfile), 511, true);
			}
			$content = request_post("https://www.diandashop.com/index/upgrade2/getfilecontent", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn', "file" => $file]);
			file_put_contents($fullfile, $content);
		}
	}
	public function doupsql()
	{
		$config = (include "config.php");
		$authkey = $config["authkey"];
		$domain = $_SERVER["HTTP_HOST"];
		require "upgrade.php";
		$this->douph5();
		$content = request_post("https://www.diandashop.com/index/upgrade2/getfilecontent", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn', "file" => "version.php"]);
		file_put_contents("version.php", $content);
		\app\common\System::plog("系统升级");
		return json(["status" => 1, "msg" => "升级成功"]);
	}
	public function douph5()
	{
		$html = file_get_contents(ROOT_PATH . "/h5/index.html");
		$adminlist = \think\facade\Db::name("admin")->where("1=1")->select()->toArray();
		foreach ($adminlist as $admin) {
			$thishtml = str_replace("var uniacid=1;", "var uniacid=" . $admin["id"] . ";", $html);
			file_put_contents(ROOT_PATH . "h5/" . $admin["id"] . ".html", $thishtml);
		}
		return json(["status" => 1, "msg" => "升级成功"]);
	}
	public function douph5test()
	{
		$h5indexhtml = file_get_contents(ROOT_PATH . "/h5/index.html");
		$h5indexhtml = str_replace("点大商城", "", $h5indexhtml);
		$h5indexhtml = str_replace("</title><script>", "</title><script>var uniacid=1;var siteroot = \"https://\"+window.location.host;", $h5indexhtml);
		file_put_contents(ROOT_PATH . "/h5/index.html", $h5indexhtml);
		preg_match("/static\\/js\\/index\\.[a-z0-9]+\\.js/", $h5indexhtml, $matches);
		$indexjs = $matches[0];
		$indexjscontent = file_get_contents(ROOT_PATH . "/h5/" . $indexjs);
		$indexjscontent = preg_replace_callback("/uniacid\\:\\\"[0-9]*\\\",siteroot\\:\\\"[^\"]*\\\"/", function ($matches) {
			return "uniacid:uniacid,siteroot:siteroot";
		}, $indexjscontent);
		file_put_contents(ROOT_PATH . "/h5/" . $indexjs, $indexjscontent);
		return $this->douph5();
	}
	public function getupgradelog()
	{
		$config = (include "config.php");
		$authkey = $config["authkey"];
		$domain = $_SERVER["HTTP_HOST"];
		$rs = request_post("https://www.diandashop.com/index/upgrade2/getupgradelog", ["authkey" => $authkey, "authdomain" => '4.t.mixin100.cn']);
		$rsdata = json_decode($rs, true);
		return json($rsdata);
	}
}