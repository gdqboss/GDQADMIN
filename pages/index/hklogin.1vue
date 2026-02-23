<template>
	<view class="container">
		<view class="title">欢迎登录</view>
		<view class="loginform">
			<!-- 手机号登录 -->
			<view class="form-item" v-if="logintype == 1 || logintype == 2">
				<image class="img" src="/static/images/tel.png"></image>
				<input type="number" placeholder="请输入手机号" class="input" v-model="tel" maxlength="11" />
			</view>
			<view class="form-item" v-if="logintype == 1">
				<image class="img" src="/static/img/tel.png"></image>
				<input type="password" placeholder="请输入密码" class="input" v-model="pwd" />
			</view>
			<view class="form-item" v-if="logintype == 2">
				<image class="img" src="/static/img/tel.png"></image>
				<input type="number" placeholder="请输入验证码" class="input" v-model="smsCodeValue" maxlength="6" />
				<text class="code" @tap="sendSmsCode" :class="{hq:hqing==1}">{{smsdjs}}</text>
			</view>
			<!-- 邀请码 -->
			<view class="form-item" v-if="logintype == 1 || logintype == 2">
				<image class="img" src="/static/img/tel.png"></image>
				<input type="text" placeholder="请输入邀请码（选填）" class="input" v-model="yqcode" />
			</view>
		</view>
		<view class="xieyi-item">
			<checkbox-group @change="isagreeChange">
				<checkbox value="agree" :checked="isagree" class="checkbox"></checkbox>
			</checkbox-group>
			<text>我已阅读并同意</text>
			<text class="xieyi" @tap="showxieyiFun">《用户注册协议》</text>
		</view>
		<button class="authlogin-btn" @tap="formSubmit">登录/注册</button>
		<!-- 其他登录方式 -->
		<view class="other_content">
			<view class="other_line"></view>
			<view class="other_text">其他登录方式</view>
			<view class="other_line"></view>
		</view>
		<view class="other_login">
			<image class="other_logo" @tap="weixinlogin" src="/static/images/weixin.png"></image>
		</view>
	</view>
</template>

<script>
const app = getApp();

