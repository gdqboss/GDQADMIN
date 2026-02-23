<?php
// JK客户定制

namespace app\custom;
use think\facade\Db;
class DesignerPageCustom
{   
    public static function deal_icback($aid){
        if(getcustom('yx_invite_cashback')) {
            $time = time();

            $data = [];
            //查询邀请返现设置是否有全部商品设置
            $icback_all = 0+Db::name('invite_cashback')->where('aid',$aid)->where('fwtype',0)->where('starttime','<=',$time)->where('endtime','>=',$time)->where('bid',0)->count();
            $data['icback_all'] = $icback_all?$icback_all:false;
            $data['cids']       = [];
            $data['proids']     = [];
            if(!$icback_all){
                $cids = [];
                //查询分类
                $cblist = Db::name('invite_cashback')->where('aid',$aid)->where('fwtype',1)->where('starttime','<=',$time)->where('endtime','>=',$time)->where('bid',0)->field('id,categoryids')->select()->toArray();
                if($cblist){
                    foreach($cblist as $cv){
                        if($cv['categoryids']){
                            $categoryids = explode(',',$cv['categoryids']);

                            $clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
                            if($clist){
                                    foreach($clist as $vc){
                                    $categoryids[] = $vc['id'];
                                    $cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
                                    $categoryids[] = $cate2['id'];
                                }
                            }
                            $cids = array_merge($cids,$categoryids);
                        }
                    }
                    unset($cv);
                }
                $data['cids'] = $cids;

                $proids = [];
                //查询商品
                $prolist = Db::name('invite_cashback')->where('aid',$aid)->where('fwtype',2)->where('starttime','<=',$time)->where('endtime','>=',$time)->where('bid',0)->field('id,productids')->select()->toArray();
                if($prolist){
                    foreach($prolist as $pv){
                        if($pv['productids']){
                            $productids = explode(',',$pv['productids']);
                            $proids = array_merge($proids,$productids);
                        }
                    }
                    unset($pv);
                }
                $data['proids'] = $proids;
            }
            return $data;
        }
    }
}