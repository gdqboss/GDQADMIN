import { createApp, nextTick } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import i18n from './i18n'
import { loadSystemSettings } from './stores/system.js'
import 'material-symbols/outlined.css'
// 2026-08-06 BUG FIX: Material Symbols 默认 font-display: block (字体加载完前图标区域空白, 然后突然显示)
// 手机 4G 用户加载 3.8MB woff2 太慢, 在加载完前看到空白方框 (体验差)
// 改成 font-display: swap - 加载前显示 fallback (虽然空白, 但不会"突然跳出来")
// 注意: fallback 文字 (menu/language/refresh) 不会出现, 因为 icon 字符 (U+EE0A 等) 不是 ASCII
// swap 让 font 加载期间直接保持空白方框而不是延迟渲染
import './styles/material-symbols-font-display.css'

// ElementPlus 按需引入（仅项目实际用到的 51 个组件 + 4 个核心 css）
// 完整列表见 /root/src/i18n/../EP_USAGE.md
import {
  ElAlert, ElAside, ElAutocomplete, ElAvatar, ElBacktop, ElBadge,
  ElBreadcrumb, ElBreadcrumbItem, ElButton, ElCard, ElCarousel, ElCarouselItem,
  ElCascader, ElCheckbox, ElCheckboxGroup, ElCol, ElCollapse, ElCollapseItem,
  ElColorPicker, ElContainer, ElDescriptions, ElDescriptionsItem, ElDialog,
  ElDivider, ElDrawer, ElDropdown, ElDropdownItem, ElDropdownMenu, ElEmpty,
  ElFooter, ElForm, ElFormItem, ElHeader, ElIcon, ElImage, ElInput, ElInputNumber,
  ElLink, ElLoading, ElMain, ElMenu, ElMenuItem, ElMessage, ElMessageBox,
  ElNotification, ElOption, ElPageHeader, ElPagination, ElPopover, ElProgress,
  ElRadio, ElRadioGroup, ElRate, ElResult, ElRow, ElScrollbar, ElSelect,
  ElSkeleton, ElSkeletonItem, ElSlider, ElSpace, ElStatistic, ElStep, ElSteps,
  ElSubMenu, ElSwitch, ElTabPane, ElTable, ElTableColumn, ElTabs, ElTag,
  ElText, ElTextarea, ElTimeline, ElTimelineItem, ElTooltip, ElTransfer,
  ElTree, ElUpload,
} from 'element-plus'

// 按需引入 ElementPlus 基础样式（dark mode vars、reset、基础元素）
// 组件样式由 unplugin-vue-components 自动注入；如未配置，手动引入需要的
import 'element-plus/theme-chalk/dark/css-vars.css'
import 'element-plus/theme-chalk/el-message.css'
import 'element-plus/theme-chalk/el-message-box.css'
import 'element-plus/theme-chalk/el-notification.css'
import 'element-plus/theme-chalk/el-loading.css'
// 表单/容器/导航组件全局样式（基础布局）
import 'element-plus/theme-chalk/el-form.css'
import 'element-plus/theme-chalk/el-form-item.css'
import 'element-plus/theme-chalk/el-input.css'
import 'element-plus/theme-chalk/el-input-number.css'
import 'element-plus/theme-chalk/el-button.css'
import 'element-plus/theme-chalk/el-select.css'
import 'element-plus/theme-chalk/el-option.css'
import 'element-plus/theme-chalk/el-table.css'
import 'element-plus/theme-chalk/el-table-column.css'
import 'element-plus/theme-chalk/el-tag.css'
import 'element-plus/theme-chalk/el-dialog.css'
import 'element-plus/theme-chalk/el-drawer.css'
import 'element-plus/theme-chalk/el-pagination.css'
import 'element-plus/theme-chalk/el-checkbox.css'
import 'element-plus/theme-chalk/el-radio.css'
import 'element-plus/theme-chalk/el-switch.css'
import 'element-plus/theme-chalk/el-date-picker.css'
import 'element-plus/theme-chalk/el-tabs.css'
import 'element-plus/theme-chalk/el-tab-pane.css'
import 'element-plus/theme-chalk/el-menu.css'
import 'element-plus/theme-chalk/el-menu-item.css'
import 'element-plus/theme-chalk/el-sub-menu.css'
import 'element-plus/theme-chalk/el-dropdown.css'
import 'element-plus/theme-chalk/el-dropdown-menu.css'
import 'element-plus/theme-chalk/el-card.css'
import 'element-plus/theme-chalk/el-divider.css'
import 'element-plus/theme-chalk/el-image.css'
import 'element-plus/theme-chalk/el-avatar.css'
import 'element-plus/theme-chalk/el-badge.css'
import 'element-plus/theme-chalk/el-empty.css'
import 'element-plus/theme-chalk/el-alert.css'
import 'element-plus/theme-chalk/el-breadcrumb.css'
import 'element-plus/theme-chalk/el-breadcrumb-item.css'
import 'element-plus/theme-chalk/el-steps.css'
import 'element-plus/theme-chalk/el-step.css'
import 'element-plus/theme-chalk/el-progress.css'
import 'element-plus/theme-chalk/el-slider.css'
import 'element-plus/theme-chalk/el-rate.css'
import 'element-plus/theme-chalk/el-color-picker.css'
import 'element-plus/theme-chalk/el-transfer.css'
import 'element-plus/theme-chalk/el-cascader.css'
import 'element-plus/theme-chalk/el-tree.css'
import 'element-plus/theme-chalk/el-link.css'
import 'element-plus/theme-chalk/el-tooltip.css'
import 'element-plus/theme-chalk/el-popover.css'
import 'element-plus/theme-chalk/el-upload.css'
import 'element-plus/theme-chalk/el-descriptions.css'
import 'element-plus/theme-chalk/el-descriptions-item.css'
import 'element-plus/theme-chalk/el-result.css'
import 'element-plus/theme-chalk/el-skeleton.css'
import 'element-plus/theme-chalk/el-skeleton-item.css'
import 'element-plus/theme-chalk/el-space.css'
import 'element-plus/theme-chalk/el-container.css'
import 'element-plus/theme-chalk/el-aside.css'
import 'element-plus/theme-chalk/el-header.css'
import 'element-plus/theme-chalk/el-main.css'
import 'element-plus/theme-chalk/el-footer.css'
import 'element-plus/theme-chalk/el-row.css'
import 'element-plus/theme-chalk/el-col.css'
import 'element-plus/theme-chalk/el-scrollbar.css'
import 'element-plus/theme-chalk/el-text.css'
import 'element-plus/theme-chalk/el-page-header.css'
import 'element-plus/theme-chalk/el-timeline.css'
import 'element-plus/theme-chalk/el-timeline-item.css'
import 'element-plus/theme-chalk/el-statistic.css'
import 'element-plus/theme-chalk/el-icon.css'
import 'element-plus/theme-chalk/el-radio-group.css'
import 'element-plus/theme-chalk/el-checkbox-group.css'
import 'element-plus/theme-chalk/el-loading.css'

