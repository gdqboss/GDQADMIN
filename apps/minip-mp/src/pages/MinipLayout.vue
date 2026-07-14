<template>
  <!-- 小程序端布局 - 手机尺寸自适应，无 MainLayout 包裹 -->
  <div class="minip-frame">
    <!-- 顶部 -->
    <header class="minip-topbar">
      <div class="minip-topbar-inner">
        <button v-if="canBack" @click="uni.navigateBack()" class="minip-back">‹</button>
        <h1 class="minip-title">{{ title }}</h1>
        <div class="minip-topbar-right">
          <slot name="topbar-right" />
        </div>
      </div>
    </header>

    <!-- 内容 -->
    <main class="minip-content">
      <slot />
    </main>

    <!-- 底部 Tabbar（可选） -->
    <nav v-if="tabbar && tabbar.length" class="minip-tabbar">
      <router-link
        v-for="t in tabbar"
        :key="t.to"
        :to="t.to"
        class="minip-tab-item"
        :class="{ active: getCurrentPages().slice(-1)[0].route || '' === t.to || getCurrentPages().slice(-1)[0].route || ''.startsWith(t.to + '/') }"
      >
        <span class="minip-tab-icon">{{ t.icon }}</span>
        <span class="minip-tab-label">{{ t.label }}</span>
      </navigator>
    </nav>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: '彩美特小程序' },
  canBack: { type: Boolean, default: false },
  tabbar: { type: Array, default: () => [] }
})
</script>

<style scoped>
.minip-frame {
  min-height: 100vh;
  background: #f5f6f8;
  display: flex;
  flex-direction: column;
  max-width: 480px;
  margin: 0 auto;
  font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
  color: #1f2329;
}
.minip-topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.minip-topbar-inner {
  display: flex;
  align-items: center;
  height: 48px;
  padding: 0 12px;
  gap: 8px;
}
.minip-back {
  background: none;
  border: 0;
  color: #fff;
  font-size: 28px;
  line-height: 1;
  padding: 0 8px;
  cursor: pointer;
}
.minip-title {
  flex: 1;
  font-size: 16px;
  font-weight: 600;
  margin: 0;
  text-align: center;
}
.minip-topbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.minip-content {
  flex: 1;
  padding: 12px;
  padding-bottom: calc(60px + env(safe-area-inset-bottom, 0px));
}
.minip-tabbar {
  position: fixed;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 480px;
  display: flex;
  background: #fff;
  border-top: 1px solid #eee;
  padding-bottom: env(safe-area-inset-bottom, 0px);
  z-index: 50;
}
.minip-tab-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 0;
  font-size: 11px;
  color: #999;
  text-decoration: none;
}
.minip-tab-item.active {
  color: #6366f1;
}
.minip-tab-icon {
  font-size: 20px;
  line-height: 1;
  margin-bottom: 2px;
}
</style>