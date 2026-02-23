<template>
<view>
	<block v-if="isload">
		<form @submit="formsubmit">
		<view class="st_box">
			<view class="st_title flex-y-center">
				<view @tap="goback" style="width:100rpx"><image :src="pre_url+'/static/img/goback.jpg'"></image></view>
				<view style="margin-right:40%">我要发帖</view>

			</view>
			<view class="st_form">
				<view v-if="cateArr">
					<picker @change="cateChange" :value="cindex" :range="cateArr" style="height:80rpx;line-height:80rpx;border-bottom:1px solid #EEEEEE;font-size:18px">
						<view class="picker">{{cindex==-1? '请选择发帖类型' : cateArr[cindex]}}</view>
					</picker>
				</view>
                <view v-if="cate2 && cateArr2 && cateArr2.length>0">
                	<picker @change="cateChange2" :value="cindex2" :range="cateArr2" style="height:80rpx;line-height:80rpx;border-bottom:1px solid #EEEEEE;font-size:18px">
                		<view class="picker">{{cindex2==-1? '请选择二级分类' : cateArr2[cindex2]}}</view>
                	</picker>
                </view>
				<!-- <view v-if="cateArr">
					<picker @change="cateChange" :value="cindex" :range="cateArr" style="height:80rpx;line-height:80rpx;border-bottom:1px solid #EEEEEE;font-size:18px">
						<view class="picker">{{cindex==-1? '请选择曝光标签' : cateArr[cindex]}}</view>
					</picker>
				</view> -->
				<view><textarea placeholder="输入内容" name="content" maxlength="-1"></textarea></view>
				<view v-if="need_call">
						<input type="text" placeholder="输入联系电话" name="mobile"/>
				</view>
				<block v-if="isphone">
					<view>
							<input type="text" placeholder="请输入姓名" name="name"/>
					</view>
					<view>
							<input type="text" placeholder="请输入手机号" name="mobile"/>
					</view>
				</block>
				
				
				
				<view class="uploadbtn_ziti1">
					插入图片
				</view>
				<view class="flex" style="flex-wrap:wrap;padding-top:20rpx;">
					<view v-for="(item, index) in pics" :key="index" class="layui-imgbox">
						<view class="layui-imgbox-close" @tap="removeimg" :data-index="index" data-field="pics"><image :src="pre_url+'/static/img/ico-del.png'"></image></view>
						<view class="layui-imgbox-img"><image :src="item" @tap="previewImage" :data-url="item" mode="widthFix"></image></view>
						<!-- <view class="layui-imgbox-repeat" @tap="xuanzhuan" :data-index="index" data-field="pics"><text class="fa fa-repeat"></text></view> -->
					</view>
					<view class="uploadbtn" :style="'background:url('+pre_url+'/static/img/shaitu_icon.png) no-repeat 60rpx;background-size:80rpx 80rpx;background-color:#F3F3F3;'" @tap="uploadimg" data-field="pics" v-if="pics.length<9">
						
					</view>
					
				</view>
				
				<input type="text" hidden="true" name="pics" :value="pics.join(',')" maxlength="-1"></input>
			
			<view class="uploadbtn_ziti2">
				插入视频
			</view>
				<view class="flex-y-center" style="width:100%;padding:20rpx 0;margin-top:20rpx;">
					<image :src="pre_url+'/static/img/uploadvideo.png'" style="width:200rpx;height:200rpx;background:#eee;" @tap="uploadvideo"></image><text v-if="video" style="padding-left:20rpx;color:#333">已上传短视频</text></view>
				<input type="text" hidden="true" name="video" :value="video" maxlength="-1"></input>
			</view>
		</view>
		<view class="st_title flex-y-center">
			<button form-type="submit" :style="{background:'linear-gradient(-90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'}">发表</button>
		</view>
		<view style="width:100%;margin-top:20rpx;text-align:center;color:#999;display:flex;align-items:center;justify-content:center" @tap="goto" data-url="fatielog">我的发帖记录<image :src="pre_url+'/static/img/arrowright.png'" style="width:30rpx;height:30rpx"/></view>
		<view style="width:100%;height:50rpx"></view>
		</form>
	</block>
	<loading v-if="loading"></loading>
	<dp-tabbar :opt="opt"></dp-tabbar>
	<popmsg ref="popmsg"></popmsg>
