/**
 * 老板微信聊天指挥系统
 * 接收老板的自然语言命令，识别意图，执行对应操作
 */

import express from 'express'
import { pool } from '../db/connection.js'
import { auth } from '../middleware/auth.js'

const router = express.Router()

// 技能模块导入
import * as attendanceSkill from '../skills/attendance-skill.js'
import * as worklogSkill from '../skills/worklog-skill.js'
import * as taskSkill from '../skills/task-skill.js'
import * as approvalSkill from '../skills/approval-skill.js'
import * as financeSkill from '../skills/finance-skill.js'
import * as inventorySkill from '../skills/inventory-skill.js'
import * as customerSkill from '../skills/customer-skill.js'
import * as employeeSkill from '../skills/employee-skill.js'
import * as departmentSkill from '../skills/department-skill.js'
import * as settingsSkill from '../skills/settings-skill.js'
import { searchSimilarChunks, addLearningLog } from '../skills/kb-skill.js'

// 意图识别映射
const intentMap = {
  // 考勤相关
  'attendance': {
    patterns: [/今天.*考勤|考勤.*怎么样|谁.*打卡|打卡.*情况/i, /.*没.*打卡|未.*打卡/i, /催.*打卡|提醒.*打卡/i],
    skill: attendanceSkill
  },
  // 工作日志
  'worklog': {
    patterns: [/.*日报|.*周报|.*日志/i, /谁.*没写.*日报|未提交.*日志/i, /.*日报.*汇总|本周.*日志/i],
    skill: worklogSkill
  },
  // 任务管理
  'task': {
    patterns: [/派.*任务|给小王.*任务|给小李.*任务/i, /任务.*完成|.*进度/i, /催.*任务|提醒.*任务/i],
    skill: taskSkill
  },
  // 审批
  'approval': {
    patterns: [/待.*审批|需要.*审批|有哪些.*审批/i, /同意.*请假|拒绝.*申请|审批.*通过/i],
    skill: approvalSkill
  },
  // 财务
  'finance': {
    patterns: [/本月.*收入|收入.*多少|销售额/i, /本月.*支出|支出.*多少|成本/i, /哪个.*赚钱|利润.*分析|产品.*赚钱/i],
    skill: financeSkill
  },
  // 库存
  'inventory': {
    patterns: [/库存|还有多少|仓库/i, /哪些.*补货|预警|要补货/i, /出入库|最近.*入库|最近.*出库/i],
    skill: inventorySkill
  },
  // 客户
  'customer': {
    patterns: [/客户|大客户|有哪些.*客户/i, /跟进了哪些|客户.*跟进/i, /客户流失|哪些.*流失/i],
    skill: customerSkill
  },
  // 员工管理
  'employee': {
    patterns: [/员工|有哪些员工|员工列表/i, /新增员工|添加员工|招聘/i, /修改.*职位|调整职位/i, /离职|删除员工/i],
    skill: employeeSkill
  },
  // 部门管理
  'department': {
    patterns: [/部门|有哪些部门|部门列表/i, /创建.*部门|新增部门/i, /调整.*部门|调动部门/i],
    skill: departmentSkill
  },
  // 系统设置
  'settings': {
    patterns: [/系统.*状态|还正常吗|系统.*检查/i, /导出.*数据|数据.*导出/i, /考勤规则|上班时间/i],
    skill: settingsSkill
  }
}

// 识别意图
function recognizeIntent(message) {
  for (const [intent, config] of Object.entries(intentMap)) {
    for (const pattern of config.patterns) {
      if (pattern.test(message)) {
        return { intent, skill: config.skill }
      }
    }
  }
  return null
}

// 通用帮助命令
function getHelp() {
  return `📋 **老板聊天指挥系统 - 可用命令**

**📅 考勤管理**
• "今天考勤怎么样" - 查看今日考勤
• "谁没打卡" - 查看未打卡人员
• "催一下没打卡的人" - 提醒打卡

**📝 工作日志**
• "小王今天日报" - 查看员工日报
• "谁没写日报" - 查看未提交人员

**✅ 任务管理**
• "给小王派个任务：整理客户名单"
• "小王的任务完成了吗" - 查看任务进度

**📊 审批管理**
• "有哪些需要我审批" - 待审批列表
• "同意小李的请假" - 执行审批

**💰 财务报表**
• "本月收入多少"
• "哪个产品最赚钱"

**📦 库存管理**
• "仓库里还有多少货"
• "哪些要补货了"

**👥 客户管理**
• "大客户有哪些"
• "哪些客户流失了"

发送中文问题，我会帮您执行！`
}

// POST /api/boss/chat - 处理老板聊天消息
router.post('/chat', auth, async (req, res, next) => {
  try {
    const { message } = req.body
    
    if (!message || message.trim() === '') {
      return res.json({
        code: 0,
        data: { reply: '您好！我是您的AI管理助手，请告诉我您想做什么？\n\n' + getHelp() }
      })
    }

    // 识别意图
    const recognized = recognizeIntent(message)
    
    if (!recognized) {
      // 未能识别，尝试RAG知识库检索
      try {
        const kbResults = await searchSimilarChunks(message, 3)
        if (kbResults.length > 0 && kbResults[0].score > 0.7) {
          // 高相似度结果，直接返回知识库答案
          const best = kbResults[0]
          return res.json({
            code: 0,
            data: {
              reply: `📖 知识库参考：\n\n${best.content}\n\n——来源：${best.doc_title}`,
              intent: 'kb',
              kb_results: kbResults
            }
          })
        }
      } catch (e) {
        console.error('[boss-chat] kb search error:', e.message)
      }

      // 知识库无结果，记录学习
      try {
        await addLearningLog(pool, {
          user_id: req.user?.id || null,
          session_id: req.user?.session_id || '',
          user_message: message,
          ai_reply: '',
          source: 'boss_chat',
          intent: '',
          confidence: 0,
          kb_doc_id: null
        })
      } catch (e) {
        console.error('[boss-chat] add learning log error:', e.message)
      }

      return res.json({
        code: 0,
        data: {
          reply: `我理解您说的是："${message}"\n\n抱歉，我暂时无法处理这类请求。已记录，我会学习，下次告诉你。\n\n${getHelp()}`
        }
      })
    }

    // 调用对应技能处理
    const { intent, skill } = recognized
    const result = await skill.handle(message, req.user, pool)

    res.json({
      code: 0,
      data: { reply: result.reply, intent }
    })

  } catch (err) {
    next(err)
  }
})

// GET /api/boss/help - 获取帮助
router.get('/help', auth, (req, res) => {
  res.json({
    code: 0,
    data: { help: getHelp() }
  })
})

// GET /api/boss/skills - 获取所有技能列表
router.get('/skills', auth, (req, res) => {
  const skills = Object.keys(intentMap).map(key => ({
    name: key,
    patterns: intentMap[key].patterns.map(p => p.source)
  }))
  res.json({
    code: 0,
    data: { skills }
  })
})

export default router