// ElementPlus Icons（图标按需自动注册）
import * as ElementPlusIconsVue from '@element-plus/icons-vue'

import './style.css'

// 创建应用实例
const app = createApp(App)

// 注册所有 ElementPlus 图标（体积小，可全量；按需注册亦可）
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(ElLoading)
// 注册 ElementPlus 服务（message/notification 等）
app.config.globalProperties.$message = ElMessage
app.config.globalProperties.$notify = ElNotification
app.config.globalProperties.$messageBox = ElMessageBox
app.config.globalProperties.$alert = ElMessageBox.alert
app.config.globalProperties.$confirm = ElMessageBox.confirm
app.config.globalProperties.$prompt = ElMessageBox.prompt
// ElementPlus 指令
app.directive('loading', ElLoading.directive)

// 加载系统设置（站点名称 + 默认语言等全局配置）
// 2026-08-06 重大改: 启动时永远用 'zh' 启动, 等公共接口下发 data.locale 后再 setLocale
// 不再用 navigator.language 启发式, 原因:
//   1. navigator.language 是浏览器语言不一定匹配服务器用户群体
//      (例如 SGP 浏览器是 en, 但 SGP profile 默认 zh, 强行切成 en 会乱)
//   2. 启发式会让公共接口 ?locale=en 进来, 后端 data.locale=en, system.js 误判不切换
//   3. 真正的"按 profile 默认"应该由 server_profiles.languages[0] 决定
// 流程: zh 启动 → loadSystemSettings('zh') → 公共接口 data.locale = profile.languages[0] → setLocale(data.locale)
loadSystemSettings('zh')

// 全局 Vue 错误处理：静默处理，不弹 alert
app.config.errorHandler = (err, instance, info) => {
  console.error('Vue error:', err, info)
}

// chunk 加载失败自动刷新（只刷新一次，防循环）
router.onError((error, to) => {
  const isChunkError = /Loading chunk|Failed to fetch|Importing a module|dynamically imported module/i.test(error.message)
  if (isChunkError) {
    const lastReload = sessionStorage.getItem('chunk_reload')
    if (!lastReload || Date.now() - Number(lastReload) > 10000) {
      sessionStorage.setItem('chunk_reload', String(Date.now()))
      window.location.assign(to.fullPath)
      return
    }
  }
  console.error('Router error:', error)
})

// 兜底：捕获未处理的动态 import 失败
window.addEventListener('unhandledrejection', (event) => {
  const msg = event.reason?.message || ''
  if (/Loading chunk|Failed to fetch|Importing a module|dynamically imported module/i.test(msg)) {
    event.preventDefault()
    const lastReload = sessionStorage.getItem('chunk_reload')
    if (!lastReload || Date.now() - Number(lastReload) > 10000) {
      sessionStorage.setItem('chunk_reload', String(Date.now()))
      window.location.reload()
    }
  }
})

// 显示应用并移除加载动画
function showApp() {
  const appEl = document.getElementById('app')
  const loading = document.getElementById('app-loading')

  if (appEl) {
    appEl.classList.add('ready')
  }

  if (loading) {
    loading.classList.add('fade-out')
    setTimeout(() => loading.remove(), 400)
  }
}

// 等路由准备好（所有异步组件加载完成）再挂载，防止 i18n key 暴露
router.isReady().then(() => {
  app.mount('#app')

  // 等待 Vue 完成首次渲染 + i18n 翻译 + CSS 应用
  nextTick(() => {
    // 等待 2 帧确保浏览器完成绘制和 i18n 翻译
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        showApp()
      })
    })
  })
}).catch(() => {
  app.mount('#app')
  showApp()
})

// 兜底：最多 5 秒后强制移除 loading overlay
setTimeout(() => {
  const appEl = document.getElementById('app')
  const loading = document.getElementById('app-loading')

  if (appEl) {
    appEl.classList.add('ready')
  }

  if (loading) {
    loading.classList.add('fade-out')
    setTimeout(() => loading.remove(), 400)
  }
}, 5000)
