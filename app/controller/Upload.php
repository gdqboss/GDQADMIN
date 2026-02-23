<?php
// JK客户定制

// +----------------------------------------------------------------------
// | 文件上传
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;
class Upload extends Common
{
	public function group(){
		$groupArr = Db::name('admin_upload_group')->where('aid',aid)->where('uid',$this->uid)->where('pid',0)->order('sort desc,createtime')->column('id,name');
		if($groupArr){
            foreach ($groupArr as $k => $item){
                $children = Db::name('admin_upload_group')->where('aid',aid)->where('uid',$this->uid)->where('pid',$item['id'])->order('sort desc,createtime')->column('id,name');
                $groupArr[$k]['children'] = $children ? $children : [];
            }
        }
        return json(['status'=>1,'data'=>$groupArr]);
	}
	public function addgroup(){
        $pid = input('param.pid');
		$data = [];
		$data['aid'] = aid;
		$data['uid'] = $this->uid;
		$data['createtime'] = time();
		$data['name'] = input('param.name');
        $data['pid'] = $pid ? $pid : 0;
        $id = Db::name('admin_upload_group')->insertGetId($data);
        $item = Db::name('admin_upload_group')->where('id', $id)->find();
		return json(['status'=>1,'msg'=>"创建成功",'data'=>$item]);
	}
	public function editgroup(){
		$data['name'] = input('param.name');
		Db::name('admin_upload_group')->where('aid',aid)->where('uid',$this->uid)->where('id',input('param.gid/d'))->update(['name'=>input('param.name')]);
		return json(['status'=>1,'msg'=>'修改成功']);
	}
	public function delgroup(){
		Db::name('admin_upload_group')->where('aid',aid)->where('uid',$this->uid)->where('id',input('param.gid/d'))->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function changegroup(){
		Db::name('admin_upload')->where('aid',aid)->where('id','in',input('param.ids'))->update(['gid'=>input('param.gid/d')]);
		return json(['status'=>1,'msg'=>'移动成功']);
	}
	//上传
    public function index(){
		$file = request()->file('file');
		if($file){
			$remote = Db::name('sysset')->where('name','remote')->value('value');
			$remote = json_decode($remote,true);
			$maxwidth = ($remote['thumb']==1 ? $remote['thumb_width'] : 0);
			$maxheight = ($remote['thumb']==1 ? $remote['thumb_height'] : 0);
			try {
			    $upload_type =config('app.upload_type');
			    validate(['file'=>['fileExt:'.$upload_type]])->check(['file' => $file]);
                $rinfo = [];
                $rinfo['extension'] = strtolower($file->getOriginalExtension());
                $rinfo['name'] = $file->getOriginalName();
                $rinfo['bsize'] = $file->getSize();
                $filesizeMb = $rinfo['bsize']/1024/1024;
                $rinfo['hash'] = $file->sha1();
                //校验文件大小超出超过限制
                if(getcustom('file_size_limit')){
                    //校验总附件大小是否超出限制
                    if($remote['file_upload_limit'] > 0){
                        //仅跟随平台存储类型生效
                        $admin = Db::name('admin')->where('id',aid)->find();
                        $aremote = json_decode($admin['remote'],true);
                        if(in_array($aremote['type'],[0,1])){
                            if($admin['file_upload_limit'] == 0 || $admin['file_upload_limit'] == ''){
                                //0为继承平台限制
                                if($rinfo['bsize'] + $admin['file_upload_total'] > $remote['file_upload_limit']*1024*1024)
                                    return json(['status'=>0,'msg'=>'上传失败，附件上限为'.$remote['file_upload_limit'].'MB']);
                            }elseif($admin['file_upload_limit'] == -1){
                                //-1为不限制
                            }else{
                                if($rinfo['bsize'] + $admin['file_upload_total'] > $admin['file_upload_limit']*1024*1024)
                                    return json(['status'=>0,'msg'=>'上传失败，附件上限为'.$admin['file_upload_limit'].'MB']);
                            }
                        }
                    }

                    //校验单个上传是否超出限制
                    if($remote['file_limit_user'] == 1){
                        $set = Db::name('admin_set')->where('aid',aid)->find();
                        if(in_array($rinfo['extension'],config('app.upload_type_image_arr'))){
                            if($set['file_image_limit'] > 0 && $set['file_image_limit'] < $filesizeMb){
                                return json(['status'=>0,'msg'=>'图片上传最大尺寸为'.$set['file_image_limit'].'MB']);
                            }
                        }elseif(in_array($rinfo['extension'],config('app.upload_type_video_arr'))){
                            if($set['file_video_limit'] > 0 && $set['file_video_limit'] < $filesizeMb){
                                return json(['status'=>0,'msg'=>'视频上传最大尺寸为'.$set['file_video_limit'].'MB']);
                            }
                        }else{
                            if($set['file_other_limit'] > 0 && $set['file_other_limit'] < $filesizeMb){
                                return json(['status'=>0,'msg'=>'文件上传最大尺寸为'.$set['file_other_limit'].'MB']);
                            }
                        }
                    }else{
                        if(in_array($rinfo['extension'],config('app.upload_type_image_arr'))){
                            if($remote['file_image_limit'] > 0 && $remote['file_image_limit'] < $filesizeMb){
                                return json(['status'=>0,'msg'=>'图片上传最大尺寸为'.$remote['file_image_limit'].'MB']);
                            }
                        }elseif(in_array($rinfo['extension'],config('app.upload_type_video_arr'))){
                            if($remote['file_video_limit'] > 0 && $remote['file_video_limit'] < $filesizeMb){
                                return json(['status'=>0,'msg'=>'视频上传最大尺寸为'.$remote['file_video_limit'].'MB']);
                            }
                        }else{
                            if($remote['file_other_limit'] > 0 && $remote['file_other_limit'] < $filesizeMb){
                                return json(['status'=>0,'msg'=>'文件上传最大尺寸为'.$remote['file_other_limit'].'MB']);
                            }
                        }
                    }
                }
				$savename = \think\facade\Filesystem::putFile(''.aid,$file);//上传目录增加aid
				$file_remote=$filepath = 'upload/'.str_replace("\\",'/',$savename);
				$maxwidth = input('post.maxwidth') ? input('post.maxwidth') : $maxwidth;
				$maxheight = input('post.maxheight') ? input('post.maxheight') : $maxheight;
				// 压缩标识
				$is_yasuo = 0;
				if(in_array($rinfo['extension'],config('app.upload_type_image_arr'))) {
					$size = getimagesize(ROOT_PATH.$filepath);
					$rinfo['width'] = $size[0];
					$rinfo['height'] = $size[1];
					if($maxwidth > 0 && $maxheight > 0){				 
						if($rinfo['width'] > $maxwidth || $rinfo['height'] > $maxheight){
							$image = \think\Image::open(ROOT_PATH.$filepath);
							$filepath = substr($filepath,0,strlen($filepath) - strlen($rinfo['extension']) - 1).'_thumb.'.$rinfo['extension'];
							$image->thumb($maxwidth,$maxheight)->save(ROOT_PATH.$filepath,null,100);
							$size = getimagesize(ROOT_PATH.$filepath);
							$rinfo['width'] = $size[0];
							$rinfo['height'] = $size[1];
							$is_yasuo = 1;
						}
					}
				}

                $insert = array(
                    'aid' => $this->aid,
                    'bid' => bid,
                    'uid' => $this->uid,
                    'name' => '',
                    'dir' => date('Ymd'),
                    'url' => '',
                    'type' => 'jpg',
                    'width' => '',
                    'height' => '',
                    'bsize' => $rinfo['bsize'],
                    'hash' => $rinfo['hash'],
                    'createtime' => time(),
                    'gid'=> cookie('browser_gid') && cookie('browser_gid')!='-1' ? cookie('browser_gid') : '0'
                );
				$rinfo['url'] = PRE_URL.'/'.$filepath;
                $file_id = '';
				if(getcustom('wx_channels') ){
				    $other_param = input('other_param')?:'';
				    //上传到微信资质图片
                    if($other_param=='channels_qua'){
                        $appid = \app\common\WxChannels::defaultApp(aid,bid);
                        $res = \app\common\WxChannels::uploadQualification(aid,bid, $appid, $rinfo['url']);
                        if($res['status']==1){
                            $file_id = $res['file_id'];
                        }else{
                            return $res;
                        }
                        $insert['channels_file_id'] = $file_id;
                        $insert['other_param'] = $other_param;
                    }
                }
                $type_no = false;
                if(!in_array($rinfo['extension'],config('app.upload_type_no_oss_arr')) || $type_no ){
                    $imgurl = \app\common\Pic::uploadoss($rinfo['url']);
                    if($imgurl === false){
                        return json(['status'=>0,'msg'=>'附件设置未配置或配置错误']);
                    }
                    $rinfo['url'] = $imgurl;
                    if(getcustom('wx_channels') ){
                        $other_param = input('other_param')?:'';
                        //上传到微信图片,获取图片链接
                        if($other_param=='channels_wx'){
                            $appid = \app\common\WxChannels::defaultApp(aid,bid);
                            $res = \app\common\WxChannels::uploadImage(aid,bid, $appid, $rinfo['url']);
                            if($res['status']==1){
                                $rinfo['url'] = $res['img_url'];
                            }else{
                                return $res;
                            }
                            $insert['channels_file_id'] = $res['img_url'];
                            $insert['other_param'] = $other_param;
                        }
                        //上传到微信图片,获取图片media_id
                        if($other_param=='channels_wx_mediaid'){
                            $appid = \app\common\WxChannels::defaultApp(aid,bid);
                            $res = \app\common\WxChannels::uploadImage(aid,bid, $appid, $rinfo['url'],0);
                            if(!$res['status']){
                                return $res;
                            }
                            $insert['channels_file_id'] = $res['img_url'];
                            $insert['other_param'] = $other_param;
                        }
                    }
                    $insert['name'] = $rinfo['name'];
                    $insert['url'] = $rinfo['url'];
                    $insert['type'] = $rinfo['extension'];
                    $insert['width'] = $rinfo['width'];
                    $insert['height'] = $rinfo['height'];
					$rinfo['id'] = Db::name('admin_upload')->insertGetId($insert);
				}
                \app\common\System::plog('上传文件：'.$rinfo['url']);
                $this->updateSizeTotal($insert);
				//开启压缩并且已经压缩了
				if($remote['thumb'] == 1 && $remote['delete_origin'] == 1 && $is_yasuo == 1){
				   	@unlink(ROOT_PATH.$file_remote);			 		 		 									 
				}
				if(false){}else{
					return json(['status'=>1,'state'=>'SUCCESS','msg'=>'上传成功','url'=>$rinfo['url'],'info'=>$rinfo,'channels_file_id'=>$file_id]);
				}
			} catch (\think\exception\ValidateException $e) {
				return json(['status'=>0,'msg'=>$e->getMessage()]);
			}
		}else{
			$errorNo = $_FILES['file']['error'];
			switch($errorNo) {
				case 1:
					$errmsg = '上传的文件超过了 upload_max_filesize 选项限制的值';break;
				case 2:
					$errmsg = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';break;
				case 3:
					$errmsg = '文件只有部分被上传';break;
				case 4:
					$errmsg = '没有文件被上传';break;
				case 6:
					$errmsg = '找不到临时文件夹';break;
				case 7:
					$errmsg= '文件写入失败';break;
				default:
					$errmsg = '未知上传错误！';
			}
			return json(['status'=>0,'msg'=>$errmsg]);
		}
    }

    public function updateSizeTotal($upload,$batch = false)
    {
        if(getcustom('file_size_limit')){
            $where = [];
            $where[] = ['aid','=',$upload['aid']];
            $where[] = ['bid','=',$upload['bid']];
            $update = [];
            $update['file_upload_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
            if($batch){
                $update['file_image_total'] = Db::name('admin_upload')->where($where)->whereIn('type',config('app.upload_type_image_arr'))->sum('bsize');
                $update['file_video_total'] = Db::name('admin_upload')->where($where)->whereIn('type',config('app.upload_type_video_arr'))->sum('bsize');
                $update['file_other_total'] = Db::name('admin_upload')->where($where)->whereNotIn('type',array_merge(config('app.upload_type_image_arr'),config('app.upload_type_video_arr')))->sum('bsize');
            }else{
                if(in_array($upload['type'],config('app.upload_type_image_arr'))){
                    $where[] = ['type','in',config('app.upload_type_image_arr')];
                    $update['file_image_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                }elseif(in_array($upload['type'],config('app.upload_type_video_arr'))){
                    $where[] = ['type','in',config('app.upload_type_video_arr')];
                    $update['file_video_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                }else{
                    $where[] = ['type','not in',array_merge(config('app.upload_type_image_arr'),config('app.upload_type_video_arr'))];
                    $update['file_other_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                }
            }

            if($upload['bid'] > 0){
                Db::name('business')->where('aid',$upload['aid'])->where('id',$upload['bid'])->update($update);
                $where = [];
                $where[] = ['aid','=',$upload['aid']];
                $update = [];
                $update['file_upload_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                if(in_array($upload['type'],config('app.upload_type_image_arr'))){
                    $where[] = ['type','in',config('app.upload_type_image_arr')];
                    $update['file_image_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                }elseif(in_array($upload['type'],config('app.upload_type_video_arr'))){
                    $where[] = ['type','in',config('app.upload_type_video_arr')];
                    $update['file_video_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                }else{
                    $where[] = ['type','not in',array_merge(config('app.upload_type_image_arr'),config('app.upload_type_video_arr'))];
                    $update['file_other_total'] = Db::name('admin_upload')->where($where)->sum('bsize');
                }
                Db::name('admin')->where('id',$upload['aid'])->update($update);
            }else{
                Db::name('admin')->where('id',$upload['aid'])->update($update);
            }
        }
    }

	//浏览
	public function browser(){
		// 添加调试信息，输出当前用户ID
		header('X-Debug-Current-Uid: ' . $this->uid);
		header('X-Debug-Current-Aid: ' . aid);
		header('X-Debug-Current-Bid: ' . bid);
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		if(input('param.gid') != '-1'){
			$where[] = ['gid','=',input('param.gid/d')];
		}
		if(input('param.keyword') != ''){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		if(input('param.sort')==1){
			$sort = 'createtime asc';
		}elseif(input('param.sort')==3){
			$sort = 'name asc';
		}elseif(input('param.sort')==4){
			$sort = 'name desc';
		}else{
			$sort = 'createtime desc';
		}
		$field = 'id,name,url,type,size,createtime,width,height,dir';
		if(getcustom('wx_channels')){
		    $field .= ',channels_file_id';
		    if(input('other_param')){
		        $where[] = ['other_param','=',input('other_param')];
		    }
		}
		// 构建基础查询条件 - 直接显示所有图片，不限制用户
		$where[] = ['aid', '=', aid];
		$where[] = ['isdel', '=', 0];
		$where[] = ['platform', '=', 'ht'];
		$where[] = ['type', '<>', 'pem'];
		
		// 直接执行查询，显示所有图片
		$count = Db::name('admin_upload')->field($field)->where($where)->count();
		$rs = Db::name('admin_upload')->field($field)->where($where)->order($sort)->page($pagenum,28)->select()->toArray();
		$totalpage = ceil($count/28);
		return json(['status'=>1,'isdir'=>'0','data'=>$rs,'totalpage'=>$totalpage]);
	}
	//微信素材
	public function material(){
		set_time_limit(0);
		ini_set('memory_limit','1024M');
		$type = input('param.type') ? input('param.type') : 'image';
		$page = input('param.page') ? input('param.page') : 1;
		$access_token = \app\common\Wechat::access_token(aid,'mp');
		$url = 'https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token='.$access_token;
		//dump($url);
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		//dump($rs);
		$voice_count = $rs['voice_count'];
		$video_count = $rs['video_count'];
		$image_count = $rs['image_count'];
		$news_count = $rs['news_count'];
		$count = $rs[$type.'_count'];
		//获取素材
		$url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token='.$access_token;
		//dump($url);
		$data = [];
		$data['type'] = $type;
		$data['offset'] = ($page-1)*18;
		$data['count'] = 18;
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		//dump($rs);
		$artlist = [];
		if($rs['item']){
			$artlist = $rs['item'];
			foreach($artlist as $k=>$v){
				if($type=='voice' || $type=='video'){
					$material = Db::name('mp_material')->where('aid',aid)->where('media_id',$v['media_id'])->where('type',$type)->where('endtime','>',time())->find();
					if(!$material){
						$rs = request_post('https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.$access_token,jsonEncode(['media_id'=>$v['media_id']]));
						$path = 'upload/'.aid.'/';
                        if($type=='video'){
							$url = json_decode($rs,true)['down_url'];
							$name = date('Ym/d_His').rand(1000,9999).($type=='voice'?'.mp3':'.mp4');
							if (!file_exists(dirname(ROOT_PATH.$path.$name))) {
								mk_dir(dirname(ROOT_PATH.$path.$name));
							}
							file_put_contents(ROOT_PATH.$path.$name,curl_get($url));
							$url = PRE_URL.'/'.$path.$name;
						}else{
							$name = date('Ym/d_His').rand(1000,9999).($type=='voice'?'.mp3':'.mp4');
							if (!file_exists(dirname(ROOT_PATH.$path.$name))) {
								mk_dir(dirname(ROOT_PATH.$path.$name));
							}
							file_put_contents(ROOT_PATH.$path.$name,$rs);
							$url = PRE_URL.'/'.$path.$name;
						}
						$artlist[$k]['url'] = $url;
						Db::name('mp_material')->insert(['aid'=>aid,'url'=>$url,'media_id'=>$v['media_id'],'type'=>$type,'createtime'=>time()]);
					}else{
						$artlist[$k]['url'] = $material['url'];
					}
				}elseif($v['url'] && $v['media_id']){
					$material = Db::name('mp_material')->where('aid',aid)->where('media_id',$v['media_id'])->where('type',$type)->where('endtime','>',time())->find();
					if(!$material){
						$picurl = \app\common\Pic::tolocal($v['url']);
						$artlist[$k]['url'] = $picurl;
						Db::name('mp_material')->insert(['aid'=>aid,'url'=>$picurl,'media_id'=>$v['media_id'],'type'=>$type,'createtime'=>time()]);
					}else{
						$artlist[$k]['url'] = $material['url'];
					}
				}
			}
		}
		return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$artlist]);
	}

	//删除
	public function deletefile(){
		$list = Db::name('admin_upload')->where('aid',aid)->where('uid',$this->uid)->where('id','in',input('post.ids'))->select()->toArray();
		if($list){
            Db::name('admin_upload')->where('aid',aid)->where('uid',$this->uid)->where('id','in',input('post.ids'))->delete();
            foreach($list as $k=>$v){
                \app\common\Pic::deletepic($v['url']);
                \app\common\System::plog('删除文件：'.$v['url']);
            }
            $this->updateSizeTotal(['aid'=>aid,'bid'=>bid],true);
            return json(['status'=>1,'msg'=>'删除成功']);
        }
	}

	//图标库
	public function iconsvg(){
		if($_POST['op'] == 'init'){
			$historylist = Db::name('iconsvg_history')->field('iconid id,name,show_svg')->where(array('aid'=>aid,'uid'=>$this->uid))->limit(10)->order('createtime desc')->select();
			if(!$historylist) $historylist = [];

			$rs = curl_post('https://www.diandashop.com/index/api/iconsvg?op=init&domain='.$_SERVER['HTTP_HOST']);
			$rs = json_decode($rs,true);
			if($rs['status']==1){
				$collection = $rs['collection'];
				$iconslist = $rs['iconslist'];
			}else{
				$collection = [];
				$iconslist = [];
			}
			return json(['clist'=>$collection,'iconslist'=>$iconslist,'historylist'=>$historylist]);
		}
		if($_POST['op'] == 'geticonlist'){
			$rs = curl_post('https://www.diandashop.com/index/api/iconsvg?op=geticonlist&domain='.$_SERVER['HTTP_HOST'],['cid'=>$_POST['cid']]);
			$rs = json_decode($rs,true);
			if($rs['status']==1){
				$iconlist = $rs['iconlist'];
			}else{
				$iconlist = [];
			}
			return json(['iconlist'=>$iconlist]);
		}
		if($_POST['op'] == 'searchiconlist'){
			$pagenum = $_POST['pagenum'] ? $_POST['pagenum'] : 1;
			$keyword = $_POST['keyword'];

			$rs = curl_post('https://www.diandashop.com/index/api/iconsvg?op=geticonlist&domain='.$_SERVER['HTTP_HOST'],['pagenum'=>$pagenum,'keyword'=>$keyword]);
			$rs = json_decode($rs,true);
			if($rs['status']==1){
				$iconlist = $rs['iconlist'];
			}else{
				$iconlist = [];
			}
			return json(['iconlist'=>$iconlist]);
		}
		if($_POST['op'] == 'geticonurl'){
			$pngdata = $_POST['pngdata'];
			$pngdata = str_replace('data:image/png;base64,','',$pngdata);
			$pngdata = base64_decode(str_replace(' ','+',$pngdata));

			$dir = 'upload/'.aid.date('/Ymd');
			if(!is_dir(ROOT_PATH.'/'.$dir)) mk_dir(ROOT_PATH.'/'.$dir,0755,true);
			$filename = date('His').rand(1000,9999).'.png';
			$mediapath = $dir.'/'.$filename;

			file_put_contents(ROOT_PATH.'/'.$mediapath,$pngdata);
			$size = getimagesize(ROOT_PATH.'/'.$mediapath);

			$url = PRE_URL.'/'.$mediapath;
			$url = \app\common\Pic::uploadoss($url);
			$adata = [];
			$adata['aid'] = aid;
			$adata['uid'] = $this->uid;
			$adata['iconid'] = $_POST['iconid'];
			$adata['name'] = $_POST['name'];
			$adata['show_svg'] = $_POST['show_svg'];
			$adata['pngurl'] = $url;
			$adata['createtime'] = time();
			Db::name('iconsvg_history')->insert($adata);

			Db::name('admin_upload')->insertGetId(array(
				'aid' => $this->aid,
                'bid' => bid,
				'uid' => $this->uid,
				'name' => $adata['name'],
				'dir' => date('Ymd'),
				'url' => $adata['pngurl'],
				'type' => 'png',
				'width' => $size[0],
				'height' => $size[1],
				'createtime' => time(),
				'gid'=> '0'
			));

			return json(['status'=>1,'url'=>$url]);
		}
	}
	
	function sizecount($size){
		if($size >= 1073741824) {
			$size = round($size / 1073741824 * 100) / 100 . ' GB';
		} elseif($size >= 1048576) {
			$size = round($size / 1048576 * 100) / 100 . ' MB';
		} elseif($size >= 1024) {
			$size = round($size / 1024 * 100) / 100 . ' KB';
		} else {
			$size = $size . ' Bytes';
		}
		return $size;
	}

	public function ueditorconfig(){
		if(input('param.action') == 'config'){
			$config = [];
			$config['imageActionName'] = 'uploadimage';
			$config['imageFieldName'] = 'file';
			$config['imageUrlPrefix'] = '';
			return json($config);
		}elseif(input('param.action') == 'uploadimage'){
			return $this->index();
		}
	}

	//获取ffmpeg img
    public function ffmpeg_img(){
        if(false){}else{
        	return json(['status'=>1,'url'=>'']);
        }
    }
}
