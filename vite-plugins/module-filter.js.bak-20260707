/**
 * module-filter.js
 *
 * Vite 插件：按 enabledModules 过滤 dist 产物。
 *
 * 解决问题：
 *   Vite 默认会把 entry chunk 的 __vite__mapDeps 静态 import 全部子 chunks。
 *   即使目标服务器只启用 25/47 模块，entry chunk 启动时仍 import 全部，
 *   缺失任何一个 chunk 都会导致整个 app 启动失败（#app 为空）。
 *
 * 机制：
 *   1. generateBundle 阶段：扫 entry chunk，解析 __vite__mapDeps 数组，
 *      根据 enabledModules 过滤出子集，重写数组。
 *   2. closeBundle 阶段：扫 dist/assets/，删除未被引用的孤立 chunks。
 *
 * @param {string[]|null} enabledModules 启用的模块 key 列表；null = 不过滤
 */
import fs from 'fs'
import path from 'path'

const MARKER1 = '__vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=['
const MARKER2 = '])))'

export default function moduleFilterPlugin(enabledModules) {
  if (!enabledModules || !Array.isArray(enabledModules) || enabledModules.length === 0) {
    return { name: 'vite-plugin-module-filter-disabled' }
  }

  const enabledSet = new Set(enabledModules)
  // 必须保留的核心 chunks（不属于任何业务模块）
  const ALWAYS_KEEP = new Set([
    'i18n', 'index', 'wecom', 'MainLayout', 'Login', 'Dashboard',
    'StatCard', 'StatusTag', 'Pagination', 'PageHeader',
    'mockData', 'favicon'
  ])

  return {
    name: 'vite-plugin-module-filter',
    apply: 'build',

    // 调试: 确认插件被加载
    configResolved(config) {
      console.log('[module-filter] configResolved: enabledModules=' + enabledModules.length)
    },

    generateBundle(_options, bundle) {
      // 实际 marker 是在 writeBundle 之后由 Vite 注入
      // 我们在 closeBundle 里直接读 dist 文件
    },

    /**
     * renderChunk: 每次 chunk 生成时调用 (after code-split, before writing)
     * mapDeps 在 renderDynamicImport 时插入, 我们需要在 chunk.code 修改
     */
    renderChunk(code, chunk) {
      const startIdx = code.indexOf(MARKER1)
      if (startIdx === -1) return null

      const arrayStart = startIdx + MARKER1.length
      const arrayEnd = code.indexOf(MARKER2, arrayStart)
      if (arrayEnd === -1) return null

      const originalStr = code.substring(arrayStart, arrayEnd)

      const originalDeps = []
      const refRegex = new RegExp('"([^"]+)"', 'g')
      let m
      while ((m = refRegex.exec(originalStr)) !== null) {
        originalDeps.push(m[1])
      }

      const keptDeps = originalDeps.filter(dep => {
        const moduleMatch = dep.match(/^assets\/([A-Za-z0-9_-]+?)(?:-[A-Za-z0-9_-]{8,})?\.(js|css)$/)
        if (!moduleMatch) return true
        const moduleName = moduleMatch[1]
        return ALWAYS_KEEP.has(moduleName) || enabledSet.has(moduleName)
      })

      const removed = originalDeps.length - keptDeps.length
      if (removed > 0) {
        console.log('[module-filter] ' + chunk.fileName + ': 保留 ' + keptDeps.length + ' deps, 过滤 ' + removed)
      }

      const newDepsStr = keptDeps.map(d => '"' + d + '"').join(',')
      const newCode = code.substring(0, arrayStart) + newDepsStr + code.substring(arrayEnd)
      return newCode
    },

    closeBundle() {
      const distDir = path.resolve(process.cwd(), 'dist')
      const indexHtmlPath = path.join(distDir, 'index.html')
      if (!fs.existsSync(indexHtmlPath)) return

      const indexHtml = fs.readFileSync(indexHtmlPath, 'utf-8')
      const referenced = new Set()

      // 1. 从 index.html 提取所有引用
      const refRegex = new RegExp('["\'](/?assets/[^"\']+)["\']', 'g')
      let m
      while ((m = refRegex.exec(indexHtml)) !== null) {
        referenced.add(path.basename(m[1].replace(/^\//, '')))
      }

      // 2. 找到 entry chunk，递归提取它 import 的所有 chunks
      const entryMatch = indexHtml.match(/<script[^>]+src="\/assets\/(index-[A-Za-z0-9_-]+\.js)"/)
      if (entryMatch) {
        const visited = new Set()
        const queue = [entryMatch[1]]
        while (queue.length > 0) {
          const f = queue.shift()
          if (visited.has(f)) continue
          visited.add(f)
          referenced.add(f)
          const fp = path.join(distDir, 'assets', f)
          if (!fs.existsSync(fp)) continue
          const code = fs.readFileSync(fp, 'utf-8')
          const importRegex = new RegExp('from\\s*["\']\\./([A-Za-z0-9_.-]+)["\']', 'g')
          let im
          while ((im = importRegex.exec(code)) !== null) {
            queue.push(im[1])
          }
          const dynImportRegex = new RegExp('import\\s*\\(\\s*["\']\\./([A-Za-z0-9_.-]+)["\']', 'g')
          while ((im = dynImportRegex.exec(code)) !== null) {
            queue.push(im[1])
          }
        }
      }

      // 3. 删除孤立文件
      const assetsDir = path.join(distDir, 'assets')
      if (!fs.existsSync(assetsDir)) return
      const allFiles = fs.readdirSync(assetsDir)
      let removedFiles = 0
      let removedBytes = 0
      for (const f of allFiles) {
        if (referenced.has(f)) continue
        const fp = path.join(assetsDir, f)
        const stat = fs.statSync(fp)
        if (stat.isFile()) {
          fs.unlinkSync(fp)
          removedFiles++
          removedBytes += stat.size
        }
      }
      if (removedFiles > 0) {
        console.log('[module-filter] 删除孤立文件: ' + removedFiles + ' 个, ' + (removedBytes/1024).toFixed(1) + ' KB')
        console.log('[module-filter] 保留文件: ' + referenced.size + ' 个')
      }
    }
  }
}
