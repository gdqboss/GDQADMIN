<?php
// JK客户定制

//名片功能
namespace app\controller;
use think\facade\Db;
class ApiMingpian extends ApiCommon
{	
	public function index(){
		$set = Db::name('mingpian_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-5,'msg'=>'功能未启用']);
		$nologin = false;//是否不登录
		if(input('param.scene') && input('param.scene') == 1154){ //朋友圈卡片的场景
			$nologin = true;
		}
		if(!$nologin){
			$this->checklogin();
		}
		$where = [];
        $where[] = ['aid', '=', aid];
		if(input('param.id')){
			$where[] = ['id', '=', input('param.id/d')];
		}elseif (input('param.huomacode')){
            }else{
			$where[] = ['mid', '=', mid];
		}

		$info = Db::name('mingpian')->where($where)->find();
		if(!$info){
			if(input('param.id')){
				return json(['status'=>-4,'msg'=>t('名片').'不存在','url'=>'/pagesExt/mingpian/edit']);
			}else{
			    $tourl = '/pagesExt/mingpian/edit';
			    if(getcustom('nfc_open_wx')) $tourl .='?huomacode='.input('param.huomacode');
				return json(['status'=>-4,'msg'=>'请先创建'.t('名片'),'url'=>$tourl]);
			}
		}
        $pagecontent = json_decode(\app\common\System::initpagecontent($info['detail'],aid),true);
		if(!$pagecontent) $pagecontent = [];
        $field_list = json_decode($set['field_list'],true);
		$newfield_list = [];
		$field_list2 = [];
		$i = 0;
		foreach($field_list as $k=>$v){
			$i++;
			if($i <= 3 && $v['isshow'] == 1){
				$field_list2[$k] = $v;
			}
			if($v['isshow'] == 1){
				$newfield_list[$k] = $v;
			}
		}

		if(getcustom('mingpian_addfields')){
			$addfields = [];//联系信息增加的字段
			if(!empty($info['addfields'])){
				$addfields = json_decode($info['addfields'],true);
			}
		}

		$viewmymp = false;
		if($this->member && $info['mid'] != mid){
			$addlog = true;//增加浏览记录
			if(getcustom('mingpian_addfields_membercustom')){
                //用户自定义显示字段功能开启，显示查看更多内容按钮
                $moreaddfields = true;
                $log = Db::name('mingpian_readlog')->where('mid',mid)->where('mpid',$info['id'])->where('aid',aid)->find();
                if($log){
                    $addlog = false;//不增加浏览记录
                    if($log['applysee'] != 2){
                        $addfields = [];
                    }else{
                        //批准可查看的信息
                        $seeaddfields = !empty($log['seeaddfields'])? json_decode($log['seeaddfields'],true):[];
                        $moreaddfields = false;
                    }
                }else{
                    $addfields = [];
                }

                //处理可显示的内容
                if($newfield_list){
                    foreach($newfield_list as $nk=>$nv){
                        $i2 ++;
                        //如果不是手机号、微信、地址前三个信息，则需要判断是否显示
                        if($nk != 'tel' && $nk != 'weixin'&& $nk != 'address'){
                            //如果显示更多信息按钮，则删除掉
                            if($moreaddfields){
                                unset($newfield_list[$nk]);
                            }else{
                                //如果为批准则删除掉
                                if(!$seeaddfields[$nk]) unset($newfield_list[$nk]);
                            }
                        }
                    }
                }
            }else{
				Db::name('mingpian_readlog')->where('aid',aid)->where('mid',mid)->where('mpid',$info['id'])->delete();
			}

			if($addlog){
				$data = [];
				$data['aid'] = aid;
				$data['mid'] = mid;
				$data['mpid'] = $info['id'];
				$data['createtime'] = time();
				Db::name('mingpian_readlog')->insert($data);
			}else{
				if(getcustom('mingpian_addfields_membercustom')){
					//更新时间
					if($log){
						Db::name('mingpian_readlog')->where('id',$log['id'])->update(['createtime'=>time()]);
					}
				}
			}

			$hasmp = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
			if($hasmp){
				$viewmymp = true;
			}
		}else{
			if(getcustom('mingpian_addfields_membercustom')){
				//查询查看更多字段申请数量
				$applyseenum = Db::name('mingpian_readlog')->where('mpid',$info['id'])->where('applysee',1)->where('aid',aid)->count('id');
			}
		}

		$open_haibao = 0;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['info'] = $info;
		$rdata['mid'] = mid;
		$rdata['pagecontent'] = $pagecontent;
		$rdata['field_list']  = $newfield_list;
		$rdata['field_list2'] = $field_list2?$field_list2:'';
		$rdata['viewmymp'] = $viewmymp;
		$rdata['open_haibao'] = $open_haibao;
		$rdata['islogin'] = false;//是否登录
		$rdata['bgbtncolor'] = $set['bgbtncolor']??0;
		if($this->member && mid ){
			$rdata['islogin'] = true;
		}
		if(getcustom('mingpian_addfields')){
			$rdata['addfields']     = $addfields;
		}

        if(getcustom('mingpian_addfields_membercustom')){
            $rdata['moreaddfields'] = $moreaddfields?true:false;
            $rdata['applyseenum']   = $applyseenum && $applyseenum>0?$applyseenum:0;
        }
		return $this->json($rdata);
	}
	//谁看过
	public function readlog(){
		$this->checklogin();
		$id = input('param.id/d');
		$info = Db::name('mingpian')->where('id',$id)->find();
		if(!$info || $info['mid'] != mid) return json(['status'=>-5,'msg'=>'无权限查看']);
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mpid','=',$id];
		$datalist = Db::name('mingpian_readlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$memberinfo = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
			$datalist[$k]['headimg'] = $memberinfo['headimg'];
			$datalist[$k]['nickname'] = $memberinfo['nickname'];
		}

        return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//谁收藏了
	public function favoritelog(){
		$this->checklogin();
		$id = input('param.id/d');
		$info = Db::name('mingpian')->where('id',$id)->find();
		if(!$info || $info['mid'] != mid) return json(['status'=>-5,'msg'=>'无权限查看']);
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mpid','=',$id];
		$datalist = Db::name('mingpian_favorite')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$memberinfo = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
			$datalist[$k]['headimg'] = $memberinfo['headimg'];
			$datalist[$k]['nickname'] = $memberinfo['nickname'];
		}
        return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//编辑名片
	public function edit(){
		$this->checklogin();
		$set = Db::name('mingpian_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-5,'msg'=>'功能未启用']);

		
		$createtj = explode(',',$set['createtj']);
		if(!in_array('-1',$createtj) && !in_array($this->member['levelid'],$createtj)){ //不是所有人
			return $this->json(['status'=>-5,'msg'=>'您没有权限创建'.t('名片')]);
		}

		$info = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
		if(!$info) $info = [];

		$bglist = $set['bgpics'] ? explode(',',$set['bgpics']) : [];
		
		$pagecontent = json_decode(\app\common\System::initpagecontent($info['detail'],aid),true);
		if(!$pagecontent) $pagecontent = [];

		$field_list = json_decode($set['field_list'],true);

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['info'] = $info;
		$rdata['bglist'] = $bglist;
		$rdata['field_list'] = $field_list;
		$rdata['pagecontent'] = $pagecontent;
		if(getcustom('mingpian_addfields')){
			$addfields = [];//增加的部分
			if(!empty($info['addfields'])){
				$addfields = json_decode($info['addfields'],true);
			}
			$rdata['addfields'] = $addfields?$addfields:'';
		}
		return $this->json($rdata);
	}
	//保存名片
	public function save(){
		$this->checklogin();
		$mingpian = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
		$info = input('post.info/a');
		$data = array();
		$data['aid'] = aid;
		$data['mid'] = mid;
		$data['bgpic'] = $info['bgpic'];
		$data['headimg'] = $info['headimg'];
		$data['realname'] = $info['realname'];
		$touxianArr = [];
		if($info['touxian1']) $touxianArr[] = $info['touxian1'];
		if($info['touxian2']) $touxianArr[] = $info['touxian2'];
		if($info['touxian3']) $touxianArr[] = $info['touxian3'];

		$data['touxian1'] = $touxianArr[0];
		$data['touxian2'] = $touxianArr[1] ?? '';
		$data['touxian3'] = $touxianArr[2] ?? '';
		$data['tel'] = $info['tel'];
		$data['weixin'] = $info['weixin'];
		$data['address'] = $info['address'];
		$data['email'] = $info['email'];
		$data['douyin'] = $info['douyin'];
		$data['weibo'] = $info['weibo'];
		$data['toutiao'] = $info['toutiao'];
		$data['field1'] = $info['field1'] ?? '';
		$data['field2'] = $info['field2'] ?? '';
		$data['field3'] = $info['field3'] ?? '';
		$data['field4'] = $info['field4'] ?? '';
		$data['field5'] = $info['field5'] ?? '';
		$data['sharetitle'] = $info['sharetitle'] ?? '';
		$data['longitude'] = $info['longitude'] ?? '';
		$data['latitude'] = $info['latitude'] ?? '';
		
		$data['detail'] = json_encode(input('post.pagecontent'));
		
		if(!$mingpian){
			$data['createtime'] = time();
		}
		$data['updatetime'] = time();

		if(getcustom('mingpian_addfields')){
			//处理增加的联系信息字段
			$set = Db::name('mingpian_set')->where('aid',aid)->find();
			if($set && !empty($set['field_list'])){
				$field_list = json_decode($set['field_list'],true);
				$addfields = [];
				foreach($field_list as $fk=>$fv){
					if($fv['isadd'] && $info[$fk]){
						$addfields[$fk] = $info[$fk];
					}
				}
				$data['addfields'] = $addfields?json_encode($addfields):'';
			}
		}
        if($mingpian){
			Db::name('mingpian')->where('aid',aid)->where('id',$mingpian['id'])->update($data);
		}else{
			$data['aid'] = aid;
			$proid = Db::name('mingpian')->insertGetId($data);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$this->checklogin();
		$id = input('post.id/d');
		$rs = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

	//存名片夹
	public function addfavorite(){
		$this->checklogin();
		$id = input('param.id/d');
		$info = Db::name('mingpian')->where('aid',aid)->where('id',$id)->find();
		if(!$info){
			return json(['status'=>0,'msg'=>'未找到该'.t('名片')]);
		}
		Db::name('mingpian_favorite')->where('aid',aid)->where('mid',mid)->where('mpid',$id)->delete();
		$data = [];
		$data['aid'] = aid;
		$data['mid'] = mid;
		$data['mpid'] = $info['id'];
		$data['createtime'] = time();
		Db::name('mingpian_favorite')->insert($data);
		return json(['status'=>1,'msg'=>'保存成功']);
	}
	//删名片夹
	public function delfavorite(){
		$this->checklogin();
		$id = input('param.id/d');
		Db::name('mingpian_favorite')->where('aid',aid)->where('mid',mid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//我的名片夹
	public function favorite(){
		$this->checklogin();
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('mingpian_favorite')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['info'] = Db::name('mingpian')->where('id',$v['mpid'])->find();
		}
		if($pagenum == 1){
			$set = Db::name('mingpian_set')->where('aid',aid)->find();
			$field_list = json_decode($set['field_list'],true);
			
			$newfield_list = [];
			$field_list2 = [];
			$i = 0;
			foreach($field_list as $k=>$v){
				$i++;
				if($i <= 3 && $v['isshow'] == 1){
					$field_list2[$k] = $v;
				}
				if($v['isshow'] == 1){
					$newfield_list[$k] = $v;
				}
			}
		}
		$newfield_list = $newfield_list?$newfield_list:'';
		$field_list2   = $field_list2?$field_list2:'';
        return $this->json(['status'=>1,'data'=>$datalist,'field_list'=>$newfield_list,'field_list2'=>$field_list2]);
	}

	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/pagesExt/mingpian/index';
		$info = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
		$scene = 'id_'.$info['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','mingpian')->where('platform',$platform)->order('id')->find();

//		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','kecheng')->where('posterid',$posterset['id'])->find();
        //关闭缓存
        if(true || !$posterdata){
			
			$textReplaceArr = [
				'[头像]'=>$info['headimg'],
				'[姓名]'=>$info['realname'],
				'[电话]'=>$info['tel'],
				'[微信]'=>$info['weixin'],
				'[地址]'=>$info['address'],
				'[头衔1]'=>$info['touxian1'],
				'[头衔2]'=>$info['touxian2'],
				'[头衔3]'=>$info['touxian3'],
			];

			$poster = $this->_getposter(aid,0,$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'mingpian';
			$posterdata['poster'] = $poster;
            $posterdata['posterid'] = $posterset['id'];
			$posterdata['createtime'] = time();
//			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}

    public function applySeeaddfields(){
        if(getcustom('mingpian_addfields_membercustom')){
            $this->checklogin();
            if(request()->isPost()){

                $id = input('?param.id')?input('param.id/d'):0;
                $where = [];
                $where[] = ['aid', '=', aid];
                $where[] = ['id', '=', $id];
                $mingpian = Db::name('mingpian')->where($where)->field('id,mid')->find();
                if(!$mingpian){
                    return json(['status'=>0,'msg'=>t('名片').'不存在']);
                }

                //查询浏览记录
                $log = Db::name('mingpian_readlog')->where('mpid',$id)->where('mid',mid)->where('aid',aid)->order('id desc')->find();
                if($log){
                    if($log['applysee'] == 2){
                        return json(['status'=>0,'msg'=>'已申请通过，请刷新页面查看']);
                    }
                    if($log['applysee'] == 1){
                        return json(['status'=>0,'msg'=>'已申请过']);
                    }
                    $updata = [];
                    $updata['applysee'] = 1;
                    $updata['checkseetime'] = time();
                    $sql = Db::name('mingpian_readlog')->where('id',$log['id'])->update($updata);
                }else{
                    $data = [];
                    $data['aid'] = aid;
                    $data['mid'] = mid;
                    $data['mpid'] = $id;
                    $data['applysee'] = 1;
                    $data['checkseetime'] = time();
                    $data['createtime'] = time();
                    $sql = Db::name('mingpian_readlog')->insert($data);
                }

                if($sql){
                    //发公众号消息通知
                    $tmplcontentNew = [];
                    $tmplcontentNew['time14']  = date("Y年m月d日 H:i");//来访时间
                    $tmplcontentNew['phrase4'] = '申请查看';//来访目的
                    \app\common\Wechat::sendtmpl(aid,$mingpian['mid'],'tmpl_mingpianread',[],m_url('pagesExt/mingpian/readlog'),$tmplcontentNew);
                    return json(['status'=>1,'msg'=>'提交成功，等待对方审核']);
                }else{
                    return json(['status'=>0,'msg'=>'操作失败']);
                }
            }
        }
    }

    public function checkAddfields(){
        if(getcustom('mingpian_addfields_membercustom')){
            $this->checklogin();
            $id = input('?param.id')?input('param.id/d'):0;
                
            //查询浏览记录
            $log = Db::name('mingpian_readlog')
                ->alias('log')
                ->join('mingpian m','m.id=log.mpid')
                ->where('log.id',$id)
                ->where('m.mid',mid)
                ->where('log.aid',aid)
                ->field('log.id,log.mpid,log.applysee,log.seeaddfields')
                ->find();
            if(!$log){
                return json(['status'=>0,'msg'=>'申请记录不存在']);
            }
            // if($log['applysee'] == 2){
            //     return json(['status'=>0,'msg'=>'已通过申请']);
            // }
            if(request()->isPost()){
                $type = input('?param.type')?input('param.type/d'):0;
                $updata = [];
                $updata['applysee']     = $type && $type==1?2:-1;
                $updata['checkseetime'] = time();
                $updata['seeaddfields'] = input('?param.seeaddfields')? json_encode(input('param.seeaddfields'),true):'';
                $sql = Db::name('mingpian_readlog')->where('id',$id)->update($updata);
                if($sql){
                    return json(['status'=>1,'msg'=>'操作成功']);
                }else{
                    return json(['status'=>0,'msg'=>'操作失败']);
                }
            }else{
                //批准的可查看内容
                $seeaddfields = !empty($log['seeaddfields'])? json_decode($log['seeaddfields'],true):[];
                $set = Db::name('mingpian_set')->where('aid',aid)->field('field_list')->find();
                $info = Db::name('mingpian')->where('id',$log['mpid'])->where('aid',aid)->find();
                if(!$info) $info = [];
                $field_list = $set && !empty($set['field_list'])? json_decode($set['field_list'],true):'';
                if($field_list){
                    $i = 0;
                    foreach($field_list as $fk=>$fv){
                        $i++;
                        if($i<=3 || !$fv['isshow'] || $fv['isshow'] !=1){
                            unset($field_list[$fk]);
                        }else{
                            if($seeaddfields){
                                if(!isset($seeaddfields[$fk])){
                                    $seeaddfields[$fk]= false;
                                }
                            }else{
                                $seeaddfields[$fk]= false;
                            }
                        }
                    }
                }

                $rdata = [];
                $rdata['log']    = $log;
                $rdata['status'] = 1;
                $rdata['info']   = $info;
                $rdata['field_list'] = $field_list;

                //增加的部分
                $addfields = [];
                if(!empty($info['addfields'])){
                    $addfields = json_decode($info['addfields'],true);
                }
                $rdata['addfields'] = $addfields?$addfields:'';

                //批准的可查看内容
                $rdata['seeaddfields'] = $seeaddfields?$seeaddfields:[];
                return json($rdata);
            }
        }
    }

    public function mingpianlist(){
        if(getcustom('mingpian_addfields_membercustom')){
        	//所有名片信息列表
            $this->checklogin();
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $datalist = Db::name('mingpian')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if($pagenum == 1){
                $set = Db::name('mingpian_set')->where('aid',aid)->find();
                $field_list = json_decode($set['field_list'],true);
                $field_list2 = [];
                $i = 0;
                foreach($field_list as $k=>$v){
                    $i++;
                    //只读取前3个
                    if($i <= 3 && $v['isshow'] == 1){
                        $field_list2[$k] = $v;
                    }
                }
            }
            $field_list2 = $field_list2?$field_list2:'';
            return $this->json(['status'=>1,'data'=>$datalist,'field_list2'=>$field_list2]);
        }
    }
}