#!/usr/bin/env node
/**
 * build-profile-router.js
 * 根据 profile_id 生成该服务器专用的路由文件
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const rootDir = path.resolve(__dirname, '..')

// 加载配置
const { PROFILE_MODULES, MODULE_ROUTE_MAP } = await import(path.join(rootDir, 'modules/profile-config.js'))

const profileId = parseInt(process.argv[2])

if (!profileId || !PROFILE_MODULES[profileId]) {
  console.error(`Usage: node build-profile-router.js <profile_id>`)
  console.error(`Available profile_ids: ${Object.keys(PROFILE_MODULES).join(', ')}`)
  process.exit(1)
}

const modules = PROFILE_MODULES[profileId]
console.log(`Building router for profile ${profileId}...`)
console.log(`Modules: ${modules.join(', ')}`)

// 收集该 profile 需要保留的路由 path
const neededPaths = new Set()
const neededNames = new Set()

for (const mod of modules) {
  const routes = MODULE_ROUTE_MAP[mod] || []
  for (const r of routes) {
    neededPaths.add(r)
  }
}

// 特殊路由保留 (mall 和 login)
neededPaths.add('/mall')
neededPaths.add('/login')
neededNames.add('Login')

// 读取原始路由文件
const routerPath = path.join(rootDir, 'router', 'index.js')
const routerContent = fs.readFileSync(routerPath, 'utf-8')

// 找到所有 children: [ 的位置
let pos = 0
const positions = []
while (true) {
  const idx = routerContent.indexOf('children: [', pos)
  if (idx === -1) break
  positions.push(idx)
  pos = idx + 1
}

// 使用第二个 children: [ (MainLayout 的 children)
const childrenStart = positions[1]
const beforeChildren = routerContent.substring(0, childrenStart + 'children: ['.length)
const afterChildren = routerContent.substring(childrenStart + 'children: ['.length)

//找到匹配的 ]
let braceCount = 0
let endIdx = 0
for (let i = 0; i < afterChildren.length; i++) {
  if (afterChildren[i] === '[') braceCount++
  if (afterChildren[i] === ']') {
    if (braceCount === 0) {
      endIdx = i
      break
    }
    braceCount--
  }
}

const childrenContent = afterChildren.substring(0, endIdx)
const afterBracket = afterChildren.substring(endIdx + 1)

// 逐行解析 children
const childrenLines = childrenContent.split('\n')
const filteredChildrenLines = []

for (const line of childrenLines) {
  const nameMatch = line.match(/name: '([^']+)'/)
  const pathMatch = line.match(/path: '([^']+)'/)
  
  const nameVal = nameMatch ? nameMatch[1] : null
  const pathVal = pathMatch ? pathMatch[1] : null
  
  let keep = false
  
  // Dashboard 用空 path
  if (pathVal === '' && nameVal === 'Dashboard') {
    keep = true
  }
  
  // 检查 path 是否需要保留
  if (pathVal) {
    if (neededPaths.has(pathVal)) {
      keep = true
    }
    // 处理带参数的 path: orders/:id -> 检查 orders 是否需要
    if (pathVal.includes(':')) {
      const basePath = pathVal.split('/')[0]
      if (neededPaths.has(basePath) || neededPaths.has(pathVal)) {
        keep = true
      }
    }
  }
  
  // 检查 name 是否需要保留
  if (nameVal && neededNames.has(nameVal)) {
    keep = true
  }
  
  if (keep) {
    filteredChildrenLines.push(line)
  }
}

// 重建 children 内容（不包含末尾的 ]，由 afterBracket 提供）
const filteredChildren = filteredChildrenLines.join('\n')

// 重建完整路由：beforeChildren + [ + filteredChildren + ] + afterBracket
const filteredRouter = beforeChildren + '\n' + filteredChildren + '\n' + '  ]' + '\n' + afterBracket

// 确保输出目录存在
const outDir = path.join(rootDir, 'modules', String(profileId), 'router')
fs.mkdirSync(outDir, { recursive: true })

const outPath = path.join(outDir, 'index.js')
fs.writeFileSync(outPath, filteredRouter)

console.log(`Router written to: ${outPath}`)
console.log(`Done!`)

// 输出统计
const originalLines = routerContent.split('\n').length
const newLines = filteredRouter.split('\n').length
console.log(`Original router: ${originalLines} lines, Filtered: ${newLines} lines`)
console.log(`Removed ${originalLines - newLines} lines`)
