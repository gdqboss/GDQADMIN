<?php
// JK客户定制


namespace app\model;

use think\Model;
use think\facade\Db;

class Commission extends Model
{
    //小市场业绩，去除业绩最高的一条线的业绩，其他的算小市场业绩
    public static function getMiniTeamCommission($aid,$mid,$starttime='',$endtime='')
    {
        if (getcustom('member_level_salary_bonus',$aid) || getcustom('team_minyeji_count',$aid)) {
            //直推会员
            $ztmembers = Db::name('member')->where('aid', $aid)->where('pid', $mid)->field('id,levelid,pid')->select()->toArray();
            //去除最高业绩的小部门业绩
            $maxYeji = 0;
            $totalYeji = 0;
            writeLog('-----'.$mid.'-----');
            foreach ($ztmembers as $ztk => $ztmember) {
                //直推部门业绩
                $yejiwhere = [];
                $yejiwhere[] = ['status', '=', 3];
                if($starttime || $endtime){
                    if($starttime){
                        $yejiwhere[] = ['collect_time', '>', $starttime];
                    }
                    if($endtime){
                        $yejiwhere[] = ['collect_time', '<', $endtime];
                    }
                }else{
                    $yejiwhere[] = ['collect_time', '<', time()];
                }
                $downmids = \app\common\Member::getteammids($aid, $ztmember['id']);
                $downmids[] = $ztmember['id'];
                if (empty($downmids)) {
                    continue;
                }
                $sumResult = Db::name('shop_order')->where('aid', $aid)->where('mid', 'in', $downmids)->where($yejiwhere)->field("sum(`totalprice`-`refund_money`) as totalamount")->find();
//                    dump(['amount'=>$sumResult['totalamount'],'mid'=>$ztmember['id']]);
                $teamYeji = $sumResult['totalamount'] ?round($sumResult['totalamount'],2): 0;
                writeLog('mid='.$ztmember['id'].'&amount='.$teamYeji);
                if ($teamYeji > $maxYeji) {
                    $maxYeji = $teamYeji;
                }
                $totalYeji = $totalYeji + $teamYeji;
            }
            $yejiAmount = round($totalYeji - $maxYeji,2);//去掉最大部门业绩算小部门业绩
            writeLog('-----'.$mid.'-----');
            return $yejiAmount;
        }
        return 0;
    }

    /**
     * 获取大、小市场业绩及团队业绩
     * 团队业绩包含会员自己
     */
    public static function getTeamYeji($aid,$mid,$starttime='',$endtime='')
    {
        if (getcustom('team_minyeji_count',$aid)) {
            $yejiwhere = [];
            $yejiwhere[] = ['status', 'in', [1,2,3]];
            if($starttime || $endtime){
                if($starttime){
                    $yejiwhere[] = ['createtime', '>', $starttime];
                }
                if($endtime){
                    $yejiwhere[] = ['createtime', '<', $endtime];
                }
            }else{
                $yejiwhere[] = ['createtime', '<', time()];
            }

            //直推会员
            $ztmembers = Db::name('member')->where('aid', $aid)->where('pid', $mid)->field('id,levelid,pid')->select()->toArray();
            //去除最高业绩的小部门业绩
            $maxYeji = 0;
            $min_yeji = 0;
            $yeji_arr = [];
            writeLog('-----'.$mid.'-----');
            foreach ($ztmembers as $ztk => $ztmember) {
                //直推部门业绩
                $downmids = \app\common\Member::getteammids($aid, $ztmember['id']);
                $downmids[] = $ztmember['id'];
                if (empty($downmids)) {
                    continue;
                }
                $totalamount = Db::name('shop_order_goods')->where('aid', $aid)->where('mid', 'in', $downmids)->where($yejiwhere)->sum('real_totalprice');
                $teamYeji = $totalamount?: 0;
                writeLog('mid='.$ztmember['id'].'&amount='.$teamYeji);
                $yeji_arr[] = $teamYeji;
            }
            if($yeji_arr){
                $maxYeji = max($yeji_arr);
                $totalYeji = array_sum($yeji_arr);
                $min_yeji = bcsub($totalYeji, $maxYeji,2);
            }
            //单独计算一次所有会员的团队业绩
            $downmids = \app\common\Member::getteammids($aid, $mid);
            $totalamount = Db::name('shop_order_goods')->where('aid', $aid)->where('mid', 'in', $downmids)->where($yejiwhere)->sum('real_totalprice');
//                    dump(['amount'=>$sumResult['totalamount'],'mid'=>$ztmember['id']]);
            $teamYeji = $totalamount?: 0;

            $self_yeji = Db::name('shop_order_goods')->where('aid', $aid)->where('mid', $mid)->where($yejiwhere)->sum('real_totalprice');
            $self_yeji = $self_yeji?: 0;
            $teamYeji = bcadd($teamYeji, $self_yeji,2);
            writeLog('-----'.$mid.'-----');
            return [
                'max_yeji' => $maxYeji,
                'total_yeji' => $teamYeji,
                'min_yeji' => $min_yeji,
                'self_yeji' => $self_yeji
            ];
        }
        return 0;
    }
}