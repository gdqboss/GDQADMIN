<?php
// JK客户定制

// custom_file(lot_cerberuse)
// +----------------------------------------------------------------------
// | 智能开门（塞伯罗斯）
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Cerberuse extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无操作权限');
    }

    //列表
    public function index(){
        $this->defaultSet();
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
            if(input('param.title')) $where[] = ['title','like','%'.input('param.title').'%'];
            if(input('?param.status') && input('param.status')!==''){
                $where[] = ['status','=',input('param.status')];
            }
            $count = 0 + Db::name('cerberuse')->where($where)->count();
            $data = Db::name('cerberuse')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('cerberuse')->where('aid',aid)->where('id',input('param.id/d'))->find();
            $info['yyzhouqi'] = explode(',',$info['yyzhouqi']);
        }else{
            $info = ['id'=>''];
        }
        View::assign('info',$info);
        return View::fetch();
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        //保持设备ID 唯一性

        //生成二维码
        $qrcontent = [
            'UID' => $info['device_id'],
            'DevPwd' => '666666',
            'CmdCode' => 5
        ];
        if($info['server_ip']){
            $qrcontent['ServerIP'] = $info['server_ip'];
        }
        if($info['view_id']){
            $qrcontent['ViewId'] = $info['view_id'];
        }
        if($info['u_key']){
            $qrcontent['UKey'] = $info['u_key'];
        }
        if($info['timeout']){
            $qrcontent['Timeout'] = $info['timeout'];
        }
        if($info['relay1_time']){
            $qrcontent['Relay1Time'] = $info['relay1_time'];
        }
        if($info['relay1_type'] !=''){
            $qrcontent['Relay1Type'] = $info['relay1_type'];
        }
        if($info['net_mode'] !='' ){
            $qrcontent['NET_MODE'] = $info['net_mode'];
            if($info['net_mode'] ==0){
                if($info['dhcp'] ==1){
                    
                }else{
                    $qrcontent['IP'] = $info['ip'];
                    $qrcontent['Mask'] = $info['mask'];
                    $qrcontent['Gateway'] = $info['gateway'];
                    $qrcontent['DNS'] = $info['dns'];
                }
                $qrcontent['DHCP'] = $info['dhcp'];
            }elseif ($info['net_mode'] ==1) {
                $qrcontent['WIFI_SSID'] = $info['wifi_ssid'];
                $qrcontent['WIFI_PASSWORD'] = $info['wifi_password'];
                $qrcontent['WIFI_DHCP'] = $info['wifi_dhcp'];
            }
        }
        $qrcontent = json_encode($qrcontent,JSON_UNESCAPED_SLASHES);
        $qrcontent_str = '_CB_CONFIG_S02E:'.$qrcontent;
        $url = '/upload/'.aid.'/qrcode/cerberuse/'.$info['device_id'].'.jpg';
        $qrcode = createqrcode($qrcontent_str,'',aid,$url);
       
        $info['qrcode'] = $qrcode;
       
        $info['yyzhouqi'] = implode(',',$info['yyzhouqi']);
        if($info['id']){
            Db::name('cerberuse')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\common\System::plog('编辑智能开门'.$info['id']);
        }else{
            $have_device = Db::name('cerberuse')->where('aid',aid)->where('device_id',$info['device_id'])->find();
            if($have_device){
                return json(['status'=>0,'msg'=>'设备已添加']);
            }
            $info['aid'] = aid;
            $info['createtime'] = time();
            $id = Db::name('cerberuse')->insertGetId($info);
            \app\common\System::plog('添加智能开门设备'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('cerberuse')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除智能开门设备'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    function defaultSet(){
        $set = Db::name('cerberuse_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('cerberuse_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}