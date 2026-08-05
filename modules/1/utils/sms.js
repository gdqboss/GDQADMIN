// 腾讯云短信服务
import tencentcloud from 'tencentcloud-sdk-nodejs-sms'

const SmsClient = tencentcloud.sms.v20210111.Client

// 从环境变量读取配置
const SMS_SECRET_ID = process.env.TENCENT_SMS_SECRET_ID || ''
const SMS_SECRET_KEY = process.env.TENCENT_SMS_SECRET_KEY || ''
const SMS_SDK_APP_ID = process.env.TENCENT_SMS_SDK_APP_ID || ''
const SMS_SIGN_NAME = process.env.TENCENT_SMS_SIGN_NAME || 'GDQ商贸'
const SMS_TEMPLATE_ID = process.env.TENCENT_SMS_TEMPLATE_ID || ''

// 初始化客户端
const client = SMS_SECRET_ID && SMS_SECRET_KEY ? new SmsClient({
  credential: {
    secretId: SMS_SECRET_ID,
    secretKey: SMS_SECRET_KEY,
  },
  region: 'ap-guangzhou',
  profile: {
    httpProfile: {
      endpoint: 'sms.tencentcloudapi.com',
    },
  },
}) : null

/**
 * 发送短信验证码
 * @param {string} phone - 手机号（需要带+86）
 * @param {string} code - 验证码
 * @returns {Promise<boolean>}
 */
export async function sendSmsCode(phone, code) {
  // 测试环境：如果未配置腾讯云，直接返回成功（验证码会打印到控制台）
  if (!client || !SMS_SDK_APP_ID || !SMS_TEMPLATE_ID) {
    console.log(`[SMS TEST MODE] 验证码: ${code} -> ${phone}`)
    return true
  }

  try {
    // 确保手机号格式正确（+86开头）
    const phoneNumber = phone.startsWith('+86') ? phone : `+86${phone}`

    const params = {
      PhoneNumberSet: [phoneNumber],
      SmsSdkAppId: SMS_SDK_APP_ID,
      SignName: SMS_SIGN_NAME,
      TemplateId: SMS_TEMPLATE_ID,
      TemplateParamSet: [code], // 只需要验证码一个参数
    }

    const response = await client.SendSms(params)

    // 检查发送结果
    if (response.SendStatusSet && response.SendStatusSet[0]) {
      const status = response.SendStatusSet[0]
      if (status.Code === 'Ok') {
        console.log(`[SMS] 发送成功: ${phone}`)
        return true
      } else {
        console.error(`[SMS] 发送失败: ${status.Code} - ${status.Message}`)
        return false
      }
    }

    return false
  } catch (error) {
    console.error('[SMS] 发送异常:', error.message)
    return false
  }
}

/**
 * 生成6位数字验证码
 * @returns {string}
 */
export function generateCode() {
  return Math.floor(100000 + Math.random() * 900000).toString()
}
