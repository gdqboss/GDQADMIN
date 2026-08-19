// ui-icon-element.js — 2026-08-15 江小鱼革命性强化
// 把 Dashboard.vue 等 100 个文件里的 <span class="material-symbols-outlined">xxx</span>
// 自动改写成 <ui-icon name="xxx">, 不动 view 任何一行, 渲染走内联 SVG (无 woff2).
//
// 用法 (main.js 末尾):
//   import './ui-icon-element.js'  // 自动注册 custom element + 启动 DOM 监听
//
// 工作原理:
//   1. customElements.define('ui-icon', class extends HTMLElement { ... })
//      connectedCallback() 时读 .name 属性 → 同 UiIcon.vue 的 PATHS → 注入 <svg>
//   2. 注意: shadow DOM 隔离样式 → 不能 inherit text-{color} 颜色 → 必须用 light DOM
//      (attachShadow 改成 this.appendChild) 才能继承外层 class
//   3. 启动后 scanDocument() 全文档扫一遍, 后续用 MutationObserver 监控动态插入
//   4. setTimeout 重试兜底 SPA route 切换后的 DOM 刷新 (vue-router 切页会重新渲染整个 view)
//
// 安全:
//   - 找不到 name → 渲染空 svg (静音, 不显示 icon 名字)
//   - 不是 <span> 或 class 不匹配 → 跳过
//   - 已经被改写过的元素有 .dataset.uiIconMigrated = '1' → 跳过
//   - Vue 重渲染整个 v-if 区: Vue 会复用 DOM 节点, 数据不变,
//
// 边界情况:
//   <span class="material-symbols-outlined text-lg">refresh</span>
//   需要保留 outerHTML 里的所有 class + style, 提取 innerText 当 name.

