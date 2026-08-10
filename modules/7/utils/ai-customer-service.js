import { pool } from '../db/connection.js'

// MiniMax API 配置
const MINIMAX_API_URL = 'https://api.minimaxi.com/anthropic/v1/messages'
const MINIMAX_API_KEY = 'sk-cp-BbLwwqBSr8RrPusVeP8-U4_ezPtJS48rVjuMepMrxOZR4vcyRt_zD-OwhYcm7KKVnWT6nZxvi9q8zTsa1yC_mIaoqD4UyPjQn6xM4oOaoR5S0AHQut6jQtU'
const AI_MODEL = 'MiniMax-M2.7'

// 闲聊计数器（内存缓存）
const chitchatCount = new Map()
const CHITCHAT_LIMIT = 5
const CHITCHAT_COOLDOWN_MS = 60 * 60 * 1000

// Telegram通知
const TELEGRAM_BOT_TOKEN = process.env.TELEGRAM_BOT_TOKEN || ''
const TELEGRAM_CHAT_ID = '861063885'

function getChitchatCount(userId) {
  const record = chitchatCount.get(userId)
  if (!record) return 0
  if (Date.now() - record.lastTime > CHITCHAT_COOLDOWN_MS) {
    chitchatCount.delete(userId)
    return 0
  }
  return record.count
}

function incrementChitchat(userId) {
  const record = chitchatCount.get(userId) || { count: 0, lastTime: 0 }
  record.count++
  record.lastTime = Date.now()
  chitchatCount.set(userId, record)
  return record.count
}

function resetChitchat(userId) {
  chitchatCount.delete(userId)
}

function isChitchat(message) {
  const lowerMsg = message.toLowerCase()
  const productKeywords = ['产品', '二维码', '扫码', '保修', '售后', '退货', '退款', '质量', '问题', '故障', '维修', '订单', '物流', '发货', '签收', '价格', '优惠', '购买', '买的', '收到', '货物', '包装', '破损', '坏了', '不能', '无法', '怎么', '如何', '哪里', '什么']
  if (message.length < 20 && !productKeywords.some(k => lowerMsg.includes(k))) return true
  return false
}

async function notifyBoge(message, userInfo, productInfo) {
  if (!TELEGRAM_BOT_TOKEN) {
    console.log('[AI客服] Telegram bot token 未配置，跳过通知')
    return
  }
  
  const text = `🦈 *江小鱼AI客服通知*

👤 客户: ${userInfo.name || '未知'} (${userInfo.phone || '未知'})
📦 产品: ${productInfo?.name || '未知'}
💬 消息: ${message}

⚠️ 需要人工处理`

  try {
    await fetch(`https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: TELEGRAM_CHAT_ID,
        text,
        parse_mode: 'Markdown'
      })
    })
  } catch (e) {
    console.error('[AI客服] 通知失败:', e.message)
  }
}

function buildSystemPrompt(productInfo, userInfo) {
  const productName = productInfo?.name || '未知产品'
  const productSku = productInfo?.sku || ''
  const productSpec = productInfo?.spec || ''
  const warrantyEnd = productInfo?.warranty_end || ''
  const afterSaleContact = productInfo?.after_sale_contact || '请联系客服热线'
  
  let warrantyInfo = '该产品可能有保修期。'
  if (warrantyEnd) {
    const isValid = new Date(warrantyEnd) >= new Date()
    warrantyInfo = isValid 
      ? `产品仍在保修期内，保修截止日期为 ${warrantyEnd.slice(0, 10)}。`
      : `产品已过保修期，保修期截止为 ${warrantyEnd.slice(0, 10)}。`
  }
  
  return `你是江小鱼，澳門中醫藥學會的AI客服助手。你真诚、友善、专业。

## 公司信息
- 公司名称：澳門中醫藥學會
- 主营业务：箱包、皮具等产品的生产销售
- 售后政策：如有质量问题，按保修期处理

## 当前产品信息
- 产品名称：${productName}
- SKU：${productSku}
- 规格：${productSpec}
- ${warrantyInfo}
- 售后联系方式：${afterSaleContact}

## 客户信息
- 客户姓名：${userInfo?.name || '游客'}
- 客户电话：${userInfo?.phone || '未登录'}

## 回答原则
1. 真诚友好，像朋友聊天一样自然
2. 了解公司产品，能回答关于产品的问题
3. 能处理常见的售后咨询（保修、退货、维修等）
4. 遇到重要事项（投诉、严重问题）要提醒人工处理
5. 如果客户闲聊，可以适度回应，但不要过度
6. 回答要简洁，不要太长（不超过100字）
7. 如果不确定，说"这个我需要帮您问一下"然后通知人工

## 当前时间
${new Date().toLocaleString('zh-CN', { timeZone: 'Asia/Shanghai' })}
`
}

async function callAI(messages) {
  const response = await fetch(MINIMAX_API_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${MINIMAX_API_KEY}`
    },
    body: JSON.stringify({
      model: AI_MODEL,
      messages,
      max_tokens: 500,
      temperature: 0.7
    })
  })
  
  if (!response.ok) {
    const error = await response.text()
    throw new Error(`AI API错误: ${response.status} - ${error}`)
  }
  
  const data = await response.json()
  const textObj = data.content?.find(c => c.type === 'text'); return textObj?.text || '抱歉，我现在有点忙，请稍后再试。'
}

