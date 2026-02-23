<?php
// JK客户定制


//custom_file(douyin_groupbuy)
// +----------------------------------------------------------------------
// | 抖音-抖音团购商品管理
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
use app\common\Wechat;
class DouyinGroupbuyProduct extends Common
{
    public function initialize(){
        parent::initialize();
    }
    //商品列表
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
            $where[] = ['dyg_product_id','>',0];
            if(input('?param.ischecked') && input('param.ischecked')!=='') $where[] = ['ischecked','=',$_GET['ischecked']];
            if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
            if(input('?param.status') && input('param.status')!==''){
                $status = input('param.status');
                $nowtime = time();
                $nowhm = date('H:i');
                if($status==1){
                    $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
                }else{
                    $where[] = Db::raw("`status`=0 or (`status`=2 and (unix_timestamp(start_time)>$nowtime or unix_timestamp(end_time)<$nowtime)) or (`status`=3 and ((start_hours<end_hours and (start_hours>'$nowhm' or end_hours<'$nowhm')) or (start_hours>=end_hours and (start_hours>'$nowhm' and end_hours<'$nowhm'))) )");
                }
            }
            if(input('?param.cid') && input('param.cid')!==''){
                $cid = input('param.cid');
                //子分类
                $clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
                if($clist){
                    $clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
                    $cCate = array_merge($clist, $clist2, [$cid]);
                    if($cCate){
                        $whereCid = [];
                        foreach($cCate as $k => $c2){
                            $whereCid[] = "find_in_set({$c2},cid)";
                        }
                        $where[] = Db::raw(implode(' or ',$whereCid));
                    }
                } else {
                    $where[] = Db::raw("find_in_set(".$cid.",cid)");
                }
            }
            if(input('?param.cid2') && input('param.cid2')!==''){
                $cid = input('param.cid2');
                //子分类
                $clist = Db::name('shop_category2')->where('aid',aid)->where('pid',$cid)->column('id');
                if($clist){
                    $clist2 = Db::name('shop_category2')->where('aid',aid)->where('pid','in',$clist)->column('id');
                    $cCate = array_merge($clist, $clist2, [$cid]);
                    if($cCate){
                        $whereCid = [];
                        foreach($cCate as $k => $c2){
                            $whereCid[] = "find_in_set({$c2},cid2)";
                        }
                        $where[] = Db::raw(implode(' or ',$whereCid));
                    }
                } else {
                    $where[] = Db::raw("find_in_set(".$cid.",cid2)");
                }
            }
            if(input('?param.gid') && input('param.gid')!=='') $where[] = Db::raw("find_in_set(".input('param.gid/d').",gid)");
            $count = 0 + Db::name('shop_product')->where($where)->count();
            $data = Db::name('shop_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            $cdata = Db::name('shop_category')->where('aid',aid)->column('name','id');
            if(bid > 0){
                $cdata2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->column('name','id');
            }
            $iscustomoption = 0;
            foreach($data as $k=>$v){
                $gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
                $ggdata = array();
                foreach($gglist as $gg){
                    $ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled" style="color:#333">￥'.$gg['sell_price'].'</button>';

                }
                $v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
                if($v['bid'] > 0){
                    $v['cid2'] = explode(',',$v['cid2']);
                    $data[$k]['cname2'] = null;
                    if ($v['cid2']) {
                        foreach ($v['cid2'] as $cid) {
                            if($data[$k]['cname2'])
                                $data[$k]['cname2'] .= ' ' . $cdata2[$cid];
                            else
                                $data[$k]['cname2'] .= $cdata2[$cid];
                        }
                    }
                    $data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
                }else{
                    $data[$k]['cname2'] = '';
                    $data[$k]['bname'] = '平台自营';
                }
                $data[$k]['ggdata'] = implode('<br>',$ggdata);
                $sales_num = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
                $refund_num = Db::name('shop_refund_order_goods')
                    ->alias('rg')
                    ->join('shop_refund_order ro','rg.refund_orderid=ro.id')
                    ->where('rg.aid',aid)->where('rg.proid',$v['id'])->where('ro.refund_status',2)->sum('rg.refund_num');
                $realsalenum = $sales_num-$refund_num;
                $data[$k]['realsalenum'] = $realsalenum>0?$realsalenum:0;
                if($v['status']==2){ //设置上架时间
                    if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
                        $data[$k]['status'] = 1;
                    }else{
                        $data[$k]['status'] = 0;
                    }
                }
                if($v['status']==3){ //设置上架周期
                    $start_time = strtotime(date('Y-m-d '.$v['start_hours']));
                    $end_time = strtotime(date('Y-m-d '.$v['end_hours']));
                    if(($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))){
                        $data[$k]['status'] = 1;
                    }else{
                        $data[$k]['status'] = 0;
                    }
                }
                if($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
                $data[$k]['iscustomoption'] = $iscustomoption;

                //抖音信息
                $douyin_groupbuy = Db::name('shop_product_douyin_groupbuy')->where('proid',$v['id'])->where('aid',aid)->where('bid',bid)->find();
                $data[$k]['douyin_groupbuy'] = $douyin_groupbuy?$douyin_groupbuy:'';
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        //分类
        $clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
        foreach($clist as $k=>$v){
            $child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
            foreach($child as $k2=>$v2){
                $child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                $child[$k2]['child'] = $child2;
            }
            $clist[$k]['child'] = $child;
        }
        if(bid > 0){
            //商家的商品分类
            $clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
            foreach($clist2 as $k=>$v){
                $clist2[$k]['child'] = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
            }
            View::assign('clist2',$clist2);
        }
        //分组
        $glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        View::assign('clist',$clist);
        View::assign('glist',$glist);

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();

        View::assign('levellist',$levellist);
        View::assign('admin',$this->admin);

        $cangetall = true;
        if(bid>0){
            if($this->auth_data == 'all' || in_array('DouyinGroupbuyGetallproduct',$this->auth_data)){
                $cangetall = true;
            }else{
                $cangetall = false;
            }
        }
        View::assign('cangetall',$cangetall);
        return View::fetch();
    }
    //改状态
    public function setst(){
        $st = input('post.st/d');
        $ids = input('post.ids/a');
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','in',$ids];
        if(bid !=0){
            $where[] = ['bid','=',bid];
            if(!getcustom('business_copy_product')) {
                $where[] = ['linkid', '=', 0];
            }
        }
        Db::name('shop_product')->where($where)->update(['status'=>$st]);
        $this->tongbuproduct($ids);
        if($st == 0){
            \app\common\Wxvideo::delisting($ids);
        }else{
            \app\common\Wxvideo::listing($ids);
        }
        \app\common\System::plog('抖音核销商城商品改状态'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        if(!$ids) $ids = array(input('post.id/d'));
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','in',$ids];
        if(bid !=0){
            $where[] = ['bid','=',bid];
            if(!getcustom('business_copy_product')){
                $where[] = ['linkid','=',0];
            }
        }
        $prolist = Db::name('shop_product')->where($where)->select();
        foreach($prolist as $pro){
            Db::name('shop_product')->where('id',$pro['id'])->delete();
            Db::name('shop_guige')->where('proid',$pro['id'])->delete();
            if(getcustom('plug_businessqr') && $pro['bid']==-1){
                $prolist2 = Db::name('shop_product')->where('linkid',$pro['id'])->select();
                foreach($prolist2 as $pro2){
                    Db::name('shop_product')->where('id',$pro2['id'])->delete();
                    Db::name('shop_guige')->where('proid',$pro2['id'])->delete();
                }
            }
            if($pro['wxvideo_product_id']){
                \app\common\Wxvideo::deleteproduct($pro['id']);
            }
        }
        \app\common\System::plog('抖音核销商城商品删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //获取抖音商品
    public function getdyproduct(){
        if(getcustom('douyin_groupbuy')){
            if(request()->isPost()){
                $type = input('?param.type')?input('param.type'):1;
                if($type == 2){
                    $where = [];
                    $where[] = ['aid','=',aid];
                    $where[] = ['bid','=',bid];
                    $where[] = ['status','=',1];
                    $poi_ids = Db::name('mendian')->where($where)->where('poi_id !=""')->column('poi_id');
                    if(!$poi_ids || empty($poi_ids)){
                        return json(['status'=>0,'msg'=>'系统门店未关联抖音来客门店，请先到系统门店列表中关联抖音来客门店']);
                    }
                }else{
                    if(bid>0){
                        if($this->auth_data != 'all' && !in_array('DouyinGroupbuyGetallproduct',$this->auth_data)){
                            return json(['status'=>0,'msg'=>'无权限获取所有抖音团购商品']);
                        }
                    }
                    $poi_ids = [];
                }
                //同步到抖音团购商品端
                $checkset = \app\custom\DouyinGroupbuyCustom::checkset(aid,bid);
                if($checkset['status'] == 0){
                     return json($checkset);
                }
                $set = $checkset['set'];
                if(empty($set['account_id'])){
                     return json(['status'=>0,'msg'=>'抖音团购设置不完善']);
                }
                $set['account_id'] = $set['account_id'];

                //抖音商品
                $res = \app\custom\DouyinGroupbuyCustom::getdyproduct(aid,bid,$set['account_id'],0,50);
                if($res['status'] ==1){
                    //处理商品同步数据库
                    self::deal_getdyproduct(aid,bid,$type,$poi_ids,$res);
                    //用于查询下一页
                    $next_cursor = $res['data']['data']['next_cursor'];
                    //是否有下一页
                    $has_more = $res['data']['data']['has_more'];
                    if($next_cursor && $has_more){
                        //继续获取
                        self::deal_getdyproduct2(aid,bid,$type,$poi_ids,$set['account_id'],$next_cursor,50);
                    }
                    \app\common\System::plog('获取抖音团购商品');
                    return json(['status'=>1, 'msg'=>'获取成功']);
                }else{
                    $msg = $res && $res['msg']?$res['msg']:'获取失败';
                    return json(['status'=>0, 'msg'=>$msg]);
                }
            }
        }
    }
    private static function deal_getdyproduct($aid,$bid=0,$type = 1,$poi_ids=[],$res){
        //线上数据列表
        $products = $res['data']['data']['products'];
        if($products){
            foreach($products as $pv){
                $status  = $pv['online_status'];
                if($status == 1 || $status == 2){
                    $product   = $pv['product'];
                    if($type == 2){
                        $dy_pois = $product['pois'] && !empty($product['pois'])?$product['pois']:'';
                        if($dy_pois){
                            $addstatus = false;
                            foreach($dy_pois as $dy_poi){
                                if(in_array($dy_poi['poi_id'],$poi_ids)){
                                    $addstatus = true;
                                    break;
                                }
                            }
                            if(!$addstatus){
                                continue;
                            }
                        }
                    }
                    $otherattr = $product['attr_key_value_map'];
                    $sku = $pv['sku'];//售卖单元
                    //查询是否本地已有此商品
                    $count = Db::name('shop_product')->where('dyg_product_id',$product['product_id'])->count('id');
                    if(!$count){
                        //添加
                        $data = [];
                        $data['aid'] = $aid;
                        $data['bid'] = $bid;
                        $data['name']              = $product['product_name'];
                        $data['dyg_product_id']    = $product['product_id'];//抖音团购
                        $data['guigedata'] = '[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]';
                        //$data['status'] = $status == 1?1:0;
                        if($otherattr){
                            if($otherattr['image_list']){
                                $image_list = json_decode($otherattr['image_list'],true);
                                $pics = [];
                                foreach($image_list as $img){
                                    $img_url = \app\common\Pic::uploadoss($img['url']);
                                    $pics[]  = $img_url?$img_url:'';
                                }
                                $data['pic']  = $pics[0];
                                $data['pics'] = implode(',',$pics);
                            }
                            if($otherattr['environment_image_list']){
                                $environment_image_list = json_decode($otherattr['environment_image_list'],true);
                                $content = '';
                                foreach($environment_image_list as $img){
                                    $img_url = \app\common\Pic::uploadoss($img['url']);
                                    $content .= '<p><img src="'.$img_url.'" width="100%" style=""/></p>';
                                }
                                $detail = [ 
                                    "id"=>"M0000000000000",
                                    "temp"=>"richtext",
                                    "params"=>["bgcolor"=>"#FFFFFF","margin_x"=>"0","margin_y"=>"0","padding_x"=>0,"padding_y"=>0,"platform"=>["all"=>true],"quanxian"=>["all"=>true]
                                    ],
                                    "data"=>"",
                                    "other"=>"",
                                    'content'=>$content
                                ];
                                $data['detail']  = json_encode([$detail]);
                            }
                        }

                        $data['market_price'] = 0;
                        $data['sell_price']   = 0;
                        if($sku['origin_amount'] && !empty($sku['origin_amount'])){
                            $data['market_price'] = round($sku['origin_amount']/100,2);
                        }
                        if($sku['actual_amount'] && !empty($sku['actual_amount'])){
                            $data['sell_price'] = round($sku['actual_amount']/100,2);
                        }

                        if($sku['stock']){
                            $limit_type = $sku['stock']['limit_type'] && !empty($sku['stock']['limit_type'])?$sku['stock']['limit_type']:0;
                            $stock_qty  = $sku['stock']['stock_qty'] && !empty($sku['stock']['stock_qty'])?$sku['stock']['stock_qty']:0;
                            if($limit_type == 1){
                                $data['stock'] = $stock_qty;
                            }else{
                                $data['stock'] = 999;
                            }
                        }
                        $data['status'] = 0;
                        if($bid !=0 ){
                            $bset = Db::name('business_sysset')->where('aid',aid)->find();
                            if($bset['product_check'] == 1){
                                $data['ischecked'] = 0;
                            }
                        }
                        $data['createtime'] = time();

                        $proid = Db::name('shop_product')->insertGetid($data);
                        if($proid){
                            $ggdata = [];
                            $ggdata['aid'] = aid;
                            $ggdata['proid'] = $proid;
                            $ggdata['procode'] = '';
                            $ggdata['barcode'] = '';
                            $ggdata['name']    = '默认规格';
                            $ggdata['ks']      = 0;
                            $ggdata['pic']     = '';

                            $ggdata['cost_price']   = 0;
                            $ggdata['market_price'] = $data['market_price'];
                            $ggdata['sell_price']   = $data['sell_price'];

                            $ggdata['stock']        = $data['stock'];
                            $ggdata['weight']       = 0;
                            //会员价
                            $ggdata['lvprice_data'] = '';
                            Db::name('shop_guige')->insert($ggdata);
                            $data2 = [];
                            $data2['aid'] = $aid;
                            $data2['bid'] = $bid;
                            $data2['proid']  = $proid;
                            $data2['dy_product_id']         = $product['product_id'];
                            $data2['dy_category_id']        = $product['category_id'];
                            $data2['dy_category_full_name'] = $product['category_full_name'] && !empty($product['category_full_name'])?$product['category_full_name']:'';
                            $data2['dy_product_type']       = $product['product_type'] && !empty($product['product_type'])?$product['product_type']:0;
                            $data2['dy_biz_line']           = $product['biz_line'] && !empty($product['biz_line'])?$product['biz_line']:0;
                            $data2['dy_sold_start_time']    = $product['sold_start_time'] && !empty($product['sold_start_time'])?$product['sold_start_time']:0;
                            $data2['dy_sold_end_time']      = $product['sold_end_time'] && !empty($product['sold_end_time'])?$product['sold_end_time']:0;

                            if($sku){
                                //原价
                                $data2['dy_origin_amount']      = $data['market_price'];
                                // if($sku['origin_amount'] && !empty($sku['origin_amount'])){
                                //     $data2['dy_origin_amount'] = round($sku['origin_amount']/100,2);
                                // }
                                //实际支付价格
                                $data2['dy_actual_amount']      = $data['sell_price'];
                                // if($sku['actual_amount'] && !empty($sku['actual_amount'])){
                                //     $data2['dy_actual_amount'] = round($sku['actual_amount']/100,2);
                                // }

                                $data2['dy_sku_name']      = $sku['sku_name'] && !empty($sku['sku_name'])?$sku['sku_name']:0;
                                if($sku['stock']){
                                    $data2['dy_limit_type']= $sku['stock']['limit_type'] && !empty($sku['stock']['limit_type'])?$sku['stock']['limit_type']:0;
                                    $data2['dy_stock_qty'] = $sku['stock']['stock_qty'] && !empty($sku['stock']['stock_qty'])?intval($sku['stock']['stock_qty']):0;
                                }
                            }

                            $data2['dy_account_id']   = $product['creator_account_id'] && !empty($product['creator_account_id'])?$product['creator_account_id']:'';
                            $data2['dy_account_name'] = $product['account_name'] && !empty($product['account_name'])?$product['account_name']:'';
                            if($data2['dy_pois']){
                                $pois = [];
                                foreach($product['pois'] as $pv){
                                    $pois[] = $pv['poi_id'];
                                }
                                if($pois){
                                    $data2['pois'] = implode(',',$pois);
                                }
                            }
                            $data2['dy_pois'] = $product['pois'] && !empty($product['pois'])?json_encode($product['pois']):'';

                            $data2['dy_bring_out_meal']     = $otherattr['bring_out_meal'] && !empty($otherattr['bring_out_meal'])?1:0;
                            $data2['dy_free_pack']          = $otherattr['free_pack'] && !empty($otherattr['free_pack'])?1:0;
                            $data2['dy_rec_person_num']     = $otherattr['rec_person_num'] && !empty($otherattr['rec_person_num'])?$otherattr['rec_person_num']:0;
                            $data2['dy_rec_person_num_max'] = $otherattr['rec_person_num_max'] && !empty($otherattr['rec_person_num_max'])?$otherattr['rec_person_num_max']:0;
                            $data2['dy_RefundPolicy']       = $otherattr['RefundPolicy'] && !empty($otherattr['RefundPolicy'])?$otherattr['RefundPolicy']:0;
                            $data2['dy_refund_need_merchant_confirm'] = $otherattr['refund_need_merchant_confirm'] && !empty($otherattr['refund_need_merchant_confirm'])?1:0;
                            $data2['dy_superimposed_discounts']       = $otherattr['superimposed_discounts'] && !empty($otherattr['superimposed_discounts'])?1:0;

                            if($otherattr['use_date']){
                                $data2['dy_use_date_type'] = $otherattr['use_date_type'] && !empty($otherattr['use_date_type'])?$otherattr['use_date_type']:0;
                                if($data2['dy_use_date_type'] == 1){
                                    if($otherattr['use_date']['use_start_date'] && !empty($otherattr['use_date']['use_start_date'])){
                                        $data2['dy_use_start_date']= strtotime($otherattr['use_date']['use_start_date']);
                                    }
                                    if($otherattr['use_date']['use_end_date'] && !empty($otherattr['use_date']['use_end_date'])){
                                        $data2['dy_use_end_date']= strtotime($otherattr['use_date']['use_end_date']);
                                    }
                                }
                                if($data2['dy_use_date_type'] == 2){
                                    if($otherattr['use_date']['use_day_duration'] && !empty($otherattr['use_date']['use_day_duration'])){
                                        $data2['dy_use_day_duration']= $otherattr['use_date']['use_day_duration'];
                                    }
                                }
                            }
                            if($otherattr['Notification'] && !empty($otherattr['Notification'])){
                                $data2['dy_Notification'] = json_encode('Notification');
                            }
                            $data2['dy_show_channel'] = $otherattr['show_channel'] && !empty($otherattr['show_channel'])?$otherattr['show_channel']:0;
                            $data2['dy_commodity'] = $otherattr['commodity'] && !empty($otherattr['commodity'])?$otherattr['commodity']:0;
                            $data2['createtime'] = time();
                            Db::name('shop_product_douyin_groupbuy')->insertGetid($data2);
                        }
                    }
                }
            }
        }
    }
    private static function deal_getdyproduct2($aid,$bid=0,$type=1,$poi_ids=[],$account_id=0,$cursor=1,$count=5){
        //抖音商品
        $res = \app\custom\DouyinGroupbuyCustom::getdyproduct($aid,$bid,$set['account_id'],50);
        if($res['status'] ==1){
            //处理商品同步数据库
            self::deal_getdyproduct($aid,$bid,$type,$poi_ids,$res);
            //用于查询下一页
            $next_cursor = $res['data']['data']['next_cursor'];
            //是否有下一页
            $has_more = $res['data']['data']['has_more'];
            if($next_cursor && $has_more){
                //继续获取
                self::deal_getdyproduct2(aid,bid,$type,$poi_ids,$set['account_id'],$next_cursor,50);
            }
        }
    }
    public function tongbu(){
        if(getcustom('douyin_groupbuy')){
            $id = input('id')?input('id/d'):0;
            $product = Db::name('shop_product')->where('id',$id)->where('aid',aid)->where('bid',bid)->find();
            $oldinfo = Db::name('shop_product_douyin_groupbuy')->where('proid',$id)->where('aid',aid)->where('bid',bid)->find();
            if(request()->isPost()){
                if(!$product){
                    return json(['status'=>0,'msg'=>'系统商品不存在']);
                }
                if($oldinfo['dy_product_id']>0){
                    return json(['status'=>0,'msg'=>'已创建过，不能再次提交']);
                }
                $info = input('info/a');
                //创建商品
                $checkset = \app\custom\DouyinGroupbuyCustom::checkset(aid,bid);
                if($checkset['status'] == 0){
                    return json($checkset);
                }
                $set = $checkset['set'];
                if(empty($set['account_id']) || empty($set['account_name'])){
                    return json(['status'=>0,'msg'=>'抖音团购设置不完善']);
                }

                $pois = input('post.pois/a');
                if(!$pois || empty($pois)){
                    return json(['status'=>0,'msg'=>'请选择门店']);
                }
                $info['pois'] = implode(',',$pois);

                if(!$info['dy_use_start_date'] || empty($info['dy_use_start_date'])){
                    return json(['status'=>0,'msg'=>'请选择使用开始时间']);
                }
                $info['dy_use_start_date']  = strtotime($info['dy_use_start_date'] );

                if(!$info['dy_use_end_date'] || empty($info['dy_use_end_date'])){
                    return json(['status'=>0,'msg'=>'请选择使用结束时间']);
                }
                $info['dy_use_end_date']  = strtotime($info['dy_use_end_date'] );

                if(!$info['dy_sold_start_time'] || empty($info['dy_sold_start_time'])){
                    return json(['status'=>0,'msg'=>'请选择售卖开始时间']);
                }
                $info['dy_sold_start_time']  = strtotime($info['dy_sold_start_time'] );

                if(!$info['dy_sold_end_time']){
                    return json(['status'=>0,'msg'=>'请选择售卖结束时间']);
                }
                $info['dy_sold_end_time']  = strtotime($info['dy_sold_end_time'] ); 

                $Notification = array();
                $posttitle = input('post.title/a');
                $postcontent = input('post.content/a');
                foreach($posttitle as $k=>$title){
                    $Notification[] = array(
                        'title'=>$title,
                        'content'=>$postcontent[$k],
                    );
                }
                $info['dy_Notification'] = json_encode($Notification,JSON_UNESCAPED_UNICODE);

                // if(!$product['pic']){
                //     return json(['status'=>0,'msg'=>'请先设置系统商品的主图']);
                // }
                if(!$product['pics']){
                    return json(['status'=>0,'msg'=>'请先设置系统商品的多图图片']);
                }

                //多图
                $pics = [];
                if($product['pics']){
                    $pics = explode(',',$product['pics']);
                    foreach($pics as $pv){
                        $pics[] = ['url'=>$pv];
                    }
                }

                //商品组信息
                $commoditys = input('commoditys')?input('commoditys'):'';
                if(!$commoditys){
                    return json(['status'=>0,'msg'=>'请先设置商品搭配']);
                }
                $group_names   = $commoditys['group_name'];//组名称
                if(!$group_names){
                    return json(['status'=>0,'msg'=>'请先设置商品组名称']);
                }

                $option_counts = $commoditys['option_count'];//可选商品数量

                $names = $commoditys['name'];//单品名称
                if(!$names){
                    return json(['status'=>0,'msg'=>'请先设置单品名称']);
                }

                $counts = $commoditys['count'];//单品数量
                if(!$counts){
                    return json(['status'=>0,'msg'=>'请先设置单品数量']);
                }

                $units = $commoditys['unit'];//单品单位
                if(!$units){
                    return json(['status'=>0,'msg'=>'请先设置单品单位']);
                }

                $prices = $commoditys['price'];//单品价格
                if(!$prices){
                    return json(['status'=>0,'msg'=>'请先设置单品价格']);
                }

                $dy_commodity = [];
                foreach($group_names as $gk=>$gv){
                    $data = [];

                    if(!$gv || empty($gv)){
                        return json(['status'=>0,'msg'=>'请先设置商品组名称']);
                        break;
                    }
                    $data['group_name'] = $gv;

                    $gnames = $names[$gk];
                    if(!$gnames){
                        return json(['status'=>0,'msg'=>'请先设置'.$gv.'的单品设置']);
                        break;
                    }
                    $data['total_count']  = count($gnames);

                    if($option_counts && $option_counts[$gk]){
                        if($option_counts[$gk]>$data['total_count']){
                            return json(['status'=>0,'msg'=>$gv.'的可选商品数量不能大于单品设置个数']);
                            break;
                        }
                        $data['option_count'] = $option_counts[$gk]>=1?$option_counts[$gk]:$data['total_count'];
                    }else{
                        $data['option_count'] = $data['total_count'];
                    }

                    $data['item_list'] = [];
                    $gcounts = $counts[$gk];
                    if(!$gcounts){
                        return json(['status'=>0,'msg'=>'请先设置'.$gv.'的单品数量']);
                        break;
                    }
                    $gunits = $units[$gk];
                    if(!$gunits){
                        return json(['status'=>0,'msg'=>'请先设置'.$gv.'的单品单位']);
                        break;
                    }
                    $gprices = $prices[$gk];
                    if(!$gprices){
                        return json(['status'=>0,'msg'=>'请先设置'.$gv.'的单品价格']);
                        break;
                    }

                    foreach($gnames as $nk=>$nv){
                        $gdata = [];
                        $gdata['name'] = $nv;
                        $gdata['count']= $gcounts[$nk]?$gcounts[$nk]:0;
                        $gdata['unit'] = $gunits[$nk]?$gunits[$nk]:'';
                        if(!$gdata['unit']){
                            return json(['status'=>0,'msg'=>'请先设置'.$gv.'的单品单位']);
                            break;
                        }
                        $gdata['price'] = $gprices[$nk]?$gprices[$nk]:'';
                        if($gdata['price']<0){
                            return json(['status'=>0,'msg'=>$gv.'的单品价格必须大于0']);
                            break;
                        }
                        array_push($data['item_list'],$gdata);
                    }
                    array_push($dy_commodity,$data);
                }

                $info['dy_commodity'] = json_encode($dy_commodity);
                if($info['id']){
                    $info['updatetime'] = time();
                    $sql = Db::name('shop_product_douyin_groupbuy')->where('id',$info['id'])->update($info);
                }else{
                    $info['prod'] = $id;
                    $info['aid']  = aid;
                    $info['bid']  = bid;
                    $info['createtime'] = time();
                    $sql = Db::name('shop_product_douyin_groupbuy')->insert($info);
                }

                if($sql){
                    //处理详情
                    $detail = json_decode($product['detail'],true);
                    $Description = '无';
                    if($detail){
                        /*preg_match_all('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is',$detail[0]['content'],$matches);*/
                        $Description = $detail[0]['content'];
                    }

                    $dy_pois = [];
                    foreach($pois as $dv){
                        $dy_pois['poi_id'] = $dv;
                    }
                    unset($dv);

                    $data = [];
                    $data['account_id'] = $set['account_id'];
                    $data['product']    = [
                        'account_name'      => $set['account_name'],
                        'attr_key_value_map'=> [
                            'appointment' => ['need_appointment'=>false],//预约信息
                            'auto_renew'    => false,//是否开启自动延期
                            'bring_out_meal'=> $info['dy_bring_out_meal']?true:false,//是否可以外带餐食
                            "can_no_use_date"        => ["enable"=>false],//提示里注明的不可使用日期，可以天、星期和节日
                            "Description"            => $Description,//详情
                            //"environment_image_list" => $environment_image_list,//环境图片
                            "free_pack"              => $info['free_pack']?true:false,//是否可以打包
                            "image_list"             => $pics,//封面图片
                            'Notification'           => $Notification,//使用规则
                            'private_room'           => false,//是否可以使用包间
                            'rec_person_num'         => $info['dy_rec_person_num'],//建议使用人数
                            'rec_person_num_max'     => $info['dy_rec_person_num_max'],//最多使用人数
                            'RefundPolicy'           => $info['dy_RefundPolicy'],//退款政策
                            'refund_need_merchant_confirm' => $info['dy_refund_need_merchant_confirm']?true:false,//退款是否需商家审核
                            'show_channel'           => 1,//投放渠道 1-不限制 2-仅直播间可见 5-仅线下 8-仅线上
                            'superimposed_discounts' => $info['superimposed_discounts']?true:false,//可以享受店内其他优惠
                            "use_date" => ["use_date_type" => 1,"use_start_date" => date("Y-m-d",$info['dy_use_start_date']),"use_end_date" => date("Y-m-d",$info['dy_use_end_date'])],//券码的可以核销日期，履约核销强依赖
                            "use_time" => ["use_time_type" => 1],//用户可以消费的时间 1营业时间可用 2：仅指定时间可用
                        ],
                        'biz_line'          => 2,
                        'category_id'       => $info['dy_category_id'],
                        'out_id'            => $id,
                        'pois'              => $dy_pois,
                        'product_name'      => $product['name'],
                        'product_type'      => 1,
                        'sold_end_time'     => $info['dy_sold_start_time'],
                        'sold_start_time'   => $info['dy_sold_end_time'],
                    ];
                    $data['sku']    = [
                        'actual_amount'     => $info['dy_actual_amount']*100,
                        // 'attr_key_value_map'=> [
                               "code_source_type" => 1,//券码生成方式 "1-抖音码 2-三方码 3-预导码",
                        //     "commodity"        =>[

                        //     ],
                                "limit_rule"       => ["is_limit" => false],//券码限用规则 不限制 按人数限制 按桌限制
                               "settle_type"      => 1,//收款方式 1-总店结算 2-分店结算 3-区域结算"
                               //"use_type"         => 1,//团购使用方式 "1-到店核销",默认值
                        // ],
                        'origin_amount'     => $info['dy_origin_amount']*100,
                        'sku_name'          => $info['dy_sku_name'],
                        'status'            => 1,
                        'stock'             => [
                            'limit_type' => $info['dy_limit_type'],
                            'stock_qty'  => $info['dy_stock_qty'],
                        ],
                    ];
                    //创建到抖音
                    $res = \app\custom\DouyinGroupbuyCustom::tongbu_product(aid,bid,$data);
                    if($res['status'] ==1){
                        $product_id = $res['data']['data']['product_id'];
                        //更新抖音商品id
                        $updata = [];
                        $updata['dy_account_id'] = $set['account_id'];
                        $updata['dy_pois']       = json_encode($dy_pois);
                        $updata['dy_product_id'] = $res['data']['data']['product_id'];
                        $updata['updatetime']    = time();
                        Db::name('shop_product_douyin_groupbuy')->where('id',$info['id'])->update($updata);
                        Db::name('shop_product')->where('id',$info['proid'])->update(['dyg_product_id'=>$updata['dy_product_id']]);
                        \app\common\System::plog('创建抖音团购商品'.$id.":".$updata['dy_product_id']);
                        return json(['status'=>1, 'msg'=>'创建完成']);
                    }else{
                        $msg = $res && $res['msg']?$res['msg']:'创建失败';
                        return json(['status'=>0, 'msg'=>$msg]);
                    }
                }else{
                    return json(['status'=>0, 'msg'=>'操作失败']);
                }
            }else{
                if(!$product){
                    showmsg('系统商品不存在');
                }
                if($oldinfo['dy_use_start_date']){
                    $oldinfo['dy_use_start_date'] = date("Y-m-d",$oldinfo['dy_use_start_date']);
                }
                if($oldinfo['dy_use_end_date']){
                    $oldinfo['dy_use_end_date'] = date("Y-m-d",$oldinfo['dy_use_end_date']);
                }
                if($oldinfo['dy_sold_start_time']){
                    $oldinfo['dy_sold_start_time'] = date("Y-m-d H:i:s",$oldinfo['dy_sold_start_time']);
                }
                if($oldinfo['dy_sold_end_time']){
                    $oldinfo['dy_sold_end_time'] = date("Y-m-d H:i:s",$oldinfo['dy_sold_end_time']);
                }
                //获取分类
                $clist = Db::name('douyin_groupbuy_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('id')->select()->toArray(); 
                foreach($clist as $k=>$v){
                    $child = Db::name('douyin_groupbuy_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('id')->select()->toArray();
                    foreach($child as $k2=>$v2){
                        $child2 = Db::name('douyin_groupbuy_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('id')->select()->toArray();
                        $child[$k2]['child'] = $child2;
                    }
                    $clist[$k]['child'] = $child;
                }
                $product_types = [
                    ['id'=>1,'name'=>'团购套餐'],
                    //['id'=>4,'name'=>'日历房'],
                    //['id'=>5,'name'=>'门票'],
                    //['id'=>7,'name'=>'旅行跟拍'],
                    //['id'=>8,'name'=>'一日游'],
                    //['id'=>11,'name'=>'代金券'],
                    //['id'=>12,'name'=>'新预售'],
                    //['id'=>13,'name'=>'预定商品'],
                    //['id'=>15,'name'=>'次卡'],
                ];
                $biz_lines = [
                    //['id'=>1,'name'=>'闭环自研开发者（如酒旅预售券）'],
                    ['id'=>5,'name'=>'小程序'],
                ];
                $name = Db::name('shop_product')->where('id',$id)->where('aid',aid)->where('bid',bid)->value('name');
                View::assign('name',$name);

                if(!$oldinfo){
                    $oldinfo = ['id'=>'','proid'=>$id];
                }
                View::assign('info',$oldinfo);
                View::assign('categorys',$clist);
                View::assign('product_types',$product_types);
                View::assign('biz_lines',$biz_lines);
                $units = [
                    ['id'=>1,'name'=>'份']
                ];
                View::assign('units',$units);
                View::assign('units_json',json_encode($units));

                //获取门店
                $mendians = Db::name('mendian')->where('aid',aid)->where('poi_id !=""')->order('id desc')->field('id,name,poi_id')->select()->toArray();
                View::assign('mendians',$mendians);
                return View::fetch();
            }
        }
    }

    //同步商品到商户
    private function tongbuproduct($proids){
    }
}