const SVG_PATHS = {
  schedule: 'M480-120q-138 0-240.5-91.5T122-440h60q14 104 92.5 172T480-180q104 0 176-72.5T728-440H600v-60h188q12 24 12 60t-12 60q-14 134-115.5 227T480-120Z',
  description: 'M300-80q-33 0-56.5-23.5T220-160v-640q0-33 23.5-56.5T300-880h360q33 0 56.5 23.5T740-800v640q0 33-23.5 56.5T660-80H300Zm0-80h360v-640H300v640Z',
  task_alt: 'M480-120q-138 0-240.5-91.5T122-440h60q14 104 92.5 172T480-180q104 0 176-72.5T728-440H576q-12 0-21-9t-9-21q0-12 9-21t21-9h220q12 0 21 9t9 21q0 137-98 233.5T480-120Z',
  qr_code_scanner: 'M440-120v-80h80v80h-80Zm160 0v-80h80q17 0 28.5-11.5T720-240h80q0 50-35 85t-85 35h-80ZM120-240q-50 0-85-35T0-360h80q0 17 11.5 28.5T120-320v80Z',
  qr_code_2: 'M120-160v-200h80v120h120v80H120Zm520 0v-80h120v-120h80v200H640ZM120-720v-200h200v80H200v120h-80Z',
  qr_code: 'M120-160v-200h80v120h120v80H120Zm520 0v-80h120v-120h80v200H640ZM120-720v-200h200v80H200v120h-80Zm640 0v-120H640v-80h200v200h-80Z',
  assignment: 'M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q39 0 70.5 22.5T593-840h167q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Z',
  receipt_long: 'M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q39 0 70.5 22.5T593-840h167q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H640v-80h120v-560h-80v120H280v-120h-80v560h120v80H200Z',
  person: 'M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Z',
  bolt: 'M480-80q-33 0-56.5-23.5T400-160q0-22 12-39.5t32-25.5l184-72-174-72q-20-8-32-25.5T400-440q0-33 23.5-56.5T480-520q22 0 39.5 12t25.5 32l72 184 72-174q8-20 25.5-32t39.5-12q33 0 56.5 23.5T840-440q0 22-12 39.5t-32 25.5l-184 72 174 72q20 8 32 25.5t12 39.5q0 33-23.5 56.5T760-80q-22 0-39.5-12T695-124l-72-184-72 174q-8 20-25.5 32T480-80Z',
  refresh: 'M480-160q-134 0-227-93t-93-227q0-134 93-227t227-93q134 0 227 93t93 227q0 18-2 35t-6 33l-60-26q2-10 4-20.5t2-21.5q0-100-70-170t-170-70q-100 0-170 70t-70 170q0 100 70 170t170 70Z',
  inventory_2: 'M440-200h80v-167l64 64 56-57-160-160-160 160 56 57 64-64v167ZM200-80q-33 0-56.5-23.5T120-160v-640q0-33 23.5-56.5T200-880h560q33 0 56.5 23.5T840-800v640q0 33-23.5 56.5T760-80H200Z',
  outbox: 'M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h207v80H160v480h640v-480h-207v-80h207q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Z',
  pending_actions: 'M360-80q-83 0-141.5-58.5T160-280q0-83 58.5-141.5T360-480q83 0 141.5 58.5T560-280q0 83-58.5 141.5T360-80Z',
  warning: 'M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Z',
  shopping_cart: 'M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80Z',
  verified_user: 'M480-80q-141 0-240.5-99.5T140-420q0-141 99.5-240.5T480-760q141 0 240.5 99.5T820-420q0 141-99.5 240.5T480-80Z',
  analytics: 'M200-160v-160h120v160H200Zm240 0v-400h120v400H440Zm240 0v-280h120v280H680Z',
  keyboard: 'M160-240q-33 0-56.5-23.5T80-320v-320q0-33 23.5-56.5T160-720h640q33 0 56.5 23.5T880-640v320q0 33-23.5 56.5T800-240H160Z',
  close: 'M480-418 300-238l-62-62 180-180-180-180 62-62 180 180 180-180 62 62-180 180 180 180-62 62-180-180Z',
  task: 'M480-80q-83 0-141.5-58.5T280-280q0-83 58.5-141.5T480-480q83 0 141.5 58.5T680-280q0 83-58.5 141.5T480-80Z',
  menu: 'M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z',
  search: 'M784-120 532-372q-30 27-69.5 41T390-318q-75 0-127.5-52.5T210-498q0-75 52.5-127.5T390-678q75 0 127.5 52.5T570-498q0 38-14 77.5T515-351l252 252-56 56ZM390-398q42 0 71-29t29-71q0-42-29-71t-71-29q-42 0-71 29t-29 71q0 42 29 71t71 29Z',
  language: 'M480-80q-82 0-155-31.5t-127.5-86Q143-252 111.5-325T80-480q0-83 31.5-156t86-127Q252-817 325-848.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 82-31.5 155T763-197.5q-54 54.5-127 86T480-80Z',
  add: 'M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z',
  edit: 'M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-528q12-12 27-18t30-6q16 0 30.5 6t26.5 18l55 56q12 12 18 26.5t6 30.5q0 15-6 30t-18 27L246-120H120Z',
  delete: 'M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Z',
  check: 'M382-240 192-430l51-51 139 139 295-295 51 51-346 346Z',
  arrow_back: 'M400-80 0-480l400-400 71 71-329 329 329 329-71 71Z',
  arrow_forward: 'm560-240-71-71 329-329-329-329 71-71 400 400-400 400Z',
  visibility: 'M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Z',
  download: 'M480-313 287-506l51-51 102 102v-282h72v282l102-102 51 51-193 193ZM200-160q-33 0-56.5-23.5T120-240v-120h80v120h560v-120h80v120q0 33-23.5 56.5T760-160H200Z',
  upload: 'M440-160v-282L338-340l-51-51 193-193 193 193-51 51-102-102v282h-80ZM200-160q-33 0-56.5-23.5T120-240v-120h80v120h560v-120h80v120q0 33-23.5 56.5T760-160H200Z',
  home: 'M240-200h120v-240h240v240h120v-360L480-756 240-560v360Zm-80 80v-480l320-240 320 240v480H520v-240h-80v240H160Zm320-400Z',
  settings: 'M480-160q-34 0-57-23t-23-57q0-34 23-57t57-23q34 0 57 23t23 57q0 34-23 57t-57 23ZM280-360q-34 0-57-23t-23-57q0-34 23-57t57-23q34 0 57 23t23 57q0 34-23 57t-57 23Z',
  notifications: 'M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q14 4 27.5 8t24.5 12q50 24 86 70t43 102h-82q-7-38-39-64t-72-26q-50 0-85 35t-35 85v280h440v-220h80v300H160Z',
  star: 'M480-120l-58-52q-100-90-167-146t-114-103q-50-50-77-104t-27-122q0-78 53-131t133-53q47 0 90 18t83 50q40-32 83-50t90-18q80 0 133 53t53 131q0 68-27 122t-77 104q-47 47-114 103T538-172L480-120Z',
  favorite: 'M480-80q-13 0-25-4t-21-11q-94-70-156-141T196-383q-20-46-26-93t-6-94q0-91 64.5-155.5T480-788q91 0 155.5 64.5T700-568q0 47-6 94t-26 93q-42 91-104 162T436-95q-9 7-21 11t-25 4Z',
  logout: 'M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h280v80H200v560h280v80H200Zm440-160-51-51 121-121H520v-80h190l-121-121 51-51 211 211-211 211Z',
  login: 'M480-120v-80h280v-560H480v-80h280q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H480ZM318-457l-51 51 211 211 211-211-211-211-51 51 119 119H120v80h297L318-457Z',
  inventory: 'M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h207v80H160v480h640v-480h-207v-80h207q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Z',
  filter_list: 'M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z',
  calendar_today: 'M200-80q-33 0-56.5-23.5T120-160v-640q0-33 23.5-56.5T200-880h560q33 0 56.5 23.5T840-800v640q0 33-23.5 56.5T760-80H200Zm0-80h560v-400H200v400Z',
  payment: 'M560-200v-560h80v560h-80Zm-200 0q-33 0-56.5-23.5T280-280q0-33 23.5-56.5T360-360q33 0 56.5 23.5T440-280q0 33-23.5 56.5T360-200Z',
  money: 'M480-120q-138 0-240.5-91.5T122-440h60q14 104 92.5 172T480-180q104 0 176-72.5T728-440H576q-12 0-21-9t-9-21q0-12 9-21t21-9h220q12 0 21 9t9 21q0 137-98 233.5T480-120Z',
  trending_up: 'M160-160v-80h120v-180L120-580l40-66 120 100 160-160 120 100 200-220v226h-80v-94l-120 132-120-100-160 160-80-66v338h-40Z',
  trending_down: 'M160-200l160-160 160 160h-120v200h-80v-200h-120Zm540-560v200h120l-160 160-160-160h120v-200h80Z',
  more_horiz: 'M240-400q-33 0-56.5-23.5T160-480q0-33 23.5-56.5T240-560q33 0 56.5 23.5T320-480q0 33-23.5 56.5T240-400Zm240 0q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400Zm240 0q-33 0-56.5-23.5T640-480q0-33 23.5-56.5T720-560q33 0 56.5 23.5T800-480q0 33-23.5 56.5T720-400Z',
  chevron_right: 'M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z',
  chevron_left: 'M664-480 424-240l-56-56 184-184-184-184 56-56 240 240Z',
  arrow_drop_down: 'M480-340 320-500h320L480-340Z',
  arrow_drop_up: 'M320-460h320L480-620 320-460Z',
  info: 'M440-280h80v-240h-80v240Zm40 320q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-40Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Z',
  help: 'M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-27 5.5-39t27.5-29q34-25 50-46.5t16-52.5q0-39-30-65t-77-26q-47 0-77 22.5T420-580l74 12q4-23 19.5-36.5T552-618q22 0 36.5 12t14.5 32q0 18-11 29t-33 26q-32 22-44.5 41T504-394h-62Z',
  expand_more: 'M480-340 320-500h320L480-340Z',
  expand_less: 'M320-460h320L480-620 320-460Z',
  edit_note: 'M200-200v-80h80v80h-80Zm0-160v-80h80v80h-80Zm0-160v-80h80v80h-80Zm160 320v-80h280v80H360Zm0-160v-80h440v80H360Zm0-160v-80h440v80H360Z',
  fact_check: 'M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q39 0 70.5 22.5T593-840h167q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560h-80v120H280v-120h-80v560Z',
  print: 'M640-200v80H320v-80h-80v-280q0-33 23.5-56.5T320-560h320q33 0 56.5 23.5T720-480v280h-80ZM240-600q-17 0-28.5-11.5T200-640v-200h160v-40h240v40h160v200q0 17-11.5 28.5T720-600H240Z',
  share: 'M680-80 520-240l40-40 100 100v-440H200v240h-80v-320q0-33 23.5-56.5T200-780h168q-4 30 4 60t24 50l-100 60v50h480v440h-96Z',
  content_copy: 'M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h167q11-35 43-57.5t70-22.5q39 0 70.5 22.5T593-840h167q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560h-80v120H280v-120h-80v560Z',
  groups: 'M40-160v-112q0-34 17.5-62.5T104-378q62-31 126-46.5T360-440q66 0 130 15.5T616-378q29 15 46.5 43.5T680-272v112H40Z',
  person_add: 'M680-160v-120H560v-80h120v-120h80v120h120v80H760v120h-80ZM360-440q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM40-160v-112q0-34 17.5-62.5T104-378q62-31 126-46.5T360-440q26 0 51 2.5t49 7.5q-23 17-41 39t-32 47q-13-2-26-2.5t-25-.5q-56 0-111 13.5T140-306q-9 5-14.5 14t-5.5 20v32h280v80H40Z',
  dashboard: 'M520-200v-200h200v200H520Zm0-280v-200h200v200H520ZM240-200v-200h200v200H240Zm0-280v-200h200v200H240Z',
  campaign: 'M760-480q0-118-83-201t-201-83q-118 0-201 83t-83 201q0 118 83 201t201 83q118 0 201-83t83-201ZM820-80q-11 0-21-5t-17-15L652-242q-37 32-83 49t-91 17q-127 0-217-90t-90-217q0-127 90-217t217-90q127 0 217 90t90 217q0 47-17.5 92T738-313l142 142q10 10 15 20t5 21q0 25-17.5 42.5T840-80q-11 0-20-5t-15-15Z',
  timer: 'M360-840v-80h240v80H360Zm80 440h80v-240h-80v240Zm40 320q-74 0-139.5-28.5T226-186q-49-49-77.5-114.5T120-440q0-74 28.5-139.5T226-694q49-49 114.5-77.5T480-800q74 0 139.5 28.5T734-694q49 49 77.5 114.5T840-440q0 74-28.5 139.5T734-186q-49 49-114.5 77.5T480-80Z',
}

