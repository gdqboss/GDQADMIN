<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 购物返现
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Cashback extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无操作权限');
    }
    //列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('cashback')->where($where)->count();
            $data = Db::name('cashback')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                if($v['starttime'] > time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
                }elseif($v['endtime'] < time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
                }else{
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#5FB878">进行中</button>';
                }
                $data[$k]['starttime'] = date('Y-m-d H:i',$v['starttime']);
                $data[$k]['endtime'] = date('Y-m-d H:i',$v['endtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('cashback')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            $info['starttime'] = date('Y-m-d H:i:s',$info['starttime']);
            $info['endtime'] = date('Y-m-d H:i:s',$info['endtime']);
        }else{
            $info = array('id'=>'','starttime'=>date('Y-m-d 00:00:00'),'endtime'=>date('Y-m-d 00:00:00',time()+7*86400),'gettj'=>'-1','sort'=>0,'fwtype'=>0,'type'=>1,'tip'=>'满减');
        }
        $info['gettj'] = explode(',',$info['gettj']);

        View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);

        $categorydata = array();
        if($info && $info['categoryids']){
            $categorydata = Db::name('shop_category')->where('aid',aid)->where('id','in',$info['categoryids'])->order('sort desc,id')->select()->toArray();
        }
        View::assign('categorydata',$categorydata);
        $productdata = array();
        if($info && $info['productids']){
            $productdata = Db::name('shop_product')->where('aid',aid)->where('id','in',$info['productids'])->order(Db::raw('field(id,'.$info['productids'].')'))->select()->toArray();
        }
        View::assign('productdata',$productdata);
        if(getcustom('plug_tengrui')){
            $groupdata = array();
            if($info && $info['group_ids']){
                $groupdata = Db::name('member_tr_group')->where('aid',aid)->where('id','in',$info['group_ids'])->order('id desc')->select()->toArray();
            }
            View::assign('groupdata',$groupdata);
        }

        if(getcustom('yx_cashback_collage')){
            $collagedata = array();
            if($info && $info['collageids']){
                $collagedata = Db::name('collage_product')->where('aid',aid)->where('id','in',$info['collageids'])->order(Db::raw('field(id,'.$info['collageids'].')'))->select()->toArray();
            }
            View::assign('collagedata',$collagedata);
        }
        $return_type = 0;
        if(getcustom('yx_cashback_time')){
            $return_type = 1;//自定义天数返还
        }
        if(getcustom('yx_cashback_stage')){
            $return_type = 2;//自定义阶梯性返还
        }
        if(getcustom('yx_cashback_multiply')){
            $return_type = 3;//倍增方式返现
        }
        View::assign('return_type',$return_type);
        return View::fetch();
    }

    //保存
    public function save(){
        $info = input('post.info/a');

        if(getcustom('yx_cashback_time') && getcustom('yx_cashback_jiange_day') && $info['return_type'] == 1 && !empty($info['jiange_day'])){
            $is_int = $info['return_day'] % $info['jiange_day'];
            if($is_int != 0){
                return json(['status'=>0,'msg'=>'返还天数必须是间隔天数的整数倍']);
            }
        }

        if(getcustom('yx_cashback_collage')){
            if($info['fwtype'] == 3 && $info['collageids']){
                //查重，确保id唯一
                $collageids     = explode(',',$info['collageids']);
                $collageids_num = count($collageids);
                $new_collageids = array_unique($collageids);
                $new_collageids_num = count($new_collageids);
                if($collageids_num != $new_collageids_num){
                    return json(['status'=>0,'msg'=>'多人拼团存在重复数据，请删除']);
                }
            }
        }
        $info['gettj'] = implode(',',$info['gettj']);
        $info['starttime'] = strtotime($info['starttime']);
        $info['endtime'] = strtotime($info['endtime']);
        //开启限额
        $cashback_max = 0;
        if(getcustom('cashback_max')){
            $cashback_max = 1;
        }
        //开启选择受益人
        $cashback_receiver = 0;
        if(getcustom('cashback_receiver')){
            $cashback_receiver = 1;
        }

        //受益人限额仅限单个商品可用
        if($cashback_receiver || $cashback_max){
            $goods_multiple_max = $info['goods_multiple_max'];
            $receiver_type = $info['receiver_type'];
            $fwtype = $info['fwtype'];
            $productids = explode(',',$info['productids']);
            //开启受益人为参与活动的人或者限制倍数限制指定一个商品
            if($receiver_type ==2){
                if($fwtype !=2 || count($productids) != 1){
                    return json(['status'=>0,'msg'=>'开启受益人和限额仅限单个指定商品']);
                }
                //判定当前活动商品不能同时存在其它开始的活动商品中
                $now_time = time();
                //$where[] = ['starttime','<=',$now_time];
                $where[] = ['endtime','>',$now_time];
                //$where[] = ['productids','=',$info['productids']];
                if($info['id']){
                    $where[] = ['id','<>',$info['id']];
                }
                //$where_or = 'receiver_type = 2 or goods_multiple_max > 0';
//                $product = Db::name('shop_product')
//                    ->where('id',$info['productids'])
//                    ->field('id,cid')
//                    ->find();
                $where_pro = 'FIND_IN_SET("'.$info['productids'].'", productids) or fwtype = 0 or fwtype = 1';
                $goods_data = Db::name('cashback')->where($where)->whereRaw($where_pro)->select()->toArray();
                if($goods_data){
                    return json(['status'=>0,'msg'=>'当前商品已存在其它活动']);
                }
            }
        }

        if(getcustom('yx_cashback_time_teamspeed')){
            $teamspeeddata  = array();
            $postmoney = input('post.money/a');
            $postspeed = input('post.speed/a');
            foreach($postmoney as $k=>$money){
                $teamspeeddata[] = array(
                    'money'=>$money,
                    'speed'=>$postspeed[$k],
                );
            }
            //按金额重新排序
            $newdata = array_column($teamspeeddata,'money');
            array_multisort($newdata ,SORT_ASC,$teamspeeddata);
            $info['teamspeeddata'] = json_encode($teamspeeddata,JSON_UNESCAPED_UNICODE);
        }

        if(getcustom('yx_cashback_stage')){
            if($info['return_type'] == 2){
                $stagedata  = array();
                $stageday   = input('post.stageday/a');
                $stageday2  = input('post.stageday2/a');
                $stageratio = input('post.stageratio/a');
                foreach($stageday as $k=>$day){
                    if($stageday2[$k]<$day){
                        return json(['status'=>0,'msg'=>'阶梯返还，最大天数必须大于等于最小天数']);
                    }
                    $data = [
                        'stageday'=>$day,
                        'stageday2'=>$stageday2[$k],
                        'stageratio'=>$stageratio[$k]
                    ];
                    $stagedata[] = $data;
                }
                //重新排序
                $stagedata2= array_column($stagedata,'stageday');
                array_multisort($stagedata2,SORT_ASC,$stagedata);
                $info['stagedata'] = json_encode($stagedata,JSON_UNESCAPED_UNICODE);
            }
        }
        if($info['id']){
            Db::name('cashback')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\common\System::plog('修改购物返现活动'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('cashback')->insertGetId($info);
            \app\common\System::plog('添加购物返现活动'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('cashback')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\common\System::plog('删除购物返现活动'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //参与会员
    public function record(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'cashback_member.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'cashback_member.id desc';
            }
            $where = [];
            $where[] = ['cashback_member.aid','=',aid];
            if(input('param.id/d')) $where[] = ['cashback_member.cashback_id','=',input('param.id/d')];
            if(input('param.mid')) $where[] = ['cashback_member.mid','=',input('param.mid/d')];
            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['cashback_member.create_time','>=',strtotime($ctime[0])];
                $where[] = ['cashback_member.create_time','<',strtotime($ctime[1]) + 86400];
            }

            $count = 0 + Db::name('cashback_member')->alias('cashback_member')->join('member member','cashback_member.mid=member.id')->where($where)->count();
            $data = Db::name('cashback_member')->alias('cashback_member')->field('cashback_member.*,member.nickname,member.headimg')->join('member member','cashback_member.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            if($data){
                //返现类型 1、余额 2、佣金 3、积分 小数位数
                $moeny_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
                if(getcustom('member_money_weishu')){
                    $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
                }
                if(getcustom('fenhong_money_weishu')){
                    $commission_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
                }
                if(getcustom('score_weishu')){
                    $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                }
                foreach($data as &$v){
                    $v['cashback_money'] = dd_money_format($v['cashback_money'],$moeny_weishu);
                    $v['commission']     = dd_money_format($v['commission'],$commission_weishu);
                    $v['score']          = dd_money_format($v['score'],$score_weishu);
                    if(!empty($v['order_mid'])){
                        $order_member = Db::name('member')->where('id',$v['order_mid'])->field('nickname,headimg')->find();
                        $v['order_headimg'] = $order_member['headimg'];
                        $v['order_nickname'] = $order_member['nickname'];
                    }
                }
                unset($v);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        if(input('param.id/d')){
            $cashback = Db::name('cashback')->where('id',input('param.id/d'))->find();
        }
        View::assign('cashback',$cashback);
        return View::fetch();
    }
        //参与会员记录
        public function recordLog(){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'cashback_member.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'cashback_member.id desc';
                }
                $where = [];
                $where[] = ['cashback_member.aid','=',aid];
                if(input('param.cashback_id/d')) $where[] = ['cashback_member.cashback_id','=',input('param.cashback_id/d')];
                if(input('param.pro_id/d')) $where[] = ['cashback_member.pro_id','=',input('param.pro_id/d')];
                if(input('param.mid')) $where[] = ['cashback_member.mid','=',input('param.mid/d')];
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['cashback_member.create_time','>=',strtotime($ctime[0])];
                    $where[] = ['cashback_member.create_time','<',strtotime($ctime[1]) + 86400];
                }
    
                $count = 0 + Db::name('cashback_member_log')->alias('cashback_member')->join('member member','cashback_member.mid=member.id')->where($where)->count();
                $data = Db::name('cashback_member_log')->alias('cashback_member')->field('cashback_member.*,member.nickname,member.headimg')->join('member member','cashback_member.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                if($data){
                    //返现类型 1、余额 2、佣金 3、积分 小数位数
                    $moeny_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
                    if(getcustom('member_money_weishu')){
                        $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
                    }
                    if(getcustom('fenhong_money_weishu')){
                        $commission_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
                    }
                    if(getcustom('score_weishu')){
                        $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                    }
                    foreach($data as &$v){
                        $v['cashback_money'] = dd_money_format($v['cashback_money'],$moeny_weishu);
                        $v['commission']     = dd_money_format($v['commission'],$commission_weishu);
                        $v['score']          = dd_money_format($v['score'],$score_weishu);
                        if(!empty($v['order_mid'])){
                            $order_member = Db::name('member')->where('id',$v['order_mid'])->field('nickname,headimg')->find();
                            $v['order_headimg'] = $order_member['headimg'];
                            $v['order_nickname'] = $order_member['nickname'];
                        }
                    }
                    unset($v);
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }

    //倍增返现数据
    public function og_log(){
        if(getcustom('yx_cashback_multiply')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'c.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'c.id desc';
                }
                $where = [];
                $where[] = ['c.aid','=',aid];
                $where[] = ['c.return_type','=',3];
                if(input('param.id/d')) $where[] = ['c.cashback_id','=',input('param.id/d')];
                if(input('param.mid')) $where[] = ['c.mid','=',input('param.mid/d')];
                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['c.createtime','>=',strtotime($ctime[0])];
                    $where[] = ['c.createtime','<',strtotime($ctime[1]) + 86400];
                }
                $status = input('param.status/d');
                if($status==1){
                    $where[] = ['c.status','in',[0,1]];
                }elseif($status==2){
                    $where[] = ['c.status','=',2];
                }
                $count = 0 + Db::name('shop_order_goods_cashback')->alias('c')->join('member member','c.mid=member.id')->where($where)->count();
                $data = Db::name('shop_order_goods_cashback')->alias('c')
                    ->field('c.*,member.nickname,member.headimg')
                    ->join('member member','c.mid=member.id')
                    ->where($where)->page($page,$limit)->order($order)->select()->toArray();
                if($data){
                    //返现类型 1、余额 2、佣金 3、积分 小数位数
                    $moeny_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
                    if(getcustom('member_money_weishu')){
                        $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
                    }
                    if(getcustom('fenhong_money_weishu')){
                        $commission_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
                    }
                    if(getcustom('score_weishu')){
                        $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                    }
                    foreach($data as &$v){
                        if(!empty($v['order_mid'])){
                            $order_member = Db::name('member')->where('id',$v['order_mid'])->field('nickname')->find();
                            $v['order_headimg'] = $order_member['headimg'];
                            $v['order_nickname'] = $order_member['nickname'];
                        }
                        $v['next_circle_yeji'] = bcadd($v['last_circle_yeji'],bcmul($v['last_circle_yeji'],$v['circle_add']/100,4),2);
                    }
                    unset($v);
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            if(input('param.id/d')){
                $cashback = Db::name('cashback')->where('id',input('param.id/d'))->find();
            }
            View::assign('cashback',$cashback);

            $lirun = \app\custom\OrderCustom::getPlateLirun(aid);
            View::assign('plate_lirun',$lirun['lirun']);
            return View::fetch();
        }
    }
    //返现测试按钮
    public function release(){
        if(getcustom('yx_cashback_multiply')) {
            Db::startTrans();
            \app\custom\OrderCustom::deal_autocashback_multiply(aid);
            Db::commit();
            return json(['status' => 1, 'msg' => '发放成功！']);
        }
    }
    //返现明细
    public function cashback_log(){
        if(getcustom('yx_cashback_multiply')) {
            if (request()->isAjax()) {
                $page = input('param.page');
                $limit = input('param.limit');
                if (input('param.field') && input('param.order')) {
                    $order = 'c.' . input('param.field') . ' ' . input('param.order');
                } else {
                    $order = 'c.id desc';
                }
                $where = [];
                $where[] = ['c.aid', '=', aid];
                if (input('cashback_id')) {
                    $where[] = ['c.cashback_id', '=', input('param.cashback_id')];
                }
                if (input('og_id')) {
                    $where[] = ['c.og_id', '=', input('param.og_id')];
                }
                if (input('param.mid')) $where[] = ['c.mid', '=', input('param.mid/d')];
                if (input('param.nickname')) $where[] = ['member.nickname', 'like', '%' . input('param.nickname') . '%'];
                if (input('param.ctime')) {
                    $ctime = explode(' ~ ', input('param.ctime'));
                    $where[] = ['c.create_time', '>=', strtotime($ctime[0])];
                    $where[] = ['c.create_time', '<', strtotime($ctime[1]) + 86400];
                }

                $count = 0 + Db::name('cashback_log')->alias('c')->join('member member', 'c.mid=member.id')->where($where)->count();
                $data = Db::name('cashback_log')->alias('c')
                    ->field('c.*,member.nickname,member.headimg')
                    ->join('member member', 'c.mid=member.id')
                    ->where($where)->page($page, $limit)->order($order)->select()->toArray();
                if ($data) {
                    foreach ($data as &$v) {

                    }
                    unset($v);
                }
                return json(['code' => 0, 'msg' => '查询成功', 'count' => $count, 'data' => $data]);
            }
            return View::fetch();
        }
    }
}