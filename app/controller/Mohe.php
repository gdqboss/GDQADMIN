<?php
// JK客户定制


// +----------------------------------------------------------------------
// | 魔盒前端
// +----------------------------------------------------------------------
namespace app\controller;
use app\BaseController;
use think\facade\Cookie;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;

class Mohe extends BaseController
{
	//首页框架
    public function index(){
        $aid = input('param.aid');
        $id = input('param.lid');
        $time = input('param.diandat');
        if(empty($time) || $time+3600 > time()) {
            $info = \db('mohe_link')->where('aid',$aid)->where('id',$id)->where('status',1)->find();
            // dd($info);
            header('Location:'.(string) $info['url']);die;
        }
       
        return View::fetch('mohe/index');
    }

}
