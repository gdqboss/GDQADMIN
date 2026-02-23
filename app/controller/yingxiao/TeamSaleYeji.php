<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 团队业绩分红 custom_file(yx_team_yeji)

// +----------------------------------------------------------------------
namespace app\controller\yingxiao;
use app\controller\Common;
use think\facade\View;
use think\facade\Db;
use function Qiniu\waterImg;

class TeamSaleYeji extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//index
    public function index(){ 
        $this->defaultSet();
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $where = array();
            $where[] = ['aid','=',aid];
//            $where[] = ['path','<>',''];

            $date = date('Y-m');
            $month =  input('param.month',0);
            if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
            if(input('param.mid')) $where[] = ['id','=',input('param.mid')];
            $month_start = strtotime(date('Y-m-01 00:00:00'));
            $month_end  = strtotime(date('Y-m-t 23:59:59'));
            $year = input('param.year');
            if($year && $month){
                $month_start = strtotime(date($year.'-'.$month.'-01 00:00:00'));
                $month_end  = strtotime(date($year.'-m-t 23:59:59',$month_start));
            }elseif ($year && !$month){
                $month_start = strtotime(date($year.'-01-01 00:00:00'));
                $month_end  = strtotime(date($year.'-12-31 23:59:59'));
            }elseif (!$year && $month){
                $month = $month<10?'0'.$month:$month;
                $month_start = strtotime(date('Y-'.$month.'-01 00:00:00'));
                $month_end  = strtotime(date('Y-m-t 23:59:59',$month_start));
                $date = date('Y-'.$month);
            }
            $count = Db::name('member')->where($where)->order('id desc')->count();
            $data = Db::name('member')->where($where)->page($page,$limit)->order('id desc')->select()->toArray();
            $set = Db::name('team_yeji_set')->where('aid',aid)->find();
            $config = json_decode($set['config_data'],true);
            foreach($data as $key=>$val){
                $this_month_start = $month_start;
                $this_month_end = $month_end;
                 //业绩
                $monthyejiwhere = [];
                $monthyejiwhere[] = ['status','in','1,2,3'];
                $deep = 999;
                 if($config[$val['levelid']]['levelnum'] > 0) $deep = intval($config[$val['levelid']]['levelnum']);
                 $downmids = \app\common\Member::getteammids(aid,$val['id'],$deep);
                 $is_include_self = getcustom('yx_team_yeji_include_self');
                if($is_include_self){
                    //包含自己 系统设置中
                    $teamyeji_include_self = Db::name('admin_set')->where('aid',aid)->value('teamyeji_include_self');
                    if($teamyeji_include_self) $downmids[] = $val['id'];
                }
                 //总业绩
                 $month_teamyeji = 0;
                 $toatlyeji = 0;
                 $totalwhere = [];
                 if($downmids){
                     $monthyejiwhere[] = ['createtime','between',[$month_start,$month_end]];
                     $month_teamyeji =0+ Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($monthyejiwhere)->sum('real_totalprice');
                     $toatlyeji=0+ Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where('status','in','1,2,3')->where($totalwhere)->sum('real_totalprice');
                 }
                 $data[$key]['month_teamyeji'] = $month_teamyeji;
                 $data[$key]['total_teamyeji'] = $toatlyeji;
                     
                 $xuni_where = [];
                 $xuni_where[] = ['aid','=',aid];
                 $xuni_where[] = ['mid','=',$val['id']];
                 $xuni_where[] = ['yeji_month','=',$date];
                 $xuni_yeji = 0 + Db::name('tem_yeji_xuni')->where($xuni_where)->value('yeji');
                 $data[$key]['xuniyeji'] = $xuni_yeji;
             }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $month_item = [];
        //今年的月份
        for($i=1;$i<= 12;$i++){
            $month_item[] = $i.'月';
        }
        View::assign('month_item',$month_item);
       
        return View::fetch();
    }
    //设置
	public function set(){
		if(request()->isAjax()){
//            dd(input('post.'));
			$info = input('post.info/a');
			$configdata = array();
			$postlevelid = input('post.levelid/a');
            $rangedata= input('post.range/a');
            $levelnum = input('post.levelnum/a');
           
            if(array_unique($postlevelid) != $postlevelid) return json(['status'=>0,'msg'=>'存在重复的等级，请修正数据']);
			foreach($postlevelid as $k=>$levelid){
                $configdata[$levelid] = array(
					'levelid'=>$levelid,
				);
                $range = $rangedata[$k];
                $new_range = [];
                foreach($range['start'] as $rk=>$r){
                    $new_range[$rk]['start'] = $range['start'][$rk];
                    $new_range[$rk]['end'] = $range['end'][$rk]; 
                    $new_range[$rk]['ratio'] = $range['ratio'][$rk]; 
                }
                $configdata[$levelid]['range'] = $new_range;
                $configdata[$levelid]['levelnum'] = intval($levelnum[$k]);
            }
          
			$info['config_data'] = json_encode($configdata,JSON_UNESCAPED_UNICODE);
            Db::name('team_yeji_set')->where('aid',aid)->update($info);
            \app\common\System::plog('团队业绩设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		$info = Db::name('team_yeji_set')->where('aid',aid)->find();
		if(!$info) $info = ['status'=>0];
        $config_data = json_decode($info['config_data'],true);
        $yejiset = [];
        foreach($config_data as $key=>$val){
            $yejiset[] = $val;
        }
        $info['config_data'] =$yejiset;
		View::assign('info',$info);

        $defaultCat = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $defaultCat = $defaultCat ? $defaultCat : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $defaultCat)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);
        return View::fetch();
	}
    //
    public function xuniyejiChange(){
        $mid = input('param.mid');
        $yeji = input('param.yeji');
        $month = input('param.month');
        if($month){
            $month = $month<10?'0'.$month:$month;
            $yeji_month = date('Y-'.$month);  
        }else{
            $yeji_month = date('Y-m');
        }
       
        $xuni = Db::name('tem_yeji_xuni')->where('aid',aid)->where('mid',$mid)->where('yeji_month',$yeji_month)->find();
        if($xuni){
            Db::name('tem_yeji_xuni')->where('aid',aid)->where('mid',$mid)->where('yeji_month',$yeji_month)->update(['yeji' => $yeji]);
        }else{
            $insert = [
                'aid' => aid,
                'yeji' => $yeji,
                'mid' => $mid,
                'yeji_month' => $yeji_month,
                'createtime' => time(),
            ];
            Db::name('tem_yeji_xuni')->insertGetId($insert) ;
            
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    public function defaultSet(){
        $set = Db::name('team_yeji_set')->where('aid',aid)->find();
        if(!$set){
            Db::name('team_yeji_set')->insert(['aid'=>aid,'createtime'=>time(),'jiesuan_type'=>1]);
        }
    }
    
}
