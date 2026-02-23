<?php
namespace app\controller;
use app\BaseController;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use pay\wechatpay\WxPayV3;

class ApiMy extends ApiCommon
{
    public function initialize(){
        parent::initialize();
        $this->checklogin();
    }
    public function getWebinfo()
    {
        $rs = Db::name('admin_set')->where('aid',aid)->find();
        if($rs){
            return $this->json($rs);
        }else{
            return $this->json(['status'=>0,'msg'=>'页面不存在']);
        }
    }
    public function usercenter(){

        \app\custom\HuiDong::syncMemberSingle(aid,mid);

        if(getcustom('plug_tengrui')){
            //全部同步认证
            $tengrui = new \app\custom\TengRui(aid,mid);
            $tengrui->tb_dan_member($this->member['mpopenid']);
        }
        $pageid = input('param.id/d');
        $where = [];
        $where[] = ['aid','=',aid];
        if(!$pageid){
            $where[] = ['ishome','=',2];
        }else{
            $where[] = ['id','=',$pageid];
        }
        $pagedata = Db::name('designerpage')->where($where)->find();
        if($pagedata){
            $pageinfo = json_decode($pagedata['pageinfo'],true);
            $pagecontent = json_decode(\app\common\System::initpagecontent($pagedata['content'],aid,mid,platform),true);
            $rdata = [];
            $rdata['status'] = 1;
            $rdata['msg'] = '查询成功';
            $rdata['pageinfo'] = $pageinfo[0]['params'];
            $rdata['pagecontent'] = $pagecontent;
            $rdata['copyright'] = Db::name('admin')->where('id',aid)->value('copyright');
            return $this->json($rdata);
        }else{
            return $this->json(['status'=>0,'msg'=>'页面不存在']);
        }
    }

    public function set()
    {
        $uid = mid;
        if(request()->isPost()){
            $data = input('post.info/a');
            if(isset($data['pwd']) && $data['pwd']!=''){
                $data['pwd'] = md5($data['pwd']);
            }else{
                unset($data['pwd']);
            }
            if(isset($data['paypwd']) && $data['paypwd']!=''){
                $data['paypwd'] = md5($data['paypwd']);
            }else{
                unset($data['paypwd']);
            }
            try {
                Db::name('member')->where('id',$uid)->where('aid',aid)->update($data);
                return $this->json(['status'=>1,'msg'=>'修改成功']);
            } catch (\Exception $e) {
                return $this->json(['status'=>0,'msg'=>'修改失败:'.$e->getMessage()]);
            }
        }
        // Use field(*) to avoid "Column not found" errors if schema is outdated
        $userinfo = Db::name('member')->where('id',$uid)->where('aid',aid)->field('*')->find();
        if($userinfo){
             if(!isset($userinfo['tel']) || !$userinfo['tel']) $userinfo['tel'] = '';
             
             // Calculate computed fields expected by frontend
             $userinfo['haspaypwd'] = (isset($userinfo['paypwd']) && !empty($userinfo['paypwd'])) ? 1 : 0;
             $userinfo['set_alipay'] = (isset($userinfo['aliaccount']) && !empty($userinfo['aliaccount'])) ? 1 : 0;
             $userinfo['set_bank'] = (isset($userinfo['bankname']) && !empty($userinfo['bankname'])) ? 1 : 0;
             
             // Handle finance notice switches safely
             $userinfo['set_receive_notice'] = 0; 
             if(isset($userinfo['is_receive_finance_tmpl']) || isset($userinfo['is_receive_finance_sms'])){
                 $userinfo['set_receive_notice'] = 1;
             }
             if(!isset($userinfo['is_receive_finance_tmpl'])) $userinfo['is_receive_finance_tmpl'] = 0;
             if(!isset($userinfo['is_receive_finance_sms'])) $userinfo['is_receive_finance_sms'] = 0;

             // Remove sensitive data
             unset($userinfo['pwd']);
             unset($userinfo['salt']);
             unset($userinfo['paypwd']);

             return $this->json([
                 'status'=>1,
                 'userinfo'=>$userinfo,
                 'otherdata'=>[],
                 'register_forms'=>[]
             ]);
        }
        return $this->json(['status'=>0,'msg'=>'用户不存在']);
    }
    
