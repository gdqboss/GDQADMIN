<template>
<view>
	<block v-if="isload">
		<form @submit="subform">
			<view class="form-box">
				<block v-if="set.activity_time_custom">
					<view class="form-item">
						<view>活动时间设置</view>
						<view>
							<radio-group class="radio-group" name="activity_time_status" @change="">
								<label><radio value="0" :checked="!info || info.activity_time_status==0?true:false"></radio>关闭</label>
								<label><radio value="1" :checked="info.activity_time_status==1?true:false"></radio> 开启</label> 
							</radio-group>
						</view>
					</view>
					<view class="form-item">
						<view class="f1">活动时间</view>
						<view class="f2">
							<!-- <input type="text" name="name" :value="info.activity_time" placeholder="请填写名称" placeholder-style="color:#888"></input> -->
							<picker class="picker" mode="date" name="activity_time_start" :value="info.activity_time_start"   @change="activityTimeStartChange" >
								<view v-if="info.activity_time_start">{{info.activity_time_start}}</view>
								<view v-else>请选择</view>
							</picker>
							<view style="padding: 0 20rpx;"> ~ </view>
							<picker class="picker" mode="date" name="activity_time_end"   :value="info.activity_time_end" @change="activityTimeEndChange" >
								<view v-if="info.activity_time_end">{{info.activity_time_end}}</view>
								<view v-else>请选择</view>
							</picker>
						</view>
					</view>
				</block>
			</view>

			<button class="savebtn" :style="'background:linear-gradient(90deg,'+t('color1')+' 0%,rgba('+t('color1rgb')+',0.8) 100%)'" form-type="submit">提交</button>
			<view style="height:50rpx"></view>
		</form>

	</block>
	<loading v-if="loading"></loading>
</view>
</template>

<script>
var app = getApp();
import uniDatetimePicker from '@/pagesB/admin/uni-datetime-picker/uni-datetime-picker.vue'
export default {
  data() {
    return {
			isload:false,
			loading:false,
			pre_url:app.globalData.pre_url,
      info:{},
	  set:{}
		}
  },

  onLoad: function (opt) {
		this.opt = app.getopts(opt);
		this.getdata();
  },
  methods: {
		getdata:function(){
			var that = this;
			that.loading = true;
			app.get('ApiAdminQueueFree/getQueueFreeSet',{}, function (res) {
				that.loading = false;
				that.info = res.info;
				that.set = res.set;
				that.loaded();
			});
		},
    subform: function (e) {
      var that = this;
      var formdata = e.detail.value;

      var id = that.opt.id ? that.opt.id : '';
	  that.loading = true;
      app.post('ApiAdminQueueFree/saveQueueFreeSet', {info:formdata}, function (res) {
		  that.loading = false;
        if (res.status == 0) {
          app.error(res.msg);
        } else {
          app.success(res.msg);
          that.getdata();
        }
      });
    },
	activityTimeStartChange(e){
		var activity_time_start = e.detail.value;
		let activity_time_start_date = new Date(activity_time_start) //开始时间
		let activity_time_end_date = new Date(this.info.activity_time_end) //结束时间
		if(activity_time_start_date > activity_time_end_date){
			app.error('开始时间不能大于结束时间');
			return false;
		}
		this.info.activity_time_start = activity_time_start;
	},
	activityTimeEndChange(e){
		var activity_time_end = e.detail.value;
		let activity_time_start_date = new Date(this.info.activity_time_start) //开始时间
		let activity_time_end_date = new Date(activity_time_end) //结束时间
		if(activity_time_start_date > activity_time_end_date){
			app.error('结束时间不能小于开始时间');
			return false;
		}
		this.info.activity_time_end = activity_time_end;
	},
  }
};
</script>
<style>
radio{transform: scale(0.6);}
checkbox{transform: scale(0.6);}
.form-box{ padding:2rpx 24rpx 0 24rpx; background: #fff;margin: 24rpx;border-radius: 10rpx}
.form-item{ line-height: 100rpx; display: flex;justify-content: space-between;border-bottom:1px solid #eee }
.form-item .f1{color:#222;width:200rpx;flex-shrink:0}
.form-item .f2{display:flex;align-items:center;}
.form-box .form-item:last-child{ border:none}
.form-box .flex-col{padding-bottom:20rpx}
.form-item input{ width: 100%; border: none;color:#111;font-size:28rpx; text-align: right}
.form-item textarea{ width:100%;min-height:200rpx;padding:20rpx 0;border: none;}
.form-item .upload_pic{ margin:50rpx 0;background: #F3F3F3;width:90rpx;height:90rpx; text-align: center  }
.form-item .upload_pic image{ width: 32rpx;height: 32rpx; }
.savebtn{ width: 90%; height:96rpx; line-height: 96rpx; text-align:center;border-radius:48rpx; color: #fff;font-weight:bold;margin: 0 5%; margin-top:60rpx; border: none; }

.layui-imgbox{margin-right:16rpx;margin-bottom:10rpx;font-size:24rpx;position: relative;}
.layui-imgbox-img{display: block;width:200rpx;height:200rpx;padding:2px;border: #d3d3d3 1px solid;background-color: #f6f6f6;overflow:hidden}
.layui-imgbox-img>image{max-width:100%;}
.layui-imgbox-repeat{position: absolute;display: block;width:32rpx;height:32rpx;line-height:28rpx;right: 2px;bottom:2px;color:#999;font-size:30rpx;background:#fff}


.clist-item{display:flex;border-bottom: 1px solid #f5f5f5;padding:20rpx 30rpx;}
.radio{flex-shrink:0;width: 32rpx;height: 32rpx;background: #FFFFFF;border: 2rpx solid #BFBFBF;border-radius: 50%;margin-right:30rpx}
.radio .radio-img{width:100%;height:100%;display:block}

</style>