class UiIconElement extends HTMLElement {
  static get observedAttributes() { return ['name', 'size', 'spin'] }

  constructor() {
    super()
    this._svg = null
  }

  connectedCallback() {
    // light DOM 模式才能继承外层 class (text-{color} 等), 不能用 shadow DOM
    // 第一次连入时填充
    if (!this._svg) {
      this._render()
    }
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue) this._render()
  }

  _render() {
    const name = this.getAttribute('name') || ''
    const size = this.getAttribute('size') || '24'
    const path = SVG_PATHS[name]
    const spin = this.hasAttribute('spin')

    this.style.display = 'inline-flex'
    this.style.verticalAlign = 'middle'
    this.style.alignItems = 'center'
    this.style.justifyContent = 'center'

    if (path) {
      // 取外层 class (Migrator 已把所有 span 的 class 复制到 ui-icon 上)
      // 直接 innerHTML 即可, 父级 class 已迁移, currentColor 自动跟
      this.innerHTML =
        `<svg width="${size}" height="${size}" viewBox="0 -960 960 960" fill="currentColor" ` +
        `xmlns="http://www.w3.org/2000/svg" aria-hidden="true" ` +
        `style="${spin ? 'animation: ui-icon-spin 1s linear infinite;' : ''}display:block;">` +
        `<path d="${path}"/>` +
        `</svg>`
    } else {
      this.textContent = ''
      this.style.color = '#94a3b8'
      this.style.fontSize = '0.75em'
    }
  }
}