    // Add setfield for single field updates and uploads
    public function setfield(){
        $post = input('post.');
        $data = [];
        $fieldList = ['realname','nickname','tel','sex','birthday','weixin','aliaccount','bankname','bankcarduser','bankcardnum','identity','supplier_id'];
        foreach($fieldList as $f){
            if(isset($post[$f])){
                $data[$f] = $post[$f];
            }
        }
        
        if($file = request()->file('headimg')){
            try{
                $savename = \think\facade\Filesystem::disk('public')->putFile('headimg', $file);
                if($savename){
                    $data['headimg'] = '/upload/'.str_replace('\\','/',$savename);
                }
            }catch(\Exception $e){
                return $this->json(['status'=>0,'msg'=>'上传失败:'.$e->getMessage()]);
            }
        }
        
        if(empty($data)){
             return $this->json(['status'=>0,'msg'=>'没有修改数据']);
        }
        
        // 使用请求参数中的mid，如果没有则使用当前登录用户的mid
        $memberId = isset($post['mid']) ? $post['mid'] : mid;
        
        try {
            Db::name('member')->where('aid',aid)->where('id',$memberId)->update($data);
        } catch (\Exception $e) {
             return $this->json(['status'=>0,'msg'=>'保存失败:'.$e->getMessage()]);
        }
        
        $return = ['status'=>1,'msg'=>'保存成功'];
        if(isset($data['headimg'])) $return['headimg'] = $data['headimg'];
        return $this->json($return);
    }

    // Add setFinanceNoticeSwitch
    public function setFinanceNoticeSwitch(){
        $field = input('post.field');
        $value = input('post.value');
        $dbfield = '';
        if($field == 'tmpl') $dbfield = 'is_receive_finance_tmpl';
        if($field == 'sms') $dbfield = 'is_receive_finance_sms';
        
        if($dbfield){
            try {
                Db::name('member')->where('aid',aid)->where('id',mid)->update([$dbfield=>$value]);
                return $this->json(['status'=>1,'msg'=>'设置成功']);
            } catch (\Exception $e) {
                return $this->json(['status'=>0,'msg'=>'设置失败:'.$e->getMessage()]);
            }
        }
        return $this->json(['status'=>0,'msg'=>'参数错误']);
    }

    // Add getRealnameSet
    public function getRealnameSet(){
        try {
            $set = Db::name('admin_set')->where('aid',aid)->field('realname_status')->find();
        } catch (\Exception $e) {
            $set = null;
        }
        if(!$set) $set = ['realname_status'=>0];
        
        $retSet = ['status'=>$set['realname_status']];

        // Use field(*) to ensure we get whatever columns exist
        $userinfo = Db::name('member')->where('aid',aid)->where('id',mid)->field('*')->find();
        
        // Ensure usercard field exists for frontend
        if($userinfo && !isset($userinfo['usercard']) && isset($userinfo['idcard'])){
            $userinfo['usercard'] = $userinfo['idcard'];
        }
        if($userinfo && !isset($userinfo['realname_status'])){
            $userinfo['realname_status'] = 0;
        }
        
        return $this->json(['status'=>1, 'set'=>$retSet, 'userinfo'=>$userinfo]);
    }

    // Add saveRealname
    public function saveRealname(){
        $info = input('post.info/a');
        $data = [];
        if(isset($info['realname'])) $data['realname'] = $info['realname'];
        if(isset($info['idcard'])) $data['idcard'] = $info['idcard'];
        if(isset($info['idcard_back'])) $data['idcard_back'] = $info['idcard_back'];
        
        if(empty($data)) return $this->json(['status'=>0,'msg'=>'没有数据']);
        
        try {
            Db::name('member')->where('aid',aid)->where('id',mid)->update($data);
            return $this->json(['status'=>1,'msg'=>'提交成功']);
        } catch (\Exception $e) {
            return $this->json(['status'=>0,'msg'=>'提交失败:'.$e->getMessage()]);
        }
    }

    // Add settel (GET)
    public function settel(){
        // Assuming no SMS verification is forced for now, or check admin settings
        $needsms = 0; 
        return $this->json(['status'=>1, 'needsms'=>$needsms]);
    }

    // Add settelsub (POST)
    public function settelsub(){
        $tel = input('post.tel');
        $smscode = input('post.smscode');
        if(empty($tel)) return $this->json(['status'=>0,'msg'=>'请输入手机号']);
        if(!preg_match("/^1\d{10}$/",$tel)){
            return $this->json(['status'=>0,'msg'=>'手机号格式不正确']);
        }
        // Check if tel exists
        $rs = Db::name('member')->where('aid',aid)->where('tel',$tel)->where('id','<>',mid)->find();
        if($rs){
             return $this->json(['status'=>0,'msg'=>'该手机号已存在']);
        }
        // TODO: Verify SMS code if needed

        try {
            Db::name('member')->where('aid',aid)->where('id',mid)->update(['tel'=>$tel]);
            return $this->json(['status'=>1,'msg'=>'修改成功']);
        } catch (\Exception $e) {
            return $this->json(['status'=>0,'msg'=>'修改失败:'.$e->getMessage()]);
        }
    }

