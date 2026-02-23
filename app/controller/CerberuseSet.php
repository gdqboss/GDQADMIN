<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 智能开门 custom_file(lot_cerberuse)
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class CerberuseSet extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    public function set(){
        $info = Db::name('cerberuse_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$info){
            Db::name('cerberuse_set')->insert(['aid'=>aid,'bid'=>bid]);
            $info = Db::name('cerberuse_set')->where('aid',aid)->where('bid',bid)->find();
        }
        
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        Db::name('cerberuse_set')->where('aid',aid)->where('bid',bid)->update($info);
        \app\common\System::plog('智能开门系统设置');
        return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
    }

}
