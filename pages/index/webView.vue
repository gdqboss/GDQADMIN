<template>
<view>
<web-view :src="url"></web-view>
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

      url: ''
    };
  },

  onLoad: function (opt) {
    this.url = decodeURIComponent(opt.url);
  },
	onShareAppMessage:function(){
		let onSharelink = '/pages/index/webView?url=' + encodeURIComponent(this.url);
		return this._sharewx({title:'',pic:'',link:onSharelink});
	},
};
</script>