export default {
	data() {
		return {
			logintype: 1,
			tel: '',
			pwd: '',
			smsCodeValue: '',
			yqcode: '',
			hqing: 0,
			smsdjs: '获取验证码',
			isagree: false,
			showxieyi: false,
			frompage: '/pagesZ/shop/classify1.vue'
		}
	},
	onLoad(options) {
		var that = this;
		// 页面加载逻辑
		that.loadData();
	},
	onShow() {
		// 页面显示时的逻辑
	},
	methods: {
		// 加载页面数据
		loadData: function() {
			var that = this;
			app.showLoading('加载中');
			app.get('ApiIndex/getconfig', {}, function(res) {
				app.showLoading(false);
				// 配置加载逻辑
				that.loaded();
			});
		},
		// 验证码发送
		sendSmsCode: function() {
			var that = this;
			if (that.hqing == 1) return;
			that.hqing = 1;
			var tel = that.tel;
			if (tel == '') {
				app.alert('请输入手机号码');
				that.hqing = 0;
				return false;
			}
			if (!app.isPhone(tel)) {
				app.alert("手机号码有误，请重填");
				that.hqing = 0;
				return false;
			}
			app.post("ApiIndex/sendsms", {tel: tel}, function(data) {
				if (data.status != 1) {
					app.alert(data.msg);
					that.hqing = 0;
					return;
				}
			});
			var time = 120;
			var interval1 = setInterval(function() {
				time--;
				if (time < 0) {
					that.smsdjs = '重新获取';
					that.hqing = 0;
					clearInterval(interval1);
				} else if (time >= 0) {
					that.smsdjs = time + '秒';
				}
			}, 1000);
		},
		// 表单提交
		formSubmit: function(e) {
			var that = this;
			var formdata = e ? e.detail.value : {tel: that.tel, pwd: that.pwd, smscode: that.smsCodeValue};
			if (formdata.tel == '') {
				app.alert('请输入手机号');
				return;
			}
			if (!app.isPhone(formdata.tel)) {
				return app.alert("请输入正确的手机号");
			}
			if (that.logintype == 1) {
				if (formdata.pwd == '') {
					app.alert('请输入密码');
					return;
				}
			}
			if (that.logintype == 2) {
				if (formdata.smscode == '') {
					app.alert('请输入短信验证码');
					return;
				}
			}

			app.showLoading('提交中');
			app.post("ApiIndex/loginsub", {
				tel: formdata.tel,
				pwd: formdata.pwd,
				smscode: formdata.smscode || that.smsCodeValue,
				logintype: that.logintype,
				pid: app.globalData.pid,
				yqcode: that.yqcode || formdata.yqcode,
				regbid: app.globalData.regbid
			}, function(res) {
				app.showLoading(false);
				if (res.status == 1) {
					app.success(res.msg);
					// 登录成功后检查并更新会员身份
					that.updateMemberRole();
				} else {
					app.error(res.msg);
				}
			});
		},
		// 微信登录
		weixinlogin: function() {
			var that = this;
			if (that.xystatus == 1 && !that.isagree) {
				that.showxieyi = true;
				return;
			}
			app.showLoading('授权中');
			app.authlogin(function(res) {
				app.showLoading(false);
				if (res.status == 1) {
					app.success(res.msg);
					// 登录成功后检查并更新会员身份
					that.updateMemberRole();
				} else if (res.status == 3) {
					// 其他处理逻辑...
				} else if (res.status == 2) {
					// 绑定手机等逻辑...
				} else {
					app.error(res.msg);
				}
			}, {frompage: that.frompage, yqcode: that.yqcode});
		},
		// 更新会员身份为团长
		updateMemberRole: function() {
			var that = this;
			app.showLoading('更新身份中');
			// 调用API检查并更新会员等级为团长
			app.post('ApiMy/updateMemberLevel', {}, function(res) {
				app.showLoading(false);
				// 无论更新成功与否，都跳转到目标页面
				setTimeout(function() {
					app.goto('/pagesZ/shop/classify1.vue', 'redirect');
				}, 500);
			});
		},
		// 协议勾选
		isagreeChange: function(e) {
			var val = e.detail.value;
			this.isagree = val.length > 0;
		},
		// 显示协议
		showxieyiFun: function() {
			this.showxieyi = true;
		},
		// 加载完成
		loaded: function() {
			// 加载完成后的处理
		}
	}
}
</script>

<style>
page{background:#ffffff;width: 100%;height:100%;}
.container{width:100%;height:100%;}

.text-center { text-align: center;}
.title{margin:70rpx 50rpx 50rpx 40rpx;height:60rpx;line-height:60rpx;font-size: 48rpx;font-weight: bold;color: #000000;}
.loginform{ width:100%;padding:0 50rpx;border-radius:5px;}
.loginform .form-item{display:flex;align-items:center;width:100%;border-bottom: 1px #ededed solid;height:88rpx;line-height:88rpx;border-bottom:1px solid #F0F3F6;margin-top:20rpx;background: #fff;border-radius: 8rpx;padding: 0 20rpx;}

.loginform .form-item .img{width:44rpx;height:44rpx;margin-right:30rpx}
.loginform .form-item .input{flex:1;color: #000;background: none;}
.loginform .form-item .code{font-size:30rpx}
.xieyi-item{display:flex;align-items:center;margin-top:30rpx}
.xieyi-item{font-size:24rpx;color:#B2B5BE}
.xieyi-item .checkbox{transform: scale(0.6);}

.authlogin-btn{width:580rpx;height:96rpx;line-height:96rpx;background:#51B1F5;border-radius:48rpx;color:#fff;margin-top:50rpx}
.other_content{overflow: hidden;width: 100%;margin-top: 60rpx;text-align: center;display: flex;justify-content:center;}
.other_line{width: 106rpx;height: 2rpx;background: #D8D8D8;margin-top: 20rpx;}
.other_text{margin: 0 20rpx;}
.other_login{width:420rpx;margin: 60rpx auto;display: flex;justify-content: center;}
.other_logo{width: 96rpx;height: 96rpx;margin: 0 20rpx;}
</style>