async function getProductInfo(qrcodeId) {
  try {
    const [[qr]] = await pool.query(
      `SELECT q.*, p.name as name, p.sku, p.spec, q.warranty_end, q.after_sale_contact
       FROM qrcodes q
       LEFT JOIN products p ON q.product_id = p.id
       WHERE q.id = ?`,
      [qrcodeId]
    )
    return qr
  } catch (e) {
    console.error('[AI客服] 获取产品信息失败:', e.message)
    return null
  }
}

async function getChatHistory(aftersaleId, limit = 10) {
  try {
    const [rows] = await pool.query(
      `SELECT sender_type, sender_name, content, created_at 
       FROM aftersale_messages 
       WHERE aftersale_id = ? 
       ORDER BY created_at DESC LIMIT ?`,
      [aftersaleId, limit]
    )
    return rows.reverse()
  } catch (e) {
    console.error('[AI客服] 获取聊天历史失败:', e.message)
    return []
  }
}

export async function processCustomerMessage(aftersaleId, qrcodeId, h5UserId, customerMessage) {
  const userId = h5UserId.toString()
  
  // 检查闲聊限制
  const count = getChitchatCount(userId)
  if (count >= CHITCHAT_LIMIT) {
    return {
      reply: `您好，我是江小鱼AI客服👋 刚才我们聊了一些和产品无关的话题，我现在需要休息一下，请您1小时后再来找我，或者直接拨打客服热线 ${afterSaleContact || '400-xxx-xxxx'} 转人工服务，谢谢理解！`,
      shouldNotify: false,
      isChitchatBlocked: true
    }
  }
  
  // 判断闲聊
  const chitchatDetected = isChitchat(customerMessage)
  if (chitchatDetected) {
    const newCount = incrementChitchat(userId)
    if (newCount >= CHITCHAT_LIMIT) {
      return {
        reply: `您好，我们已经聊了${CHITCHAT_LIMIT}次了，我这边是AI客服，主要帮助解决产品相关问题哦～ 如果您有产品或售后问题可以直接问我，或者拨打客服热线转人工服务。谢谢！`,
        shouldNotify: false,
        isChitchatBlocked: true
      }
    }
  } else {
    resetChitchat(userId)
  }
  
  // 获取信息
  const [productInfo, [[userInfo]], history] = await Promise.all([
    getProductInfo(qrcodeId),
    pool.query('SELECT name, phone FROM h5_users WHERE id = ?', [h5UserId]),
    getChatHistory(aftersaleId, 10)
  ])
  
  // 构建消息
  const messages = [
    { role: 'system', content: buildSystemPrompt(productInfo, userInfo) },
    ...history.map(h => ({
      role: h.sender_type === 'customer' ? 'user' : 'assistant',
      content: `${h.sender_name || '客户'}: ${h.content}`
    })),
    { role: 'user', content: customerMessage }
  ]
  
  try {
    const reply = await callAI(messages)
    const shouldNotify = /投诉|严重|坏了|质量问题|不退|欺诈|举报|曝光/.test(customerMessage)
    return { reply, shouldNotify, isChitchatBlocked: false }
  } catch (e) {
    console.error('[AI客服] AI调用失败:', e.message)
    return {
      reply: '抱歉，我现在有点忙碌，请您稍后再试，或者拨打客服热线。',
      shouldNotify: false,
      isChitchatBlocked: false
    }
  }
}

export async function saveAIMessage(aftersaleId, content) {
  try {
    // 去重检查：5秒内同一aftersale_id + 同一AI回复内容，不重复写入
    const [[dup]] = await pool.query(
      'SELECT id FROM aftersale_messages WHERE aftersale_id = ? AND sender_type = "staff" AND content = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND) LIMIT 1',
      [aftersaleId, content]
    )
    if (dup) {
      console.log('[AI客服] 跳过重复AI回复:', content.slice(0, 50))
      return
    }
    await pool.query(
      `INSERT INTO aftersale_messages (aftersale_id, sender_type, sender_id, sender_name, content) VALUES (?, 'staff', '江小鱼AI', '江小鱼AI', ?)`,
      [aftersaleId, content]
    )
  } catch (e) {
    console.error('[AI客服] 保存AI回复失败:', e.message)
  }
}

export { notifyBoge }
export default { processCustomerMessage, saveAIMessage, notifyBoge }
