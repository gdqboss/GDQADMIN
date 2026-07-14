#!/usr/bin/env node
/**
 * check-syntax.mjs
 * ─────────────────────────────────────────────────────
 * 校验 src/pages/*.vue 的 script 块是否语法 OK
 * 用法:
 *   npm run lint:syntax
 * ─────────────────────────────────────────────────────
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const PAGES_DIR = path.join(process.cwd(), 'src/pages')

const files = fs.readdirSync(PAGES_DIR).filter(f => f.endsWith('.vue')).sort()

let ok = 0, bad = []
for (const f of files) {
  const src = fs.readFileSync(path.join(PAGES_DIR, f), 'utf8')
  const m = src.match(/<script[^>]*>([\s\S]*?)<\/script>/)
  if (!m) {
    bad.push([f, 'no script'])
    continue
  }
  const tmp = `/tmp/_cs_${f}.mjs`
  fs.writeFileSync(tmp, m[1])
  try {
    execSync(`node --check ${tmp}`, { stdio: 'pipe' })
    ok++
  } catch (e) {
    bad.push([f, e.stderr.toString().split('\n').slice(0, 3).join(' | ')])
  }
}

console.log(`\n✅ Syntax OK: ${ok}/${files.length}`)
if (bad.length) {
  console.log('\n❌ 语法错误：')
  for (const [f, err] of bad) console.log(`  - ${f}: ${err}`)
  process.exit(1)
}
console.log('🎉 全部通过\n')
