import { createI18n } from 'vue-i18n'

const messages = {
  zh: {
    scan: {
      title: '扫码查询',
      loading: '加载中...',
      notFound: '未找到该产品信息',
      loadError: '加载失败，请稍后重试',
      share: '分享',
      buyLinks: '购买链接',
      clickToBuy: '点击购买',
      warrantyInfo: '保修信息',
      joinServiceGroup: '加入服务群',
      joinServiceGroupHint: '扫码即可加入专属服务群，获取更多帮助',
      longPressToJoin: '长按识别二维码加入',
      longPressToJoinVia: '长按通过{platform}识别加入',
      loginRequired: '登录后可查看',
      loginToView: '立即登录',
      loginToApply: '立即登录申请',
      loginExpired: '登录已过期，请重新登录',
      applyAfterSale: '申请售后',
      expand: '展开',
      collapse: '收起',
      nameOrContact: '姓名/联系方式',
      namePlaceholder: '请输入姓名或联系方式',
      issueDesc: '问题描述',
      issuePlaceholder: '请详细描述您的问题...',
      uploadImages: '上传图片',
      submitAfterSale: '提交售后申请',
      submitting: '提交中...',
      submitFailed: '提交失败，请稍后重试',
      networkError: '网络错误，请稍后重试',
      linkCopied: '链接已复制',
      afterSaleSubmitted: '售后申请已提交！',
      myAftersale: '我的售后记录',
      fault: '故障',
      solution: '解决方案',
      customerService: '客服',
      statusPending: '处理中',
      statusResolved: '已解决',
      statusRejected: '已拒绝',
      mine: '我的'
    },
    qrcode: {
      warrantyEnd: '保修截止',
      warrantyValid: '保修中',
      warrantyExpired: '已过期',
      afterSaleContact: '售后联系方式',
      repairRecords: '维修记录'
    },
    product: {
      spec: '规格',
      groupQrTypeWechatWork: '企业微信',
      groupQrTypeDingtalk: '钉钉',
      groupQrTypeTelegram: 'Telegram',
      groupQrTypeWhatsapp: 'WhatsApp',
      groupQrTypeOther: '其他',
      platformOther: '其他平台'
    },
    common: {
      unbound: '未绑定',
      category: '分类',
      unknown: '未知',
      submitting: '提交中...'
    },
    serviceChat: {
      title: '在线客服',
      noMessages: '暂无消息',
      inputPlaceholder: '请输入消息...',
      fab: '联系客服',
      networkError: '网络错误',
      sendFailed: '发送失败'
    },
    imageUploader: {
      uploadImage: '上传图片',
      continueAdding: '已上传{count}/{max}张',
      maxUploadHint: '最多上传{max}张，单张不超过{size}MB'
    }
  },
  en: {
    scan: {
      title: 'Scan Query',
      loading: 'Loading...',
      notFound: 'Product not found',
      loadError: 'Load failed, please try again',
      share: 'Share',
      buyLinks: 'Buy Links',
      clickToBuy: 'Click to buy',
      warrantyInfo: 'Warranty Info',
      joinServiceGroup: 'Join Service Group',
      joinServiceGroupHint: 'Scan to join the exclusive service group',
      longPressToJoin: 'Long press to scan and join',
      longPressToJoinVia: 'Long press to join via {platform}',
      loginRequired: 'Login to view',
      loginToView: 'Login Now',
      loginToApply: 'Login to Apply',
      loginExpired: 'Session expired, please login again',
      applyAfterSale: 'Apply Aftersale',
      expand: 'Expand',
      collapse: 'Collapse',
      nameOrContact: 'Name/Contact',
      namePlaceholder: 'Enter your name or contact',
      issueDesc: 'Issue Description',
      issuePlaceholder: 'Please describe your issue...',
      uploadImages: 'Upload Images',
      submitAfterSale: 'Submit Aftersale Application',
      submitting: 'Submitting...',
      submitFailed: 'Submit failed',
      networkError: 'Network error',
      linkCopied: 'Link copied',
      afterSaleSubmitted: 'Aftersale application submitted!',
      myAftersale: 'My Aftersale Records',
      fault: 'Fault',
      solution: 'Solution',
      customerService: 'Customer Service',
      statusPending: 'Processing',
      statusResolved: 'Resolved',
      statusRejected: 'Rejected',
      mine: 'My'
    },
    qrcode: {
      warrantyEnd: 'Warranty End',
      warrantyValid: 'Valid',
      warrantyExpired: 'Expired',
      afterSaleContact: 'Aftersale Contact',
      repairRecords: 'Repair Records'
    },
    product: {
      spec: 'Spec',
      groupQrTypeWechatWork: 'WeCom',
      groupQrTypeDingtalk: 'DingTalk',
      groupQrTypeTelegram: 'Telegram',
      groupQrTypeWhatsapp: 'WhatsApp',
      groupQrTypeOther: 'Other',
      platformOther: 'Other Platform'
    },
    common: {
      unbound: 'Unbound',
      category: 'Category',
      unknown: 'Unknown',
      submitting: 'Submitting...'
    },
    serviceChat: {
      title: 'Customer Service',
      noMessages: 'No messages',
      inputPlaceholder: 'Type a message...',
      fab: 'Contact Us',
      networkError: 'Network error',
      sendFailed: 'Send failed'
    },
    imageUploader: {
      uploadImage: 'Upload Image',
      continueAdding: 'Uploaded {count}/{max}',
      maxUploadHint: 'Max {max} images, {size}MB each'
    }
  }
}

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('caimeite_locale') || 'zh',
  fallbackLocale: 'zh',
  messages
})

export default i18n
