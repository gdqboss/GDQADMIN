<?php
// JK客户定制

//custom_file(member_overdraft_money)
// +----------------------------------------------------------------------
// | 信用额度
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class OverdraftMoney extends Common
{
    public $type = '';
    public $act = '';
    public $log_type  = '';
    public $type_name = '';
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    public function recharge(){
        $mid = input('param.mid/d');
        $money = input('param.money');
        $limit_money = input('param.limit_money');
        $remark = input('param.remark');
        $open_overdraft_money = input('param.open_overdraft_money');
        if($this->user['isadmin']==0 && !in_array('OverdraftMoney/recharge',$this->auth_data)){
            return json(['status'=>0,'msg'=>'无权限操作']);
        }
        if($money && empty($remark)){
            return json(['status'=>0,'msg'=>'请填写备注信息']);
        }
        Db::name('member')->where('aid',aid)->where('id',$mid)->update(['limit_overdraft_money'=>$limit_money,'open_overdraft_money'=>$open_overdraft_money]);
        if($money){
            \app\common\Member::addOverdraftMoney($this->aid,$mid,$money,$remark);
        }
        \app\common\System::plog(t('信用额度').'充值mid='.$mid."({$money})");
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //信用额度明细
    public function moneylog(){
        if(request()->isAjax()){
            $page  = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'log.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'log.id desc';
            }
            $where = [];
            $where[] = ['log.aid','=',aid];

            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['log.mid','=',trim(input('param.mid'))];
            $count = 0 + Db::name('member_overdraft_moneylog')->alias('log')->field('member.nickname,member.headimg,log.*')->join('member member','member.id=log.mid')->where($where)->count();
            $data = Db::name('member_overdraft_moneylog')->alias('log')->field('member.nickname,member.headimg,log.*')->join('member member','member.id=log.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //信用额度明细导出
    public function moneylogexcel(){
        if(input('param.field') && input('param.order')){
            $order = 'log.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'log.id desc';
        }
        $page  = input('param.page');
        $limit = input('param.limit');
        $where = array();
        $where[] = ['log.aid','=',aid];

        if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
        if(input('param.mid')) $where[] = ['log.mid','=',trim(input('param.mid'))];
        $list = Db::name('member_overdraft_moneylog')->alias('log')->field('member.nickname,member.headimg,log.*')
            ->join('member member','member.id=log.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('member_overdraft_moneylog')->alias('log')->field('member.nickname,member.headimg,log.*')
            ->join('member member','member.id=log.mid')->where($where)->count();
        $title = array();
        $title[] = t('会员').'信息';
        $title[] = '变更金额';
        $title[] = '变更后剩余';
        $title[] = '变更时间';
        $title[] = '备注';
        $data = array();
        foreach($list as $v){
            $tdata = array();
            $tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
            $tdata[] = $v['money'];
            $tdata[] = $v['after'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $tdata[] = $v['remark'];
            $data[] = $tdata;
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    //信用额度明细删除
    public function moneylogdel(){
        $ids = input('post.ids/a');
        Db::name('member_overdraft_moneylog')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除'.t('信用额度').'明细'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //批量修改限制信用额度
    public function batchLimitOverdrafmoney(){
        $ids = input('post.ids/a');
        $limit_money = input('param.limit_money');
        Db::name('member')->where('aid',aid)->where('id','in',$ids)->update(['limit_overdraft_money'=>$limit_money]);
        \app\common\System::plog('修改'.t('信用额度').implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}