    // Add setpwd
    public function setpwd(){
        if(request()->isPost()){
            $oldpwd = input('post.oldpwd');
            $pwd = input('post.pwd');
            if(empty($pwd)){
                 return $this->json(['status'=>0,'msg'=>'请输入新密码']);
            }
            $member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
            if(!empty($member['pwd'])){
                if(empty($oldpwd)){
                     return $this->json(['status'=>0,'msg'=>'请输入原密码']);
                }
                if(md5($oldpwd) != $member['pwd']){
                     return $this->json(['status'=>0,'msg'=>'原密码错误']);
                }
            }
            try {
                Db::name('member')->where('aid',aid)->where('id',mid)->update(['pwd'=>md5($pwd)]);
                return $this->json(['status'=>1,'msg'=>'修改成功']);
            } catch (\Exception $e) {
                return $this->json(['status'=>0,'msg'=>'修改失败:'.$e->getMessage()]);
            }
        }
        $member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
        $haspwd = !empty($member['pwd']) ? 1 : 0;
        return $this->json(['status'=>1,'haspwd'=>$haspwd]);
    }

    // Add paypwd
    public function paypwd(){
        if(request()->isPost()){
            $oldpaypwd = input('post.oldpaypwd');
            $paypwd = input('post.paypwd');
            if(empty($paypwd)){
                 return $this->json(['status'=>0,'msg'=>'请输入新支付密码']);
            }
            $member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
            if(!empty($member['paypwd'])){
                if(empty($oldpaypwd)){
                     return $this->json(['status'=>0,'msg'=>'请输入原支付密码']);
                }
                if(md5($oldpaypwd) != $member['paypwd']){
                     return $this->json(['status'=>0,'msg'=>'原支付密码错误']);
                }
            }
            try {
                Db::name('member')->where('aid',aid)->where('id',mid)->update(['paypwd'=>md5($paypwd)]);
                return $this->json(['status'=>1,'msg'=>'设置成功']);
            } catch (\Exception $e) {
                return $this->json(['status'=>0,'msg'=>'设置失败:'.$e->getMessage()]);
            }
        }
        $member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
        $haspwd = !empty($member['paypwd']) ? 1 : 0;
        return $this->json(['status'=>1,'haspwd'=>$haspwd]);
    }

