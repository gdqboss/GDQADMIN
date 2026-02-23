<?php
// JK客户定制

// custom_file(extend_certificate)
namespace app\controller;
use think\facade\Db;
class ApiCertificate extends ApiCommon
{   
    
    public function getlist(){
        $pagenum = input('param.pagenum/d');
        if(!$pagenum) $pagenum = 1;
        $where =[];
        $where[] = ['ischecked','=',1];
        if(input('param.keyword')){
            $cids = Db::name('certificate_category')->where('name','like','%'.input('param.keyword').'%')->column('id');
            if($cids){
                foreach($cids as $key=>$val){
                    $whereCid = [];
                    foreach($cids as $c2){
                        $whereCid[] = "find_in_set({$c2},cid)";
                    }
                    $where[] = Db::raw(implode(' or ',$whereCid));
                } 
            }
        }
        $set = Db::name('certificate_set')->where('aid',aid)->find();
        $set['gettj'] = explode(',',$set['gettj']);
//        if($this->member['levelid'])
//            $where[] = Db::raw("find_in_set(-1,gettj) or find_in_set(".$this->member['levelid'].",gettj)");
        $datalist = Db::name('certificate_list')->where('aid',aid)->page($pagenum,20)->where($where)->order('sort desc,id desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $key=>$val){
            $len = mb_strlen($val['name'],'UTF-8');
            if($len >= 3){
                $name = mb_substr($val['name'],0,1,'UTF-8').' x x';
            }elseif ($len ==2){
                $name = mb_substr($val['name'],0,1,'UTF-8').' x';
            }
            $datalist[$key]['name'] = $name;
            $datalist[$key]['tel'] = substr($val['tel'],0,7).'xxxx';
            $category = Db::name('certificate_category')->where('id','in',$val['cid'])->column('name');

            $datalist[$key]['cname'] = $category?implode('，',$category):'暂无';
//            $datalist[$key]['certificate_pic'] =explode(',',$val['certificate_pic'])[0];
            $datalist[$key]['auth'] = true;
            $val['gettj'] = explode(',',$val['gettj']);
            $val['gettj'] = array_filter($val['gettj']);

            if(empty($val['gettj'])){
                //系统设置
                if(!in_array('-1',$set['gettj'])){
                    if(!$this->member){
                        $datalist[$key]['auth'] = false;
                    }
                    if($this->member && !in_array($this->member['levelid'],$set['gettj'])){
                        $datalist[$key]['auth'] = false;
                    }
                }
            }else if(!empty($val['gettj']) && !in_array('-1',$val['gettj'])){
                //独立设置
                if(!$this->member){
                    $datalist[$key]['auth'] = false;
                }
                if($this->member && !in_array($this->member['levelid'],$val['gettj'])){
                    $datalist[$key]['auth'] = false;
                }
            }
        }
        return $this->json(['status'=>1,'data'=>$datalist,'set'=>$set ? $set : []]);
    }
    public function getmylist(){
        $pagenum = input('param.pagenum/d');
        if(!$pagenum) $pagenum = 1;
        $where =[];
        $where[] = ['mid','=',mid];
        if(input('param.keyword')){
            $cids = Db::name('certificate_category')->where('name','like','%'.input('param.keyword').'%')->column('id');
            $where[] = ['cid','in',$cids];
        }
        $datalist = Db::name('certificate_list')->where('aid',aid)->page($pagenum,20)->where($where)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $key=>$val){
            //证件名称
            $category = Db::name('certificate_category')->where('id','in',$val['cid'])->column('name');
           
            $datalist[$key]['cname'] = $category?implode(',',$category):'暂无';
            $datalist[$key]['certificate_pic'] =explode(',',$val['certificate_pic'])[0];
        }
        return $this->json(['status'=>1,'data'=>$datalist]);
    }
    //详情
    public function getdetail(){
        $id = input('param.id');
        $detail = Db::name('certificate_list')->where('aid',aid)->where('id',$id)->find();
        
        if(!$detail){
            return $this->json(['status' => 0, 'msg' => '信息不存在']);
        }
        $set = Db::name('certificate_set')->where('aid',aid)->find();
        $set['gettj'] = explode(',',$set['gettj']);

        $detail['auth'] = true;
        $detail['gettj'] = explode(',',$detail['gettj']);
        $detail['gettj'] = array_filter($detail['gettj']);
        if(empty($detail['gettj'])){
            //系统设置
            if(!in_array('-1',$set['gettj'])){
                if(!$this->member){
                    $detail['auth'] = false;
                }
                if($this->member && !in_array($this->member['levelid'],$set['gettj'])){
                    $detail['auth'] = false;
                }
            }
        }else if(!empty($detail['gettj']) && !in_array('-1',$detail['gettj'])){
            //独立设置
            if(!$this->member){
                $detail['auth'] = false;
            }
            if($this->member && !in_array($this->member['levelid'],$detail['gettj'])){
                $detail['auth'] = false;
            }
        }
        if(!$detail['auth']) {
            return $this->json(['status'=>-4,'msg'=>$set['uplevel_text'] ? $set['uplevel_text'] :'无权限查看，请先升级','url'=>$set['uplevel_url'] ? $set['uplevel_url'] :'/pagesExt/my/levelup']);
        }

        $detail['certificate_pic'] = $detail['certificate_pic'] ? explode(',',$detail['certificate_pic']) : [];
        $detail['idcard_pic_front'] = $detail['idcard_pic_front'] ? explode(',',$detail['idcard_pic_front']) : [];
        $detail['idcard_pic_back'] =$detail['idcard_pic_back'] ? explode(',',$detail['idcard_pic_back']) : [];
        $detail['cid'] = explode(',',$detail['cid']);
        $detail['job_id'] = explode(',',$detail['job_id']);
        $education_name = Db::name('certificate_education')->where('id',$detail['education'])->value('name');
        $detail['educationname'] = $education_name;
        $cname = Db::name('certificate_category')->where('id','in',$detail['cid'])->column('name');
        
        $detail['cname'] = implode(',',$cname);
        $job_name = Db::name('certificate_job')->where('id','in',$detail['job_id'])->column('name');
       
        $detail['job_name'] = implode(',',$job_name);
        if($detail['mid'] !=mid){
           $len = mb_strlen($detail['name'],'UTF-8');
           if($len >= 3){
               $name = mb_substr($detail['name'],0,1,'UTF-8').' x x';
           }elseif ($len ==2){
               $name = mb_substr($detail['name'],0,1,'UTF-8').' x';
           }
           $detail['name'] = $name;
           $detail['tel'] = substr($detail['tel'],0,7).'xxxx';
        }
        $checkstatus = ['0' => '待审核','1'=>'审核通过','审核驳回'];
        $detail['checkstatus'] = $checkstatus[$detail['ischecked']];
        $rdata = [];
        $rdata['status'] =1;
        $rdata['data'] = $detail;
        return $this->json($rdata);
    }
    //分类列表
    public function getclist(){
        $id = input('param.id');
        $detail = Db::name('certificate_list')->where('aid',aid)->where('id',$id)->find();
        
        $clist = Db::name('certificate_category')->where('aid',aid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
        
        $joblist = Db::name('certificate_job')->where('aid',aid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
        $tel = $this->member['tel']?$this->member['tel']:'';
        $educationlist = Db::name('certificate_education')->where('aid',aid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
        
        $catearr= Db::name('certificate_category')->where('aid',aid)->where('bid',bid)->column('name','id');
        $jobArr = Db::name('certificate_job')->where('aid',aid)->where('bid',bid)->column('name','id');
        return $this->json(['status'=>1,'info' => $detail,'cateArr' => $catearr,'clist'=>$clist,'joblist'=>$joblist,'educationlist'=>$educationlist,'jobArr' => $jobArr,'tel' => $tel]);
    }
    public function save(){
        $info = input('param.info');
        if(!$info){
            return $this->json(['status'=>0,'msg'=>'请输入提交内容']);
        }
        $id =  input('param.id');
        $info['ischecked'] = 0;
        $info['job_id'] = implode(',',$info['job_id']);
        $info['cid'] = implode(',',$info['cid']);
        if($id){
            $info['check_reason'] = '';
            $res = Db::name('certificate_list')->where('id',$id)->update($info);
        }else{
            $info['aid'] = aid;
            $info['bid'] = 0;
            $info['mid'] = mid;
            $info['createtime'] = time();
            $res = Db::name('certificate_list')->insert($info);
        }
        return $this->json(['status'=>1,'msg'=>'成功']);
    }
}