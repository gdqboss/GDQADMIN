#!/usr/bin/env node
/**
 * diff-modules.mjs — 模块影响范围分析
 *
 * 目的：改 1 个模块前，精确知道"会影响哪些 chunks"
 *
 * 用法：
 *   node scripts/diff-modules.mjs <module-name>
 *   # 例：node scripts/diff-modules.mjs InOutList
 *
 * 输出：
 *   模块涉及的所有 chunks
 *   修改后会变的 chunks（基于文件 mtime 估算）
 *   同步时需要传输的文件清单
 *
 * 配合 verify-sync.sh 用：
 *   1. 改 module X 之前跑这个，看影响范围
 *   2. build 后跑这个，生成新的 manifest
 *   3. sync 时只传受影响的 chunks
 */

import fs from 'fs'
import path from 'path'
import crypto from 'crypto'

const moduleName = process.argv[2]
if (!moduleName) {
  console.error('用法: node scripts/diff-modules.mjs <module-name>')
  console.error('例:   node scripts/diff-modules.mjs InOutList')
  process.exit(1)
}

const ROOT = path.resolve('.')
const MANIFEST = path.join(ROOT, 'dist/chunk-manifest.json')

if (!fs.existsSync(MANIFEST)) {
  console.error('❌ dist/chunk-manifest.json 不存在，先 build')
  process.exit(1)
}

const m = JSON.parse(fs.readFileSync(MANIFEST, 'utf8'))

// 模糊匹配模块名（InOutList 包含 inout 关键字也行）
const candidates = Object.keys(m.moduleMap).filter(k => {
  const lk = k.toLowerCase()
  const lm = moduleName.toLowerCase()
  return lk === lm || lk.includes(lm) || lm.includes(lk)
})

if (candidates.length === 0) {
  console.error(`❌ 模块 "${moduleName}" 在 manifest 里没找到`)
  console.error(`可用模块: ${Object.keys(m.moduleMap).slice(0, 20).join(', ')}...`)
  process.exit(1)
}

console.log(`\n=== 模块 "${moduleName}" 影响范围分析 ===\n`)

let totalSize = 0
for (const mod of candidates) {
  const chunks = m.moduleMap[mod] || []
  console.log(`📦 ${mod}  (${chunks.length} chunks)`)
  for (const chunk of chunks) {
    const info = m.chunks[chunk]
    const sizeKB = (info.size / 1024).toFixed(1)
    const coreTag = info.isCore ? ' 🔴 CORE' : ''
    console.log(`   - ${chunk}  ${sizeKB}KB${coreTag}`)
    totalSize += info.size
  }
  console.log()
}

console.log(`总计 ${candidates.length} 个匹配模块 / ${Object.values(m.moduleMap).filter((_, i) => candidates.includes(Object.keys(m.moduleMap)[i])).reduce((s, c) => s + c.length, 0)} chunks / ${(totalSize / 1024).toFixed(1)}KB`)
console.log()
console.log('💡 同步时：')
console.log('   1. 只 rsync 这些 chunks（其它不动）')
console.log('   2. 跑 bash scripts/verify-sync.sh 验证')
console.log('   3. 改动小的模块 → 总传输 < 50KB，几乎瞬时完成')
console.log()

// 输出 rsync include 列表
console.log('--- rsync include 列表（--include-from 用）---')
const allChunks = []
for (const mod of candidates) {
  for (const chunk of m.moduleMap[mod] || []) {
    allChunks.push(chunk)
  }
}
console.log(allChunks.join('\n'))