// CSS for spin (注入到 document.head 一次)
function injectSpinCSS() {
  if (document.getElementById('ui-icon-spin-style')) return
  const style = document.createElement('style')
  style.id = 'ui-icon-spin-style'
  style.textContent = `@keyframes ui-icon-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`
  document.head.appendChild(style)
}

// DOM Migrator — 把 <span class="material-symbols-outlined ...">xxx</span>
// 改为 <ui-icon name="xxx" class="...">
function migrateNode(root) {
  if (!root || !root.querySelectorAll) return
  // 不选自己 (避免 <ui-icon> 内 svg 被误识别)
  const spans = root.querySelectorAll('span.material-symbols-outlined')
  for (const span of spans) {
    if (span.dataset.uiIconMigrated === '1') continue
    const name = (span.textContent || '').trim()
    if (!name) continue
    if (!SVG_PATHS[name]) {
      // 字体方案有的图标但我们没有 path → 不动, 让字体方案兜底
      span.dataset.uiIconMigrated = '1'
      continue
    }
    const uiIcon = document.createElement('ui-icon')
    uiIcon.setAttribute('name', name)
    uiIcon.setAttribute('aria-hidden', 'true')
    // 把所有 class 迁移到 ui-icon 上, 保证 text-{color} 等样式生效
    uiIcon.className = span.className
    // 把 span 上的 style 合并过来
    if (span.style && span.style.cssText) {
      uiIcon.style.cssText = span.style.cssText
    }
    // inline size: .text-lg / .text-xl / .text-2xl / .text-3xl 已经是 class 继承
    // font-size inline: e.g. style="font-size: 18px" → 提到 width/height 上
    const fontSize = (span.style && span.style.fontSize) || ''
    const m = fontSize.match(/^(\d+)/)
    if (m) {
      uiIcon.setAttribute('size', m[1])
    }
    span.replaceWith(uiIcon)
  }
}

