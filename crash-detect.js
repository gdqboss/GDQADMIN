// /root/server/crash-detect.js (ESM)
// 启动检测 + 崩溃捕获 — 学自 xai-org/grok-build xai-crash-handler
// 用法: 在 server/index.js 顶部 import
//   import { checkPreviousCrash, installCrashCapture } from './crash-detect.js'
//   checkPreviousCrash()                     // 启动时第一件事
//   installCrashCapture({ app_version })      // 注册 uncaughtException + unhandledRejection handler
//
// 设计原则 (学自 grok-build):
//   1. checkPreviousCrash() 必须在 install 之前跑
//   2. 保留最近 5 份 crash report (避免磁盘涨爆)
//   3. report 是 JSON 同时打 stdout 给人看
//   4. 包含 app_version + git_sha + profile + crash_time + error_type + stack

import fs from 'fs'
import path from 'path'
import { execSync } from 'child_process'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const SERVER_ROOT = path.dirname(__filename)

const CRASH_DIR = process.env.SERVER_CRASH_DIR || '/root/.hermes/server-crashes'
const MAX_KEEP = 5

function nowIso() {
  return new Date().toISOString()
}

function getGitSha() {
  try {
    return execSync('git rev-parse --short HEAD 2>/dev/null', { cwd: SERVER_ROOT })
      .toString()
      .trim()
  } catch {
    return 'unknown'
  }
}

function getAppVersion() {
  // 优先用 env, 其次 package.json version, 最后 git sha
  if (process.env.SERVER_APP_VERSION) return process.env.SERVER_APP_VERSION
  try {
    const pkg = JSON.parse(fs.readFileSync(path.join(SERVER_ROOT, 'package.json'), 'utf8'))
    return pkg.version || getGitSha()
  } catch {
    return getGitSha()
  }
}

function getProfile() {
  return process.env.SERVER_PROFILE || 'sgp'
}

function ensureDir() {
  if (!fs.existsSync(CRASH_DIR)) fs.mkdirSync(CRASH_DIR, { recursive: true })
}

export function captureCrash(err, errorType, extra = {}) {
  ensureDir()
  const timestamp = nowIso()
  const crashFile = path.join(CRASH_DIR, `crash-${Date.now()}.json`)
  const lastCrashFile = path.join(CRASH_DIR, 'last-crash.json')

  const report = {
    timestamp,
    error_type: errorType,
    error_message: (err && err.message) || String(err),
    error_stack: (err && err.stack) || '',
    app_version: getAppVersion(),
    git_sha: getGitSha(),
    profile: getProfile(),
    node_version: process.version,
    pid: process.pid,
    uptime_sec: Math.round(process.uptime()),
    memory_rss_mb: Math.round(process.memoryUsage().rss / 1024 / 1024),
    ...extra,
  }

  try {
    fs.writeFileSync(crashFile, JSON.stringify(report, null, 2))
    fs.writeFileSync(lastCrashFile, JSON.stringify(report, null, 2))

    // 保留最近 5 份
    const allCrashes = fs
      .readdirSync(CRASH_DIR)
      .filter((f) => /^crash-\d+\.json$/.test(f))
      .map((f) => ({
        f,
        t: parseInt(f.match(/crash-(\d+)/)[1], 10),
      }))
      .sort((a, b) => b.t - a.t)
    const toDelete = allCrashes.slice(MAX_KEEP).map((x) => x.f)
    for (const f of toDelete) {
      try {
        fs.unlinkSync(path.join(CRASH_DIR, f))
      } catch {}
    }

    console.error(`[CRASH-CAPTURED] ${errorType}: ${report.error_message}`)
    console.error(`[CRASH-CAPTURED] report: ${crashFile}`)
    return crashFile
  } catch (writeErr) {
    console.error(`[CRASH-CAPTURE-FAILED] ${writeErr.message}`)
    return null
  }
}

export function checkPreviousCrash() {
  const lastCrashFile = path.join(CRASH_DIR, 'last-crash.json')
  if (!fs.existsSync(lastCrashFile)) return null

  try {
    const raw = fs.readFileSync(lastCrashFile, 'utf8')
    const crash = JSON.parse(raw)

    console.error('\n' + '='.repeat(60))
    console.error(`⚠️  [PREV-CRASH-DETECTED] server 上次挂过:`)
    console.error(`    时间: ${crash.timestamp}`)
    console.error(`    类型: ${crash.error_type}`)
    console.error(`    错误: ${crash.error_message}`)
    console.error(`    profile: ${crash.profile} | git_sha: ${crash.git_sha} | pid: ${crash.pid}`)
    console.error(`    报告: ${lastCrashFile}`)
    console.error('='.repeat(60) + '\n')

    return crash
  } catch (err) {
    console.error(`[PREV-CRASH-READ-FAIL] ${err.message}`)
    return null
  }
}

export function installCrashCapture(opts = {}) {
  const app_version = opts.app_version || getAppVersion()

  process.on('uncaughtException', (err) => {
    captureCrash(err, 'uncaughtException')
    console.error('[CRASH-FATAL] uncaughtException, exit 1')
    // 给 500ms 让日志 flush 再退出
    setTimeout(() => process.exit(1), 500)
  })

  process.on('unhandledRejection', (reason) => {
    captureCrash(reason, 'unhandledRejection')
    console.error('[CRASH-WARN] unhandledRejection logged')
  })

  console.log(`[crash-detect] installed | version=${app_version} | dir=${CRASH_DIR} | keep=${MAX_KEEP}`)
}

export { CRASH_DIR }