</view>
</template>

<script>
var app = getApp();

export default {
  data() {
    return {
			opt:{},
			loading:false,
      isload: false,
			menuindex:-1,
			
			pre_url:app.globalData.pre_url,
      datalist: [],
      content_pic: [],
      pagenum: 1,
      cateArr: [],
      cindex: -1,
			pics:[],
      video: '',
      need_call:false,
      clist:[],
      
      clist2:[],
      cateArr2: [],
      cindex2: -1,
      cate2:false,
      displaytype:-1,
			isphone:false,
			iscatephone:false
    };
  },

  onLoad: function (opt) {
		this.opt = app.getopts(opt);
        this.displaytype = this.opt.displaytype || -1;
		this.getdata();
  },
	onPullDownRefresh: function () {
		this.getdata();
	},
  methods: {
		getdata: function () {
			var that = this;
			that.loading = true;
			app.get('ApiLuntan/fatie', {display_type:that.displaytype}, function (res) {
				that.loading = false;
				if (res.status == 0) {
					app.alert(res.msg);
					return;
				}
				that.clist = res.clist;
				that.iscatephone = res.iscatephone
				var clist = res.clist;
				if (clist.length > 0) {
					var cateArr = [];
					for (var i in clist) {
						if (that.opt && that.opt.cid == clist[i].id) {
							
							that.cindex = i;
							if(that.iscatephone){
									that.isphone = clist[that.cindex].isphone;
							}
							if(res.cate2){
									that.getCate2(that.opt.cid);
							}
						}
						cateArr.push(clist[i].name);
					}
				} else {
					cateArr = false;
				}
				that.cateArr = cateArr
				if(res.need_call){
						that.need_call = true;
				}
				if(res.cate2){
						that.cate2    = true;
				}
				that.loaded();
			});
		},
    cateChange: function (e) {
        var that = this;
        that.cindex = e.detail.value;

        var clist = that.clist;
        if (clist &&clist.length > 0) {
            var cid = clist[that.cindex].id;
						if(that.iscatephone){
								that.isphone = clist[that.cindex].isphone;
						}
        } else {
            var cid = 0;
        }
			
        if(that.cate2){
            that.getCate2(cid);
        }
    },
    cateChange2: function (e) {
      this.cindex2 = e.detail.value;
    },
    formsubmit: function (e) {
      var that = this;
      
      var clist = that.clist;

      if (clist.length > 0) {
        if (that.cindex == -1) {
          app.error('请选择分类');
          return false;
        }
        var cid = clist[that.cindex].id;
		
      } else {
        var cid = 0;
      }
      var cid2 =0;
      if(that.cate2){
          var clist2 = that.clist2;
          if (clist2.length > 0) {
            if (that.cindex2 == -1) {
              app.error('请选择二级分类');
              return false;
            }
            var cid2 = clist2[that.cindex2].id;
          } else {
            var cid2 = 0;
          }
      }
      var formdata = e.detail.value;
      var content = formdata.content;
      var pics = formdata.pics;
      var video = formdata.video;
      var mobile = formdata.mobile;
      if (content == '' && pics == '') {
        app.error('请输入内容');
        return false;
      }
			var name = formdata.name;
			if(that.isphone){
				if(!name){
					app.error('请输入姓名');return false;
				}
				if(!mobile){
					app.error('请输入手机号');	return false;
				}
				if (!app.isPhone(mobile)) {
					app.alert('手机号格式错误');return;
				}
			}
      
      app.post('ApiLuntan/fatie', {cid: cid,cid2:cid2,pics: pics,content: content,video: video,mobile: mobile,name:name}, function (res) {
        app.showLoading(false);
        if (res.status == 1) {
          app.success(res.msg);
          setTimeout(function () {
            app.goback(true);
          }, 1000);
        } else {
          app.error(res.msg);
        }
      });
    },
		uploadimg:function(e){
			var that = this;
			var field= e.currentTarget.dataset.field
			var pics = that[field]
			if(!pics) pics = [];
			app.chooseImage(function(urls){
				for(var i=0;i<urls.length;i++){
					pics.push(urls[i]);
				}
				if(field == 'pic') that.pic = pics;
				if(field == 'pics') that.pics = pics;
				if(field == 'zhengming') that.zhengming = pics;
			},9)
		},
    uploadvideo: function () {
      var that = this;
      console.log(11);
      uni.chooseVideo({
        sourceType: ['album', 'camera'],
        maxDuration: 60,
        success: function (res) {
          var tempFilePath = res.tempFilePath;
          app.showLoading('上传中');
          uni.uploadFile({
            url: app.globalData.baseurl + 'ApiImageupload/uploadImg/aid/' + app.globalData.aid + '/platform/' + app.globalData.platform + '/session_id/' + app.globalData.session_id,
            filePath: tempFilePath,
            name: 'file',
            success: function (res) {
              app.showLoading(false);
              var data = JSON.parse(res.data);

              if (data.status == 1) {
                that.video = data.url;
              } else {
                app.alert(data.msg);
              }
            },
            fail: function (res) {
              app.showLoading(false);
              app.alert(res.errMsg);
            }
          });
        },
        fail: function (res) {
          console.log(res); //alert(res.errMsg);
        }
      });
    },
		removeimg:function(e){
			var that = this;
			var index= e.currentTarget.dataset.index
			var field= e.currentTarget.dataset.field
			var pics = that[field]
			pics.splice(index,1)
		},
        getCate2: function (pid) {
        	var that = this;
        	app.post('ApiLuntan/getCate2', {pid:pid}, function (res) {
                if(res.status == 1){
                    var clist2 = res.data;
                    that.clist2 = clist2;
                    if (clist2.length > 0) {
                    	var cateArr2 = [];
                    	for (var i in clist2) {
                    		if (that.opt && that.opt.cid == clist2[i].id) {
                    			that.cindex = i;
                                if(res.cate2){
                                    that.getCate2(that.opt.cid);
                                }
                    		}
                    		cateArr2.push(clist2[i].name);
                    	}
                    } else {
                    	cateArr2 = false;
                    }
                    that.cateArr2 = cateArr2
                }else{
                    that.cateArr2 = [];
                    app.alert(res.msg)
                }
        	});
        },
  }
};
</script>
<style>
page{background:#f7f7f7}
.st_box{ padding: 20rpx 0 }
.st_title{ display: flex; justify-content: space-between;padding:24rpx;  }
.st_title1{ display: flex; justify-content: space-between;padding:24rpx;border-bottom: 1px solid #D0D0D0  }
.st_title image{width: 18rpx;height:32rpx}
.st_title text{ color:#242424; font-size: 36rpx}
/* .st_title button{ background: #31C88E; border-radius:6rpx; line-height: 48rpx;border: none; padding:0 20rpx ;color:#fff;margin:0} */


.st_title button{
    background: #1658c6;
    border-radius: 3px;
    line-height: 48rpx;
    border: none;
    padding: 0 20rpx;
    color: #fff;
    font-size: 20px;
    text-align: center;
    /* margin: 0; */
    width: 90%;
    display: flex;
    height: 100rpx;
    justify-content: center;
    align-items: center;}



.st_form{ padding: 24rpx;background: #ffffff;margin: 10px;border-radius: 15px;}
.st_form input{ width: 100%;height: 120rpx; border: none;border-bottom:1px solid #EEEEEE;}
.st_form input::-webkit-input-placeholder { /* WebKit browsers */ color:    #BBBBBB; font-size: 24rpx}
.st_form textarea{ width:100%;min-height:200rpx;padding:20rpx 0;border: none;border-bottom:1px solid #EEEEEE;}

.layui-imgbox{margin-right:16rpx;margin-bottom:10rpx;font-size:24rpx;position: relative;}
.layui-imgbox-img{display: block;width:200rpx;height:200rpx;padding:2px;border: #d3d3d3 1px solid;background-color: #f6f6f6;overflow:hidden}
.layui-imgbox-img>image{max-width:100%;}
.layui-imgbox-repeat{position: absolute;display: block;width:32rpx;height:32rpx;line-height:28rpx;right: 2px;bottom:2px;color:#999;font-size:30rpx;background:#fff}
.uploadbtn{position:relative;height:200rpx;width:200rpx}
.uploadbtn_ziti1{height:30rpx; line-height: 30rpx;font-size:30rpx; margin-top: 20rpx;}
.uploadbtn_ziti2{height:30rpx; line-height: 30rpx;font-size:30rpx; padding-top: 20rpx; margin-top: 20rpx;border-top:1px solid #EEEEEE;}
</style>