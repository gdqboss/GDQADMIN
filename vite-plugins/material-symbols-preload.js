// 2026-08-06 BUG FIX: 自动注入 Material Symbols Outlined woff2 preload link
// 解决手机 4G 用户 FOIT (字体加载前空白 3-5 秒) 问题
// 用法: import materialSymbolsPreload from './material-symbols-preload.js'
//       plugins: [vue(), moduleFilterPlugin(enabledModules), materialSymbolsPreload()]
//
// 工作原理:
//   1. transformIndexHtml 在 html 转 string 时, 用 htmlTagDescriptor 注入 link 标签
//   2. vite 会原样保留注入的 link (因为 descriptor 模式它不再做 href resolve)
//   3. build 完成后用 sed 替换 MATERIALSYMBOLSWOFF2 占位符为真实 hash

export default function materialSymbolsPreload() {
  return {
    name: 'material-symbols-preload',
    transformIndexHtml() {
      console.log('[material-symbols-preload] injecting link tag')
      return [
        {
          tag: 'link',
          attrs: {
            rel: 'preload',
            as: 'font',
            type: 'font/woff2',
            href: 'MATERIALSYMBOLSWOFF2',
            crossorigin: ''
          },
          injectTo: 'head'
        }
      ]
    }
  }
}