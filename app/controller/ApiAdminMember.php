<?php
// JK客户定制

//管理员中心 - 会员列表
namespace app\controller;
use think\facade\Db;
class ApiAdminMember extends ApiAdmin
{	
	public function initialize(){
		parent::initialize();
        if(getcustom('member_business')){
            if(bid != 0){
                $action = request()->action();
                if(!in_array('MemberBusiness',$this->auth_data)){
                    showmsg('无访问权限');
                }
                if(!in_array($action,['index','detail','recharge','addscore','remark','changelv','history','memberlevel','searchCode','changepid'])){
                    die(json_encode(['status'=>-4,'msg'=>'无权限操作']));
                }
            }
        }else{
            if(!in_array(request()->action(),['searchCode','detail','decscore'])){
                if(bid != 0) die(json_encode(['status'=>-4,'msg'=>'无权限操作']));
            }
        }
	}
	public function index(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
		if(input('param.keyword')){
			$where[] = ['id|nickname|realname|tel','like','%'.input('param.keyword').'%'];
		}
		if(input('param.id')){
			$where[] = ['id','=',input('param.id/d')];
		}
		$datalist = Db::name('member')->field('id,aid,bid,nickname,realname,headimg,sex,tel,money,score,levelid,createtime,last_visittime,province,city,remark')
            ->where($where)->page($pagenum,$pernum)->order('last_visittime desc,id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$v['createtime']);
            $datalist[$k]['last_visittime'] = $v['last_visittime'] ? date('Y-m-d H:i',$v['last_visittime']) : '暂无';
            if(getcustom('admin_login_user')){
                $datalist[$k]['can_login'] = 1;
            }else{
                $datalist[$k]['can_login'] = 0;
            }
            $moeny_weishu = 2;
            if(getcustom('member_money_weishu')){
                $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
            }
            $datalist[$k]['money'] = dd_money_format($v['money'],$moeny_weishu);
		}
		if($pagenum == 1){
			$count = Db::name('member')->where($where)->count();
		}
        $is_add_member = 0;
        if(getcustom('member_add')){
            $is_add_member = 1;
        }
		$rdata = [];
		$rdata['is_add_member'] = $is_add_member;
		$rdata['status'] = 1;
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['auth_data'] = $this->auth_data;
		return $this->json($rdata);
	}
	//会员详情
	public function detail(){
		$mid = input('param.mid/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            //兼容处理
            if(bid > 0 && !in_array('member_code_buy',json_decode($this->user['hexiao_auth_data'],true))){
                $where[] = ['bid','=',bid];
            }
        }else{
            if(bid > 0 && !in_array('member_code_buy',json_decode($this->user['hexiao_auth_data'],true))){
                //多商户仅此功能可查看会员详情
                return $this->json(['status'=>0,'msg'=>'非法操作']);
            }
        }
        $field = 'id,aid,bid,pid,nickname,realname,headimg,sex,tel,money,score,levelid,createtime,last_visittime,province,city,remark,identity,supplier_id';
        if(getcustom('mendian_member_levelup_fenhong')){
            $field.= ',mdid';
        }
        $where[] = ['aid','=',aid];
		$member = db('member')->field($field)
            ->where($where)->find();
        
        // 获取上级团长信息
        $member['pnickname'] = '';
        if($member['pid'] > 0){
            // 直接使用原pid值，不覆盖，同时添加aid条件
            $parent_member = db('member')->field('id,nickname')->where(['id'=>$member['pid'],'aid'=>$member['aid']])->find();
            if($parent_member){
                $member['pnickname'] = $parent_member['nickname'];
            }
        }
        
		$member['levelname'] = db('member_level')->where(['id'=>$member['levelid']])->value('name');
		$member['createtime'] = date('Y-m-d H:i',$member['createtime']);
		$rdata = [];

		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;

		$rdata['levelList'] = Db::name('member_level')->field('id,name')->where('aid',aid)->where('cid',$default_cid)->order('sort,id')->select()->toArray();
		
		//查看下级订单
		$ordershow=false;
		if(getcustom('commissionranking')){
			$ordershow=true;
		}
		$member['ordercount'] = 0 + Db::name('shop_order')->where('aid',aid)->where('mid',$mid)->count();
		$member['showrichinfo'] = false;
		if(getcustom('member_richinfo')){
			$member['showrichinfo'] = true;
		}
        $moeny_weishu = 2;
        if(getcustom('member_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
        }
        if(getcustom('mendian_member_levelup_fenhong')){
            $member['mdname'] = Db::name('mendian')->where('aid',aid)->where('id', $member['mdid'])->value('name');
            $member['mendian_member_levelup_fenhong'] = true;
        }
        $member['money'] = dd_money_format($member['money'],$moeny_weishu);
		$rdata['ordershow'] = $ordershow;
		$rdata['member'] = $member;
		return $this->json($rdata);
	}
	//充值
	public function recharge(){
		$mid = input('post.rechargemid/d');
		$money = floatval(input('post.rechargemoney'));
        $type  = input('post.type')?input('post.type'):'';
        if($type == 'consume'){
            $actionname = '消费';
        }else{
            if($money>=0){
                $actionname = '充值';
            }else{
                $actionname = '扣除';
            }
        }
		if($money == 0){
            return $this->json(['status'=>0,'msg'=>'请输入'.$actionname.'金额']);
		}
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
		$info = db('member')->where($where)->find();
		if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该会员'.t('会员')]);
        \app\common\Member::addmoney(aid,$mid,$money,'商家'.$actionname.'，操作员：'.$this->user['un']);
        \app\common\System::plog('手机端'.$this->user['un'].'给会员'.$mid.$actionname.'金额：'.$money);
        if(getcustom('sms_temp_money_recharge')){
            $tel = $info['tel'];
            if($tel && $money >0){
                $rs = \app\common\Sms::send(aid,$tel,'tmpl_money_recharge',['money'=>$money,'givemoney'=>0]);
            }
        }
		return $this->json(['status'=>1,'msg'=>$actionname.'成功']);
	}
	//改积分
	public function addscore(){
		$mid = input('post.rechargemid/d');
		$score = floatval(input('post.rechargescore'));
		if($score == 0){
			return $this->json(['status'=>0,'msg'=>'请输入'.t('积分').'数量']);
		}
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
		$info = db('member')->where($where)->find();
		if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
        $rs = \app\common\Member::addscore(aid,$mid,$score,'商家增加'.t('积分'),'admin',bid);
        if($rs['status'] == 0) return $this->json($rs);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
    //改积分
    public function decscore(){
        if(!in_array('member_code_buy',json_decode($this->user['hexiao_auth_data'],true))){
            return $this->json(['status'=>0,'msg'=>'非法操作']);
        }
        if(bid > 0){
            if(!getcustom('business_update_member_score'))
                return $this->json(['status'=>0,'msg'=>'非法操作']);
        }
        $mid = input('post.rechargemid/d');
        $score = floatval(input('post.rechargescore'));
        if($score == 0){
            return $this->json(['status'=>0,'msg'=>'请输入'.t('积分').'数量']);
        }
        $where = [];
        $where[] = ['id','=',$mid];
        $where[] = ['aid','=',aid];
        $info = db('member')->where($where)->find();
        if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
        $rs = \app\common\Member::addscore(aid,$mid,$score,'商家消费，扣除'.t('积分'),'admin',bid);
        if($rs['status'] == 0) return $this->json($rs);
        return $this->json(['status'=>-4,'msg'=>'操作成功','url'=>'/admin/index/index']);
    }
    //修改上级关系
    public function changepid(){
        $mid = input('post.mid/d');
        $pid = input('post.pid/d');
        if(!$mid && $mid!==0){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $member = Db::name('member')->where($where)->find();
        if(!$member){
            return $this->json(['status'=>0,'msg'=>t('会员').'不存在']);
        }

        $new_path = '';
        if($pid>0){
            $parent = Db::name('member')->where('aid',aid)->where('id',$pid)->find();
            if(!$parent){
                return $this->json(['status'=>0,'msg'=>'上级ID不存在']);
            }
            //防止设置为自身或其下级，造成循环关系
            $parent_chain = [];
            if(!empty($parent['path'])){
                $parent_chain = explode(',', $parent['path']);
            }
            if($pid == $mid || in_array($mid,$parent_chain)){
                return $this->json(['status'=>0,'msg'=>'不可设置为自身或下级']);
            }
            $new_path = trim(($parent['path']?($parent['path'].','):'').$parent['id'],',');
        }else{
            $pid = 0;
            $new_path = '';
        }

        Db::name('member')->where($where)->update(['pid'=>$pid,'path'=>$new_path]);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
	//备注
	public function remark(){
		$mid = input('post.remarkmid/d');
		$remark = input('post.remark');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
		db('member')->where($where)->update(['remark'=>$remark]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//修改等级
	public function changelv(){
		$mid = input('post.changemid/d');
	$levelid = input('post.levelid');
    $confirm = input('post.confirm', 0);
	Db::startTrans();
        $member = Db::name('member')->where('id',$mid)->find();
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        
        // 检查是否参与过团购
        if($levelid > 1){
            if(Db::name('collage_order')->where('aid',aid)->where('mid',$member['id'])->count() && $confirm != 1){
                return $this->json(['status'=>-1,'msg'=>'该用户参与过团购，是否确认继续修改等级？','confirm_needed'=>1]);
            }
        }
        
	Db::name('member')->where($where)->update(['levelid'=>$levelid]);
        if($levelid != $member['levelid']){
            //插入级别变动记录
            $level_sort = Db::name('member_level')->where('aid',aid)->column('sort','id');
            $level_type = $level_sort[$levelid]>$level_sort[$member['levelid']]?'0':'1';
            $remark =  $level_sort[$levelid]>$level_sort[$member['levelid']]?'后台升级':'后台降级';
            $order = [
                'aid' => $member['aid'],
                'mid' => $member['id'],
                'from_mid' => $member['id'],
                'pid'=>$member['pid'],
                'levelid' => $levelid,
                'title' => t('后台修改'),
                'totalprice' => 0,
                'createtime' => time(),
                'levelup_time' => time(),
                'beforelevelid' => $member['levelid'],
                'form0' => '类型^_^' . $remark,
                'platform' => '',
                'status' => 2,
                'type' => $level_type
            ];
            Db::name('member_levelup_order')->insert($order);
        }
		\app\common\Wechat::updatemembercard(aid,$mid);
        Db::commit();
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

    public function history(){
        if(input('param.id/d') < 1){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;

        $mid = input('param.id/d');
        $where = [];
        $where[] = ['id','=',$mid];
        if(getcustom('member_business')){
            if(bid){
                $where[] = ['bid','=',bid];
            }
        }
        $where[] = ['aid','=',aid];
        $count = Db::name('member')->where($where)->count('id');
        if(!$count){
             return json(['status'=>0,'msg'=>t('会员').'不存在']);
        }

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',$mid];
        $datalist = Db::name('member_history')->field('id,proid,from_unixtime(createtime)createtime,type')->where($where)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $k=>$v){
            $product = [];
            if($v['type'] == 'shop'){
                $product = Db::name('shop_product')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'yuyue'){
                $product = Db::name('yuyue_product')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'collage'){
                $product = Db::name('collage_product')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'scoreshop'){
                $product = Db::name('scoreshop_product')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'choujiang'){
                $product = Db::name('choujiang_product')->where('id',$v['proid'])->find();
                $product['market_price'] = $product['sell_price'];
                $product['sell_price'] = $product['min_price'];
            }
            if(!$product){
                Db::name('member_history')->where('id',$v['id'])->delete();
                unset($datalist[$k]);
            }else{
                $datalist[$k]['product'] = $product;
            }
        }
        if(request()->isPost()){
            return $this->json(['status'=>1,'data'=>$datalist]);
        }
        $count = Db::name('member_history')->where($where)->count();

        $rdata = [];
        $rdata['count'] = $count;
        $rdata['datalist'] = $datalist;
        $rdata['pernum'] = $pernum;
        return $this->json($rdata);
    }
    public function searchCode()
    {   
        if(!in_array('member_code_buy',json_decode($this->user['hexiao_auth_data'],true))){
            return $this->json(['status'=>0,'msg'=>'非法操作']);
        }
        if(!input('?param.code')){
            return $this->json(['status'=>0,'msg'=>'请输入会员码']);
        }

        $code = input('param.code');
        $where = [];
        $where[] = ['member_code','=',$code];
        $where[] = ['aid','=',aid];
        $member = Db::name('member')->where($where)->find();
        if(empty($member)){
            return $this->json(['status'=>0,'msg'=>'会员不存在，请检查会员码']);
        }

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['mid'] = $member['id'];
        return $this->json($rdata);
    }

    //管理员登录会员中心,不太安全 TODO
    public function adminLoginUser(){
	    if(getcustom('admin_login_user')){
            $mid = input('mid');
            $str = input('str');
            $where = [];
            $where[] = ['id','=',$mid];
            $where[] = ['aid','=',aid];
            $member = Db::name('member')->where($where)->find();
            $verify_str = $member['id'].$member['tel'];
            if(!$member || $verify_str!=$str){
                return $this->json(['status'=>0,'msg'=>'无权限操作']);
            }
            cache($this->sessionid.'_mid',$mid,7*86400);
            Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$mid,'session_id'=>$this->sessionid]);
        }

    }

	//详细介绍
	public function richinfo(){
        $mid = input('param.mid/d');
        $where = [];
        $where[] = ['id','=',$mid];
        $where[] = ['aid','=',aid];
		$member = db('member')->field('id,aid,bid,nickname,realname,headimg,sex,tel,money,score,levelid,createtime,last_visittime,province,city,remark,richinfo')->where($where)->find();
		if(!$member) return $this->json(['status'=>-4,'msg'=>'未找到该用户']);
		if(request()->isPost()){
			$postinfo = input('post.info/a');
			$richinfo = json_encode(input('post.pagecontent'));
			//$nickname = $postinfo['nickname'];
			Db::name('member')->where('aid',aid)->where('id',$mid)->update(['richinfo'=>$richinfo]);
			return $this->json(['status'=>1,'msg'=>'操作成功']);
		}
		$pagecontent = json_decode(\app\common\System::initpagecontent($member['richinfo'],aid),true);
		if(!$pagecontent) $pagecontent = [];
        $rdata = [];
        $rdata['status'] = 1;
        $rdata['info'] = $member;
        $rdata['pagecontent'] = $pagecontent;
        return $this->json($rdata);
	}
    
    //手机后台追加会员
    //头像，昵称（必填），手机号，等级（默认等级），推荐人ID，密码，确认密码
    public function memberadd(){
        if(getcustom('member_add')){
            $data = input('post.');
            $tel = $data['tel'];
            $pwd = $data['pwd'];
            if(!$data['tel']){
                return $this->json(['status'=>0,'msg'=>'填写手机号']);
            }
            if (!checkTel($tel)) {
                return $this->json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            $member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
            if($member){
                return $this->json(['status'=>0,'msg'=>'当前手机号已存在']);
            }            
            if(!$member){
                $reg_data = [];
                $reg_data['aid'] = aid;
                $reg_data['tel'] = $tel;
                if($pwd){
                    $reg_data['pwd'] = md5($pwd);
                    if($pwd != $data['repwd']){
                        return $this->json(['status'=>0,'msg'=>'两次密码不一致']);
                    }
                }
                if(!empty($data['levelid'])){
                    $reg_data['levelid'] = $data['levelid'];
                }
                if(!empty($data['nickname'])){
                    $reg_data['nickname'] = $data['nickname'];
                }else{
                    $reg_data['nickname'] = substr($tel,0,3).'****'.substr($tel,-4);
                }
                if(!empty($data['headimg'])){
                    $reg_data['headimg'] = $data['headimg'];
                }else{
                    $reg_data['headimg'] = PRE_URL.'/static/img/touxiang.png';
                }
                if(!empty($data['reg_pid'])){
                    $reg_data['pid'] = $data['reg_pid'];
                    $pmember = Db::name('member')->where('aid',aid)->where('id',$data['reg_pid'])->find();
                    if(!$pmember){
                        return $this->json(['status'=>0,'msg'=>'上级id不存在请重新选择']);
                    }
                }else{
                    $reg_data['pid'] = 0;
                }

                $reg_data['sex'] = 3;
                $reg_data['createtime'] = time();
                $reg_data['last_visittime'] = time();
                $reg_data['platform'] = platform;
                $mid = \app\model\Member::add(aid,$reg_data);
                $defaultlevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
                if($data['levelid'] != $defaultlevel['id']){
                    //增加升级记录
                    $order = [
                        'aid' => aid,
                        'mid' => $mid,
                        'from_mid' => 0,
                        'pid'=>$data['pid'],
                        'levelid' => $data['levelid'] ,
                        'title' => '后台升级',
                        'totalprice' => 0,
                        'createtime' => time(),
                        'levelup_time' => time(),
                        'beforelevelid' => $defaultlevel['id'],
                        'form0' => '类型^_^后台升级',
                        'platform' => platform,
                        'status' => 2
                    ];
                    Db::name('member_levelup_order')->insert($order);
                }
                $member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
            }				
            return $this->json(['status'=>1,'msg'=>'操作成功','member'=>$member]);
        }
    }
    //会员级别
    public function memberlevel(){
            $level = Db::name('member_level')->where('aid',aid)->field('id,name,isdefault')->select()->toArray(); 
            return $this->json(['status'=>1,'msg'=>'操作成功','data'=>$level]);

    }
    public function batchMemberList(){
        if(bid != 0){
            return $this->json(['status'=>0,'msg'=>'仅系统管理员可操作']);
        }
        $pagenum = input('post.pagenum/d');
        if(!$pagenum) $pagenum = 1;
        $pernum = input('post.pernum/d');
        if(!$pernum) $pernum = 50;
        $pid = input('post.pid');
        $levelid = input('post.levelid/d');
        $join_start = input('post.join_start');
        $join_end = input('post.join_end');
        $where = [];
        $where[] = ['aid','=',aid];
        if($pid !== null && $pid!==''){
            $where[] = ['pid','=',intval($pid)];
        }
        if($levelid){
            $where[] = ['levelid','=',intval($levelid)];
        }
        if($join_start){
            $start = is_numeric($join_start) ? intval($join_start) : strtotime($join_start);
            if($start){
                $where[] = ['createtime','>=',$start];
            }
        }
        if($join_end){
            $end = is_numeric($join_end) ? intval($join_end) : strtotime($join_end);
            if($end){
                $where[] = ['createtime','<=',$end];
            }
        }
        $count = Db::name('member')->where($where)->count();
        $list = Db::name('member')->field('id,nickname,realname,headimg,tel,createtime,levelid,pid')
            ->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$list) $list = [];
        foreach($list as $k=>$v){
            $list[$k]['createtime'] = date('Y-m-d',$v['createtime']);
        }
        return $this->json(['status'=>1,'data'=>['list'=>$list,'count'=>$count,'pagenum'=>$pagenum,'pernum'=>$pernum]]);
    }
    public function batchChangeLevel(){
        if(bid != 0){
            return $this->json(['status'=>0,'msg'=>'仅系统管理员可操作']);
        }
        $levelid = input('post.levelid/d');
        if(!$levelid){
            return $this->json(['status'=>0,'msg'=>'请选择等级']);
        }
        $mids = input('post.mids/a');
        if($mids){
            $mids = array_values(array_filter(array_map(function($x){return intval($x);}, $mids), function($x){return $x>0;}));
        }
        if($mids && count($mids)>0){
            $count = Db::name('member')->where('aid',aid)->where('id','in',$mids)->count();
            if($count == 0){
                return $this->json(['status'=>0,'msg'=>'未匹配到会员']);
            }
            Db::name('member')->where('aid',aid)->where('id','in',$mids)->update(['levelid'=>$levelid]);
            \app\common\System::plog('批量修改会员等级，数量：'.$count);
            return $this->json(['status'=>1,'msg'=>'操作成功','count'=>$count]);
        }
        $pid = input('post.pid');
        $join_start = input('post.join_start');
        $join_end = input('post.join_end');
        $where = [];
        $where[] = ['aid','=',aid];
        if($pid !== null && $pid!==''){
            $where[] = ['pid','=',intval($pid)];
        }
        if($join_start){
            $start = is_numeric($join_start) ? intval($join_start) : strtotime($join_start);
            if($start){
                $where[] = ['createtime','>=',$start];
            }
        }
        if($join_end){
            $end = is_numeric($join_end) ? intval($join_end) : strtotime($join_end);
            if($end){
                $where[] = ['createtime','<=',$end];
            }
        }
        $count = Db::name('member')->where($where)->count();
        if($count == 0){
            return $this->json(['status'=>0,'msg'=>'未匹配到会员']);
        }
        Db::name('member')->where($where)->update(['levelid'=>$levelid]);
        \app\common\System::plog('批量修改会员等级，数量：'.$count);
        return $this->json(['status'=>1,'msg'=>'操作成功','count'=>$count]);
    }
}
