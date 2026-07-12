// Web Chat API - 本地消息队列 + Telegram 通知
import { Router } from 'express'
import 'dotenv/config'

const router = Router()

// Telegram Bot Token (base64 编码)
const BOT_TOKEN = Buffer.from('ODY2MzAyNDkzOTpBQUduOXQwNi1EWG42SDZtRjVJanNqeHJxQXI4RzUyVVVuMA==', 'base64').toString()
const BOT_CHAT_ID = '861063885'

const conversations = {}
let nextMsgId = 1

function getOrCreateConv(convId) {
  if (!conversations[convId]) conversations[convId] = []
  return conversations[convId]
}

function writeMessage(convId, role, text) {
  const conv = getOrCreateConv(convId)
  const msg = { id: role + '_' + convId + '_' + (nextMsgId++), role, text, ts: Date.now() }
  conv.push(msg)
  return msg
}

router.get('/poll/:convId', (req, res) => {
  const convId = req.params.convId
  const after = req.query.after || ''
  const conv = conversations[convId]
  if (!conv) return res.json({ ok: true, messages: [] })
  const newMessages = conv.filter(m => m.id !== after)
  newMessages.sort((a, b) => a.ts - b.ts)
  res.json({ ok: true, messages: newMessages })
})

router.post('/send', async (req, res) => {
  try {
    const { text, conv_id } = req.body
    if (!text) return res.json({ ok: false, error: '消息不能为空' })
    const convId = conv_id || 'default'
    writeMessage(convId, 'user', text)

    // 通知 Telegram
    try {
      const botToken = BOT_TOKEN
      console.log('[chat] BOT_TOKEN first 10:', botToken.substring(0, 10), 'len:', botToken.length)
      const tgRes = await fetch('https://api.telegram.org/bot' + botToken + '/sendMessage', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ chat_id: BOT_CHAT_ID, text: '💬 网页端消息:\n\n' + text + '\n\nconv_id: ' + convId })
      })
      const tgData = await tgRes.json()
      console.log('[chat] Telegram result:', JSON.stringify(tgData))
    } catch(e) {}

    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.post('/reply', (req, res) => {
  try {
    const { conv_id, text } = req.body
    if (!conv_id || !text) return res.json({ ok: false, error: '缺少参数' })
    writeMessage(conv_id, 'assistant', text)
    res.json({ ok: true })
  } catch (e) {
    res.json({ ok: false, error: e.message })
  }
})

router.get('/', (req, res) => {
  res.sendFile('/root/weapp-mall/chat.html', { root: '/' })
})

export default router