// 全文档扫描 + 监听新增节点
function startMigration() {
  injectSpinCSS()
  migrateNode(document.body)
}

// 等 DOM ready 再初始化
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startMigration, { once: true })
} else {
  startMigration()
}

// MutationObserver: 兜底 SPA 路由切换后 Vue 重新渲染 view
let observer = null
function startObserver() {
  if (observer) return
  observer = new MutationObserver((mutations) => {
    let needsMigrate = false
    for (const m of mutations) {
      if (m.addedNodes.length) {
        for (const node of m.addedNodes) {
          if (node.nodeType === 1 && node.querySelectorAll) {
            // 检查新增节点本身或其后代是否含 material-symbols-outlined
            if (
              node.matches && node.matches('span.material-symbols-outlined') ||
              node.querySelector && node.querySelector('span.material-symbols-outlined')
            ) {
              needsMigrate = true
              break
            }
          }
        }
      }
      if (needsMigrate) break
    }
    if (needsMigrate) {
      // debounce 微任务
      requestAnimationFrame(() => migrateNode(document.body))
    }
  })
  observer.observe(document.body, { childList: true, subtree: true })
}

// 等首次 scan 完成后再开 observer, 避免双重处理
setTimeout(startObserver, 100)

// 注册 custom element — 必须在使用前
if (typeof customElements !== 'undefined') {
  customElements.define('ui-icon', UiIconElement)
}

export default UiIconElement
