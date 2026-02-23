<template>
	<view class="container"></view>
</template>

<script>
var app = getApp();
export default {
  data() {
    return {

    }
  },
  onLoad: function (opt) {
		var o = app.getopts(opt)||{};
		var url = '/pages/my/usercenter'; // 正确的我的页面路径
		var qs = [];
		for(var k in o){
			if(o.hasOwnProperty(k)){
				var v = o[k];
				if(v!==undefined && v!==null && v!==''){
					qs.push(encodeURIComponent(k)+'='+encodeURIComponent(v));
				}
			}
		}
		if(qs.length>0){ url += ('?' + qs.join('&')); }
		app.goto(url,'redirectTo');
  }
}
</script>
