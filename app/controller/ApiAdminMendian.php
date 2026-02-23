<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 门店列表    custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
//管理员中心 - 门店列表
namespace app\controller;
use think\facade\Db;
class ApiAdminMendian extends ApiAdmin
{	
	public function initialize(){
		parent::initialize();
        if(!in_array(request()->action(),['searchCode','detail','decscore'])){
            if(bid != 0) die(json_encode(['status'=>-4,'msg'=>'无权限操作']));
        }
	}
	public function index(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.keyword')){
			$where[] = ['id|name|tel','like','%'.input('param.keyword').'%'];
		}
		$st = input('post.st');
		$where[] = ['check_status','=',$st];
		$datalist = Db::name('mendian')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as &$d){
			$member = Db::name('member')->field('headimg')->where('id',$d['mid'])->find();
			$d['headimg'] = $member['headimg'];
		}
		if($pagenum == 1){
			$count = Db::name('mendian')->where($where)->count();
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	
	public function detail(){
		 $detail = Db::name('mendian')->where('id',input('param.id/d'))->where('aid',aid)->find();
		 if(!$detail) $this->json(['status'=>0,'msg'=>'门店不存在']);
	     $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		 $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		 $detail['nickname'] = $member['nickname'];
		 $detail['headimg'] = $member['headimg'];

		 $rdata = [];
		 $rdata['detail'] = $detail;
		 return $this->json($rdata);
	}

	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		$mendian = Db::name('mendian')->where('aid',aid)->where('id',$id)->find();
		if(!$mendian) return json(['status'=>0,'msg'=>'信息不存在']);
	
		if($st == 1){
			Db::name('mendian')->where('aid',aid)->where('id',$id)->update(['check_status'=>$st,'status'=>1]);
			//修改会员等级
            $mendianset = Db::name('mendian_sysset')->where('aid',aid)->find();
            if(false){}else{
                $member = Db::name('member')->where('aid',aid)->where('id',$mendian['mid'])->update(['levelid'=>$mendianset['member_levelid']]);
            }
			
		}else{
			Db::name('mendian')->where('aid',aid)->where('id',$id)->update(['check_status'=>$st,'reason'=>$reason]);
		}
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的申请入驻通过' : '抱歉您的提交未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $mendian['name'].'申请';
		$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
        $tempconNew = [];
        $tempconNew['thing9'] = $mendian['name'].'申请';
        $tempconNew['thing2'] = ($st == 1 ? '已通过' : '未通过');
        $tempconNew['time3'] = date('Y年m月d日 H:i');
		$rs = \app\common\Wechat::sendtmpl(aid,$mendian['mid'],'tmpl_shenhe',$tmplcontent,m_url('yuyue/yuyue/apply'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $mendian['name'].'申请';
		$tmplcontent['phrase2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['thing4'] = $st == 1?'您的申请未通过':'您的申请已通过';
		$rs = \app\common\Wechat::sendwxtmpl(aid,$mendian['mid'],'tmpl_shenhe',$tmplcontent,'yuyue/yuyue/apply','');
		return json(['status'=>1,'msg'=>'审核成功']);
	}
	public function withdrawlog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$st = input('post.st');
		if($st=='all'){}else{
			$where[] = ['status','=',$st];
		}
		$datalist = Db::name('mendian_withdrawlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		foreach($datalist as &$d){
			$d['xqname'] = Db::name('mendian')->where('id',$d['mdid'])->value('xqname');
			$d['headimg'] = Db::name('member')->where('id',$d['mid'])->value('headimg');
		}
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function withdrawdetail(){
		$id = input('param.id');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',$id];
		$detail = Db::name('mendian_withdrawlog')->where($where)->find();
		$mendian = Db::name('mendian')->field('name,xqname,tel')->where('id',$detail['mdid'])->find();
		$detail['name'] = $mendian['name'];
		$detail['xqname'] =  $mendian['xqname'];
		$detail['tel'] =  $mendian['tel'];
		$detail['createtime'] = date('Y-m-d H:i:s', $detail['createtime']);
		$detail['headimg'] = Db::name('member')->where('id',$detail['mid'])->value('headimg');
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'detail'=>$detail]);
	}


	//提现记录改状态
    public function withdrawlogsetst(){

        $id = input('post.id/d');
        $st = input('post.st/d');
        $reason = input('post.reason');
        $info = Db::name('mendian_withdrawlog')->where('aid',aid)->where('id',$id)->find();
        $info['txmoney'] = dd_money_format($info['txmoney']);
        $info['money'] = dd_money_format($info['money']);
        if($st==10){//微信打款
            if($info['status']!=1) return json(['status'=>0,'msg'=>'已审核状态才能打款']);
            $rs = \app\common\Wxpay::transfers(aid,$info['mid'],$info['money'],$info['ordernum'],$info['platform'],t('余额').'提现');
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }else{
                Db::name('mendian_withdrawlog')->where('id',$id)->where('aid',aid)->update(['status'=>3,'reason'=>$reason,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
                //提现成功通知
                $tmplcontent = [];
                $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                $tmplcontent['remark'] = '请点击查看详情~';
                $tmplcontent['money'] = (string) round($info['money'],2);
                $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
                \app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
                //订阅消息
                $tmplcontent = [];
                $tmplcontent['amount1'] = $info['money'];
                $tmplcontent['thing3'] = $info['paytype'];
                $tmplcontent['time5'] = date('Y-m-d H:i');

                $tmplcontentnew = [];
                $tmplcontentnew['amount3'] = $info['money'];
                $tmplcontentnew['phrase9'] = $info['paytype'];
                $tmplcontentnew['date8'] = date('Y-m-d H:i');
                \app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                //短信通知
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                    \app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
                }
                \app\common\System::plog('佣金提现微信打款'.$id);
                return json(['status'=>1,'msg'=>$rs['msg']]);
            }
        }else{
            
            Db::name('mendian_withdrawlog')->where('id',$id)->where('aid',aid)->update(['status'=>$st,'reason'=>$reason]);
            if($st == 2){
                //驳回返还余额
                \app\common\Mendian::addmoney(aid,$info['mdid'],$info['txmoney'],'佣金提现返还');

                //提现失败通知
                $tmplcontent = [];
                $tmplcontent['first'] = '您的提现申请被商家驳回，可与商家协商沟通。';
                $tmplcontent['remark'] = $reason.'，请点击查看详情~';
                $tmplcontent['money'] = (string) round($info['txmoney'],2);
                $tmplcontent['time'] = date('Y-m-d H:i',$info['createtime']);
                \app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontent,m_url('pages/my/usercenter'));
                //订阅消息
                $tmplcontent = [];
                $tmplcontent['amount1'] = $info['txmoney'];
                $tmplcontent['time3'] = date('Y-m-d H:i',$info['createtime']);
                $tmplcontent['thing4'] = $reason;

                $tmplcontentnew = [];
                $tmplcontentnew['thing1'] = '提现失败';
                $tmplcontentnew['amount2'] = $info['txmoney'];
                $tmplcontentnew['date4'] = date('Y-m-d H:i',$info['createtime']);
                $tmplcontentnew['thing12'] = $reason;
                \app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixianerror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                //短信通知
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                    \app\common\Sms::send(aid,$tel,'tmpl_tixianerror',['reason'=>$reason]);
                }
                \app\common\System::plog('佣金提现驳回'.$id);
            }
            if($st==3){
                //提现成功通知
                $tmplcontent = [];
                $tmplcontent['first'] = '您的提现申请已打款，请留意查收';
                $tmplcontent['remark'] = '请点击查看详情~';
                $tmplcontent['money'] = (string) round($info['money'],2);
                $tmplcontent['timet'] = date('Y-m-d H:i',$info['createtime']);
                $tempconNew = [];
                $tempconNew['amount2'] = (string) round($info['money'],2);//提现金额
                $tempconNew['time3'] = date('Y-m-d H:i',$info['createtime']);//提现时间
                \app\common\Wechat::sendtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
                //订阅消息
                $tmplcontent = [];
                $tmplcontent['amount1'] = $info['money'];
                $tmplcontent['thing3'] = $info['paytype'];
                $tmplcontent['time5'] = date('Y-m-d H:i');

                $tmplcontentnew = [];
                $tmplcontentnew['amount3'] = $info['money'];
                $tmplcontentnew['phrase9'] = $info['paytype'];
                $tmplcontentnew['date8'] = date('Y-m-d H:i');
                \app\common\Wechat::sendwxtmpl(aid,$info['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
                //短信通知
                $member = Db::name('member')->where('id',$info['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                    \app\common\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$info['money']]);
                }
                \app\common\System::plog('佣金提现改为已打款'.$id);
            }
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}