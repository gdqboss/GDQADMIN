// WeChat Native Pay utility
import crypto from 'crypto'

const BASE_URL = 'https://api.mch.weixin.qq.com'

/**
 * 获取微信支付配置
 */
export async function getWechatPayConfig(pool) {
  const [[config]] = await pool.query(
    'SELECT * FROM wechat_pay_config WHERE status = "active" LIMIT 1'
  )
  return config
}

/**
 * 生成微信支付签名
 */
function sign(params, apiKey) {
  const sorted = Object.keys(params)
    .filter(k => params[k] !== '' && params[k] !== null && k !== 'sign')
    .sort()
    .map(k => `${k}=${params[k]}`)
    .join('&')
  return crypto
    .createHash('md5')
    .update(sorted + '&key=' + apiKey, 'utf8')
    .digest('hex')
    .toUpperCase()
}

/**
 * 统一下单（Native支付）
 */
export async function createNativeOrder(pool, { orderNo, amount, description, notifyUrl }) {
  const config = await getWechatPayConfig(pool)
  if (!config) {
    throw new Error('微信支付未配置')
  }

  const timestamp = Math.floor(Date.now() / 1000).toString()
  const nonceStr = crypto.randomBytes(16).toString('hex')

  const params = {
    appid: config.appid,
    mchid: config.mchid,
    description: description || '商品购买',
    out_trade_no: orderNo,
    time_expire: new Date(Date.now() + 30 * 60 * 1000).toISOString().replace(/[-:]/g, '').split('.')[0] + '+0800',
    amount: {
      total: Math.round(amount * 100), // 金额单位是分
      currency: 'CNY'
    },
    notify_url: notifyUrl || `https://claw.gdqshop.cn/api/pay/wechat/callback`,
    trade_type: 'NATIVE',
    nonce_str: nonceStr
  }

  // 生成签名
  params.sign = sign(params, config.api_key)

  // 调用微信统一下单API
  const response = await fetch(`${BASE_URL}/pay/unifiedorder`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/xml' },
    body: toXML(params)
  })

  const result = await response.text()
  const xmlData = parseXML(result)

  if (xmlData.result_code === 'SUCCESS' && xmlData.return_code === 'SUCCESS') {
    return {
      code_url: xmlData.code_url, // 用于生成二维码
      trade_type: xmlData.trade_type,
      prepay_id: xmlData.prepay_id,
      mchid: xmlData.mchid
    }
  } else {
    throw new Error(xmlData.err_code_des || xmlData.return_msg || '微信支付下单失败')
  }
}

/**
 * 查询订单
 */
export async function queryOrder(pool, orderNo) {
  const config = await getWechatPayConfig(pool)
  if (!config) {
    throw new Error('微信支付未配置')
  }

  const timestamp = Math.floor(Date.now() / 1000).toString()
  const nonceStr = crypto.randomBytes(16).toString('hex')

  const params = {
    appid: config.appid,
    mchid: config.mchid,
    out_trade_no: orderNo,
    nonce_str: nonceStr
  }

  params.sign = sign(params, config.api_key)

  const response = await fetch(`${BASE_URL}/pay/orderquery`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/xml' },
    body: toXML(params)
  })

  const result = await response.text()
  const xmlData = parseXML(result)

  if (xmlData.result_code === 'SUCCESS' && xmlData.return_code === 'SUCCESS') {
    return {
      trade_state: xmlData.trade_state, // SUCCESS/REFUND/CLOSED/PAYERROR/NOTPAY/ACCEPT
      trade_state_desc: xmlData.trade_state_desc,
      transaction_id: xmlData.transaction_id,
      amount: xmlData.amount ? {
        total: parseInt(xmlData.amount.total) / 100,
        currency: xmlData.amount.currency
      } : null
    }
  } else {
    throw new Error(xmlData.err_code_des || '查询失败')
  }
}

/**
 * 验证回调签名
 */
export function verifyCallback(params, apiKey) {
  const { sign: receivedSign, ...rest } = params
  const calculatedSign = sign(rest, apiKey)
  return calculatedSign === receivedSign
}

/**
 * XML转对象
 */
function parseXML(xml) {
  const result = {}
  const matches = xml.match(/<([^>]+)>([^<]*)<\/[^>]+>/g) || []
  for (const match of matches) {
    const [, key, value] = match.match(/<([^>]+)>([^<]*)<\/[^>]+>/)
    result[key] = value
  }
  return result
}

/**
 * 对象转XML
 */
function toXML(obj) {
  return Object.keys(obj)
    .map(k => {
      if (typeof obj[k] === 'object') {
        return `<${k}>${JSON.stringify(obj[k])}</${k}>`
      }
      return `<${k}><![CDATA[${obj[k]}]]></${k}>`
    })
    .join('')
}
