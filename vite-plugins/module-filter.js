// 彩美特前端多服务器构建 - 模块过滤插件 (Vite 7 重写版)
//
// 历史 bug (Vite 6 时代版本):
//   - MARKER1/MARKER2 匹配 Vite 6 时代的 `__vite__mapDeps(...=>{...})` 嵌入数组
//   - Vite 7 把 __vite__mapDeps 重写为 `const __vite__mapDeps=...=>i.map(i=>d[i])`,
//     整型 index 数组 + 集中定义在 entry, 整个 filter 逻辑不再有效
//
// 新设计 (Vite 7):
//   1. 改 hook 从 renderChunk (依赖已内联 marker) 到 closeBundle (后处理已写文件)
//   2. 从 dist/assets/index-*.js 中找出所有 `import("./X-HASH.js")` 调用
//   3. 用 profile-config.js 的 MODULE_FILE_MAP 把 module_key (server_profiles)
//      映射到 chunk name 前缀 (ServerProfiles), 检查是否在禁用模块
//   4. 替换被禁 chunk 的 import 字符串为 Promise.resolve(stub), 让 webpack/vite
//      死代码消除 + webpack chunk graph 不引用
//   5. 删除被禁 chunk 文件本身
//
// 风险:
//   - 用户访问被禁路由会看到 "模块未启用" stub (非白屏, 是可读错误)
//   - 不会崩 SPA shell

import fs from 'fs'
import path from 'path'

