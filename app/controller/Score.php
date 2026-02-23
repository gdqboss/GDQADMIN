<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 积分管理
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Score extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//积分明细
    public function scorelog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_scorelog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_scorelog.id desc';
			}
			$where = array();
			$where[] = ['member_scorelog.aid','=',aid];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_scorelog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_scorelog.createtime','<',strtotime($ctime[1]) + 86400];
            }
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_scorelog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_scorelog.status','=',input('param.status')];
			if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_scorelog.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_scorelog.createtime','<',strtotime($ctime[1]) + 86400];
            }

			$count = 0 + Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')->join('member member','member.id=member_scorelog.mid')->where($where)->count();
			$data = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')->join('member member','member.id=member_scorelog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $score_weishu = 0;
            foreach ($data as $k => $v){
                $data[$k]['score'] = dd_money_format($v['score'],$score_weishu);
                $data[$k]['used'] = dd_money_format($v['used'],$score_weishu);
                $data[$k]['after'] = dd_money_format($v['after'],$score_weishu);

                $data[$k]['un'] = '';
				if($v['uid']){
					$un = Db::name('admin_user')->where('id',$v['uid'])->where('aid',aid)->value('un');
					$data[$k]['un'] = $un??'已失效';
				}
            }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//积分明细导出
	public function scorelogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_scorelog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_scorelog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = array();
		$where[] = ['member_scorelog.aid','=',aid];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_scorelog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_scorelog.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['member_scorelog.createtime','>=',strtotime($ctime[0])];
            $where[] = ['member_scorelog.createtime','<',strtotime($ctime[1]) + 86400];
        }

		$list = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')
            ->join('member member','member.id=member_scorelog.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_scorelog')->alias('member_scorelog')->field('member.nickname,member.headimg,member_scorelog.*')
            ->join('member member','member.id=member_scorelog.mid')->where($where)->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更'.t('积分');
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$title[] = '操作员';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['score'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];

			$un = '';
			if($v['uid']){
				$un = Db::name('admin_user')->where('id',$v['uid'])->where('aid',aid)->value('un');
				$un .= $un??'已失效';
				$un .= '(操作员ID:'.$v['uid'].')';
			}
			$tdata[] = $un;

            $data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	public function scorelogdel(){
		$ids = input('post.ids/a');
		Db::name('member_scorelog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\common\System::plog(t('积分').'明细删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    public function cancel()
    {
        $ids = input('post.ids/a');
        $list = Db::name('member_scorelog')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
        foreach ($list as $item){
            if($item['status'] != -1 && $item['is_cancel'] == 0){
                //过期和已撤销的无需处理
                Db::name('member_scorelog')->where('aid',aid)->where('id',$item['id'])->update(['is_cancel'=>1]);
                \app\common\Member::addscore(aid,$item['mid'],$item['score']*-1,'撤销操作');
            }

        }
        \app\common\System::plog(t('积分').'明细撤销'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}