    public function getmemberinfo(){
        $this->checklogin();
        $userinfo = Db::name('member')->where('id',mid)->where('aid',aid)->field('id,realname,tel,weixin,aliaccount,bankname,bankcarduser,bankcardnum,sex,province,city,area,headimg,nickname,money,score,commission,levelid')->find();
        if($userinfo){
            if(!isset($userinfo['tel']) || !$userinfo['tel']) $userinfo['tel'] = '';
            $levelname = Db::name('member_level')->where('id',$userinfo['levelid'])->value('name');
            $userinfo['levelname'] = $levelname;
            return $this->json(['status'=>1,'data'=>$userinfo]);
        }
        return $this->json(['status'=>0,'msg'=>'用户不存在']);
    }
    //我的订单
    public function orderlist(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        $where[] = ['delete','=',0];
        if($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            $where[] = ['status','=',3];
        }elseif($st == '4'){
            $where[] = ['status','=',4];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('shop_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $k=>$v){
            $v['prolist'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
            $datalist[$k] = $v;
        }
        return $this->json(['status'=>1,'data'=>$datalist]);
    }
    //订单详情
    public function orderdetail(){
        $id = input('param.id/d');
        $detail = Db::name('shop_order')->where('id',$id)->where('aid',aid)->where('mid',mid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['sendtime'] = $detail['sendtime'] ? date('Y-m-d H:i:s',$detail['sendtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $prolist = Db::name('shop_order_goods')->where('orderid',$id)->select()->toArray();
        $detail['prolist'] = $prolist;
        return $this->json(['status'=>1,'detail'=>$detail]);
    }
    //收货地址
    public function address(){
        if(request()->isPost()){
            $type = input('param.type');
            if($type == 'add'){
                $data = input('post.info/a');
                $data['aid'] = aid;
                $data['mid'] = mid;
                $data['createtime'] = time();
                if($data['isdefault'] == 1){
                    Db::name('member_address')->where('aid',aid)->where('mid',mid)->update(['isdefault'=>0]);
                }
                Db::name('member_address')->insert($data);
                return $this->json(['status'=>1,'msg'=>'添加成功']);
            }elseif($type == 'edit'){
                $id = input('param.id/d');
                $data = input('post.info/a');
                if($data['isdefault'] == 1){
                    Db::name('member_address')->where('aid',aid)->where('mid',mid)->update(['isdefault'=>0]);
                }
                Db::name('member_address')->where('id',$id)->where('aid',aid)->where('mid',mid)->update($data);
                return $this->json(['status'=>1,'msg'=>'修改成功']);
            }elseif($type == 'del'){
                $id = input('param.id/d');
                Db::name('member_address')->where('id',$id)->where('aid',aid)->where('mid',mid)->delete();
                return $this->json(['status'=>1,'msg'=>'删除成功']);
            }
        }
        $list = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->select()->toArray();
        return $this->json(['status'=>1,'data'=>$list]);
    }
    //充值
    public function recharge(){
        if(request()->isPost()){
            $money = input('post.money');
            if($money <= 0) return $this->json(['status'=>0,'msg'=>'充值金额必须大于0']);
            $ordernum = date('YmdHis').rand(1000,9999);
            $order = [];
            $order['aid'] = aid;
            $order['mid'] = mid;
            $order['ordernum'] = $ordernum;
            $order['money'] = $money;
            $order['createtime'] = time();
            $order['status'] = 0;
            $order['paytypeid'] = 1;
            $order['paytypename'] = '微信支付';
            $orderid = Db::name('member_recharge_order')->insertGetId($order);
            $paydata = \app\common\Wxpay::build(aid,mid,mid,$ordernum,$money,'充值');
            return $this->json($paydata);
        }
    }
    //余额提现
    public function withdraw(){
        if(request()->isPost()){
            $money = input('post.money');
            $paytype = input('post.paytype');
            if($money <= 0) return $this->json(['status'=>0,'msg'=>'提现金额必须大于0']);
            if($money > $this->member['money']) return $this->json(['status'=>0,'msg'=>'余额不足']);
            
            $data = [];
            $data['aid'] = aid;
            $data['mid'] = mid;
            $data['money'] = $money;
            $data['paytype'] = $paytype;
            $data['createtime'] = time();
            $data['status'] = 0;
            if($paytype == '微信钱包'){
                $data['account'] = $this->member['weixin'];
                $data['realname'] = $this->member['realname'];
            }elseif($paytype == '支付宝'){
                $data['account'] = $this->member['aliaccount'];
                $data['realname'] = $this->member['realname'];
            }elseif($paytype == '银行卡'){
                $data['bankname'] = $this->member['bankname'];
                $data['account'] = $this->member['bankcardnum'];
                $data['realname'] = $this->member['realname'];
            }
            $id = Db::name('member_withdrawlog')->insertGetId($data);
            \app\common\Member::addmoney(aid,mid,-$money,'余额提现');
            return $this->json(['status'=>1,'msg'=>'申请成功']);
        }
        $set = Db::name('admin_set')->where('aid',aid)->find();
        return $this->json(['status'=>1,'withdraw_min'=>$set['withdraw_min'],'withdraw_fee'=>$set['withdraw_fee'],'withdraw_weixin'=>$set['withdraw_weixin'],'withdraw_aliaccount'=>$set['withdraw_aliaccount'],'withdraw_bankcard'=>$set['withdraw_bankcard']]);
    }
    //我的拼团
    public function collage(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        if($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            $where[] = ['status','=',3];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('collage_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $k=>$v){
            $v['prolist'] = Db::name('collage_order_goods')->where('orderid',$v['id'])->select()->toArray();
            $datalist[$k] = $v;
        }
        return $this->json(['status'=>1,'data'=>$datalist]);
    }

    //升级会员
    public function levelup(){
        $levelid = input('param.levelid/d');
        $leveldata = Db::name('member_level')->where('id',$levelid)->find();
        if(!$leveldata) return $this->json(['status'=>0,'msg'=>'该等级不存在']);
        
        if(request()->isPost()){
            $ordernum = date('YmdHis').rand(1000,9999);
            $order = [];
            $order['aid'] = aid;
            $order['mid'] = mid;
            $order['ordernum'] = $ordernum;
            $order['title'] = '升级['.$leveldata['name'].']';
            $order['totalprice'] = $leveldata['apply_paymoney'];
            $order['createtime'] = time();
            $order['status'] = 0;
            $order['levelid'] = $levelid;
            $orderid = Db::name('member_levelup_order')->insertGetId($order);
            
            $rs = \app\common\Wxpay::build(aid,mid,$this->member['openid'],'升级['.$leveldata['name'].']',$ordernum,$leveldata['apply_paymoney'],'member_levelup_order');
            return $this->json($rs);
        }
        return $this->json(['status'=>1,'data'=>$leveldata]);
    }

    public function getUpAgree(){
        $pageid = input('param.id/d');
        $info = Db::name('article')->where('aid',aid)->where('id',$pageid)->find();
        return $this->json(['status'=>1,'data'=>$info]);
    }
}