// Vite 7 解析后, dist 里会出现:
//   - 动态 import: import("./ServerProfiles-X.js")
//   - prefetch mapDeps 字符串数组: ["assets/X.js","assets/X.css"]
// 两种都需要过滤
const IMPORT_RE = /import\(\s*["']\.\/(.+?)["']\s*\)/g
const MAPDEPS_RE = /"\s*(assets\/[A-Za-z0-9_-]+?-[A-Za-z0-9_-]{6,}\.(?:js|css))"/g

// Stub - 被过滤的 dynamic import 替换成什么
const STUB = 'Promise.resolve({default:{name:"DisabledModule",template:(()=>{const e=document.createElement("div");e.innerHTML="<div style=\\"padding:40px;text-align:center;color:#999\\">🚫 该模块未启用</div>";return e.outerHTML})()}})'

// module_key → chunk-base name 前缀 (从 MODULE_FILE_MAP 推导)
// MODULE_FILE_MAP key 是 module_key, value 是 view 路径前缀 (e.g. 'aftersale/', 'AiClassroom.vue')
// 我们从 view 文件名去掉路径+后缀, 得到 chunk base prefix:
//   'aftersale/'                  → 'AftersaleManage' (扫 views/aftersale/ 里的 .vue 文件)
//   'AiClassroom.vue'             → 'AiClassroom'
//   'inventory/'                  → 'InOutList', 'ReturnList', 'StockManage', 'Stocktake'
//   'settings/ServerProfiles.vue' → 'ServerProfiles'
//
// 这个比 MODULE_ROUTE_MAP 准确 (后者手写易错)
function deriveChunkPrefixes(moduleKey, fileMapEntries) {
  // 单 build 模式 cwd = /root/src, views 在 cwd/views
  // profile 模式 cwd = /root/src/modules/<id>, views 在 modules/<id>/views (被 build-for-profile.sh cp 过来)
  let viewDir = path.resolve(process.cwd(), 'views')
  if (!fs.existsSync(viewDir)) {
    viewDir = path.resolve(process.cwd(), '../views')
  }
  if (!fs.existsSync(viewDir)) {
    viewDir = path.resolve(process.cwd(), '../../views')
  }
  const prefixes = new Set()
  for (const filePath of fileMapEntries) {
    if (filePath.endsWith('/')) {
      // 目录 - 扫所有 .vue 文件
      const dir = path.join(viewDir, filePath)
      if (fs.existsSync(dir)) {
        fs.readdirSync(dir).forEach(f => {
          if (f.endsWith('.vue')) {
            const base = f.replace(/\.vue$/, '')
            // chunks 是 base name: e.g. 'AftersaleManage.vue' → 'AftersaleManage'
            prefixes.add(base)
          }
        })
      } else {
        console.log('[module-filter] WARN view dir not found: ' + dir)
      }
    } else if (filePath.endsWith('.vue')) {
      // 单文件
      const base = path.basename(filePath, '.vue')
      prefixes.add(base)
    }
  }
  return [...prefixes]
}

export default function moduleFilterPlugin(enabledModuleKeys, options = {}) {
  const dryRun = !!options.dryRun
  const distDirOverride = options.distDir || null  // profile build 用 outDir=dist-<id>

  if (!enabledModuleKeys || !Array.isArray(enabledModuleKeys) || enabledModuleKeys.length === 0) {
    return {
      name: 'module-filter',
      apply: 'build',
      configResolved() {
        console.log('[module-filter] disabled (no enabledModules)')
      }
    }
  }

  const enabledSet = new Set(enabledModuleKeys)
  let chunkPrefixMap = null
  let assetsDir = null

  return {
    name: 'module-filter',
    apply: 'build',
    configResolved(config) {
      console.log('[module-filter] configResolved: enabledModules=' + enabledModuleKeys.length + ' = [' + enabledModuleKeys.join(',') + ']')
    },

    closeBundle() {
      const distDir = distDirOverride
        ? path.resolve(process.cwd(), distDirOverride)
        : path.resolve(process.cwd(), 'dist')
      const entryHtmlPath = path.join(distDir, 'index.html')
      if (!fs.existsSync(entryHtmlPath)) {
        console.log('[module-filter] no index.html, skip')
        return
      }

      // 1. 同步加载 profile-config.js + module-key → chunk-name 前缀映射
      let moduleFileMap, moduleRouteMap
      try {
        // plugin cwd 是 modules/<id>/ (profile 模式), profile-config 在 ../modules/profile-config.js
        // 单 build 模式 cwd 是 src 根, profile-config 直接 modules/profile-config.js
        let profileCfg = path.resolve(process.cwd(), 'modules/profile-config.js')
        if (!fs.existsSync(profileCfg)) {
          profileCfg = path.resolve(process.cwd(), '../modules/profile-config.js')
        }
        if (!fs.existsSync(profileCfg)) {
          profileCfg = path.resolve(process.cwd(), '../../modules/profile-config.js')
        }
        // 用 readFile + 手动 eval (modules/profile-config.js 是 ESM 但只 export const,
        // 我们用 regex 把它改写成 CJS module.exports.X = {…})
        const cfgSrc = fs.readFileSync(profileCfg, 'utf8')
        const cjsSrc = cfgSrc.replace(
          /export\s+const\s+([A-Za-z0-9_]+)\s*=/g,
          'module.exports.$1 ='
        )
        const fakeModule = { exports: {} }
        const fn = new Function('module', 'exports', 'path', cjsSrc)
        fn(fakeModule, fakeModule.exports, path)
        moduleFileMap = fakeModule.exports.MODULE_FILE_MAP
        moduleRouteMap = fakeModule.exports.MODULE_ROUTE_MAP
        if (!moduleFileMap) throw new Error('MODULE_FILE_MAP missing after eval')
      } catch (e) {
        console.log('[module-filter] WARN cannot load profile-config: ' + e.message + ', filtering disabled')
        return
      }

      // 2. 计算禁用 modules (all keys - enabled = disabled)
      const allKeys = new Set(Object.keys(moduleFileMap))
      const disabledKeys = new Set()
      allKeys.forEach(k => { if (!enabledSet.has(k)) disabledKeys.add(k) })

      if (disabledKeys.size === 0) {
        console.log('[module-filter] nothing to filter')
        return
      }

      // 3. 用 MODULE_FILE_MAP 推导每个禁用 module 的 chunk prefixes
      const disabledPrefixes = []
      disabledKeys.forEach(k => {
        const prefixes = deriveChunkPrefixes(k, moduleFileMap[k] || [])
        if (prefixes.length > 0) {
          prefixes.forEach(prefix => disabledPrefixes.push({key: k, prefix}))
        }
      })
      console.log('[module-filter] disabledModules=' + disabledKeys.size + ': ' + [...disabledKeys].join(','))
      console.log('[module-filter] disabledPrefixes=' + disabledPrefixes.map(d => d.key + '→' + d.prefix).join(','))

      // 4. 找 entry chunk
      assetsDir = path.join(distDir, 'assets')
      if (!fs.existsSync(assetsDir)) return
      const entryName = fs.readFileSync(entryHtmlPath, 'utf8')
        .match(/<script[^>]+src="\/assets\/(index-[A-Za-z0-9_-]+\.js)"/)?.[1]
      if (!entryName) {
        console.log('[module-filter] cannot find entry chunk in index.html')
        return
      }
      const entryPath = path.join(assetsDir, entryName)
      if (!fs.existsSync(entryPath)) return
      let entryCode = fs.readFileSync(entryPath, 'utf8')

      // 5. 扫 entry 里所有 import() 调用 + mapDeps 数组, 找出被禁的 chunks
      let modified = 0
      let filteredChunks = new Set()

      function chunkBaseOf(chunkRef) {
        // chunkRef like './ServerProfiles-DjYrIjON.js' or 'assets/QrcodeManage-BkgePRKI.css'
        // Vite hash is 8 alphanumeric chars appended with '-' separator
        // 例如: 'QrcodeManage-BkgePRKI.css' -> 'QrcodeManage'
        // 例如: 'ServerProfiles-DjYrIjON.js' -> 'ServerProfiles'
        const cleaned = chunkRef.replace(/^\.\//, '').replace(/^assets\//, '').replace(/\.(js|css)$/, '')
        // 标准格式: <name>-<8 char hash>
        const m = cleaned.match(/^(.+)-([a-zA-Z0-9_-]{8,})$/)
        if (m) return m[1]
        // 回退: 找最后一个 >=6 字符的 segment 作为 hash
        const parts = cleaned.split('-')
        for (let i = parts.length - 1; i >= 0; i--) {
          if (parts[i].length >= 6 && /^[a-zA-Z0-9_]+$/.test(parts[i])) {
            return parts.slice(0, i).join('-')
          }
        }
        return cleaned
      }

      function isDisabled(chunkRef) {
        const chunkBase = chunkBaseOf(chunkRef)
        return disabledPrefixes.find(d => chunkBase === d.prefix || chunkBase.startsWith(d.prefix))
      }

      // 5a. 替换 dynamic import() 调用
      entryCode = entryCode.replace(IMPORT_RE, (full, chunkPath) => {
        if (isDisabled(chunkPath)) {
          filteredChunks.add(chunkPath)
          modified++
          return STUB
        }
        return full
      })

      // 5b. 替换 mapDeps 字符串数组里的资产引用 (前置 chunk + css)
      const beforeMapDeps = filteredChunks.size
      entryCode = entryCode.replace(MAPDEPS_RE, (full, assetPath) => {
        // assetPath like 'assets/QrcodeManage-BkgePRKI.css'
        const ref = './' + assetPath.replace(/^assets\//, '')
        if (isDisabled(ref)) {
          filteredChunks.add(ref)
          return '""'  // 替换为空字符串 (保持数组长度)
        }
        return full
      })
      const mapDepsReplaced = filteredChunks.size - beforeMapDeps

      if (modified === 0) {
        console.log('[module-filter] no disabled chunks found in entry (already filtered or modules not in entry)')
        return
      }

      // 6. 写回 entry (除非 dry-run)
      if (!dryRun) {
        fs.writeFileSync(entryPath, entryCode)
      }
      // 6.5 区分 .js 引用和 .css 引用 (避免报告数字混淆)
      let refJsCount = 0, refCssCount = 0
      filteredChunks.forEach(ref => {
        if (ref.endsWith('.css')) refCssCount++
        else refJsCount++
      })

      console.log('[module-filter] entry modified: ' + modified + ' imports replaced' + (dryRun ? ' [DRY RUN]' : ''))
      console.log('[module-filter] mapDeps cleared: ' + mapDepsReplaced + ' prefetch refs' + (dryRun ? ' [DRY RUN]' : ''))
      console.log('[module-filter] filtered refs: ' + filteredChunks.size + ' (' + refJsCount + ' js + ' + refCssCount + ' css)')

      // 7. 删除孤立 chunk 文件 (同时删 .js 和 .css 同 hash)
      // 每个 ref 触发 .js + .css 两次 fs.existsSync 检查, 但只算实际删除的次数
      let removedJsFiles = 0
      let removedCssFiles = 0
      let removedBytes = 0
      // 按 chunk-base 去重, 避免同一 chunk 的 .js ref 和 .css ref 重复扫描
      const checkedBases = new Set()
      for (const ref of filteredChunks) {
        // ref like './ServerProfiles-DjYrIjON.js' 或 './ServerProfiles-X.css'
        // 提取 base 名字 (无 ext) → 同时检查 .js + .css
        const refBase = path.basename(ref).replace(/\.(js|css)$/, '')
        if (checkedBases.has(refBase)) continue
        checkedBases.add(refBase)
        for (const ext of ['js', 'css']) {
          const fp = path.join(assetsDir, refBase + '.' + ext)
          if (fs.existsSync(fp)) {
            const stat = fs.statSync(fp)
            if (!dryRun) fs.unlinkSync(fp)
            if (ext === 'js') removedJsFiles++
            else removedCssFiles++
            removedBytes += stat.size
          }
        }
      }
      const totalRemoved = removedJsFiles + removedCssFiles
      console.log('[module-filter] removed ' + totalRemoved + ' files (' + removedJsFiles + ' js + ' + removedCssFiles + ' css), ' + (removedBytes / 1024).toFixed(1) + ' KB' + (dryRun ? ' [DRY RUN]' : ''))

      // 7.5 JSON 报告 (供 verify dry-run 后做断言 / 审计 / 集成测试用)
      // 开关: 环境变量 MODULE_FILTER_JSON_REPORT=1, 写到 distDir/.module-filter-report.json
      if (process.env.MODULE_FILTER_JSON_REPORT === '1' || process.env.MODULE_FILTER_JSON_REPORT === 'true') {
        const report = {
          timestamp: new Date().toISOString(),
          dryRun: !!dryRun,
          profile: {
            enabledModules: enabledModuleKeys,
            disabledModules: [...disabledKeys],
            disabledPrefixes: disabledPrefixes.map(d => ({key: d.key, prefix: d.prefix})),
          },
          entry: {
            name: entryName,
            importsReplaced: modified,
            mapDepsCleared: mapDepsReplaced,
            filteredRefs: {
              total: filteredChunks.size,
              js: refJsCount,
              css: refCssCount,
            },
            removedFiles: {
              total: totalRemoved,
              js: removedJsFiles,
              css: removedCssFiles,
            },
            removedBytes,
            removedKb: +(removedBytes / 1024).toFixed(1),
          },
          filteredChunkList: [...new Set([...filteredChunks].map(ref => path.basename(ref)))].sort(),
        }
        const reportPath = path.join(distDir, '.module-filter-report.json')
        // dry-run 也写 (默认开启, 审计最有价值; 设 =0 可关)
        if (process.env.MODULE_FILTER_JSON_FORCE_DRY === '0' && dryRun) {
          // 跳过
        } else {
          fs.writeFileSync(reportPath, JSON.stringify(report, null, 2))
          console.log('[module-filter] JSON report: ' + reportPath)
        }
      }
    }
  }
}
