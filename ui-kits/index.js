/**
 * ui-kits/index.js — UI kit 注册表 (0.6 铁律 P2)
 *
 * 当前实现：element-plus 一套
 * 后续可加：naive-ui / ant-design / custom
 *
 * 切换方式：
 *   server_profiles.ui_kit = 'naive-ui'  → vite alias + 这个注册表
 *
 * 禁止在 view 写 <el-button> 等 EP 内部组件名 — 必须走 UiButton 等抽象层
 */

export const UI_KITS = {
  'element-plus': {
    name: 'ElementPlus',
    // 当 ui-kit = element-plus 时使用 EP 组件
    button: () => import('element-plus').then(m => m.ElButton),
    table: () => import('element-plus').then(m => m.ElTable),
    input: () => import('element-plus').then(m => m.ElInput),
    form: () => import('element-plus').then(m => m.ElForm),
    dialog: () => import('element-plus').then(m => m.ElDialog),
  },
  // 占位 (P2 才会实装)
  'naive-ui': {
    name: 'NaiveUI',
    placeholder: true,
  },
  'ant-design': {
    name: 'AntDesign',
    placeholder: true,
  },
}

export function getUiKit(name = 'element-plus') {
  return UI_KITS[name] || UI_KITS['element-plus']
}

export function listAvailableUiKits() {
  return Object.keys(UI_KITS).map(k => ({ key: k, ...UI_KITS[k] }))
}
