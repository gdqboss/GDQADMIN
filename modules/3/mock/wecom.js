export const wecomConversations = [
  { id: 1, name: '张姐', avatar: '', type: 'single', lastMessage: '那批鼠标什么时候到货？', lastTime: '10:32', unread: 2 },
  { id: 2, name: '供应商-深圳科创', avatar: '', type: 'single', lastMessage: '报价单已发，请查收', lastTime: '09:15', unread: 0 },
  { id: 3, name: '仓储部群', avatar: '', type: 'group', lastMessage: '李秀芳: 义乌分仓盘点完成', lastTime: '昨天', unread: 5 },
  { id: 4, name: '刘总', avatar: '', type: 'single', lastMessage: '本月销售报表发我一下', lastTime: '昨天', unread: 1 },
  { id: 5, name: '采购审批群', avatar: '', type: 'group', lastMessage: '王建国: USB-C数据线补货申请已提交', lastTime: '02-19', unread: 0 },
]

export const wecomMessages = {
  1: [
    { id: 1, sender: '张姐', isSelf: false, content: 'SKU-001 无线鼠标库存还有多少？', time: '10:28' },
    { id: 2, sender: '我', isSelf: true, content: '当前库存5个，低于安全线50，已经在走补货流程了', time: '10:30' },
    { id: 3, sender: '张姐', isSelf: false, content: '那批鼠标什么时候到货？', time: '10:32' },
  ],
  2: [
    { id: 1, sender: '供应商-深圳科创', isSelf: false, content: '您好，上次询价的USB-C扩展坞有货了', time: '09:00' },
    { id: 2, sender: '我', isSelf: true, content: '好的，麻烦发一下最新报价单', time: '09:10' },
    { id: 3, sender: '供应商-深圳科创', isSelf: false, content: '报价单已发，请查收', time: '09:15' },
  ],
  3: [
    { id: 1, sender: '王建国', isSelf: false, content: '义乌分仓今天开始盘点', time: '08:30' },
    { id: 2, sender: '我', isSelf: true, content: '收到，注意核对SKU-003和SKU-005的数量', time: '08:45' },
    { id: 3, sender: '李秀芳', isSelf: false, content: '义乌分仓盘点完成', time: '17:20' },
  ],
  4: [
    { id: 1, sender: '刘总', isSelf: false, content: '本月销售报表发我一下', time: '14:00' },
  ],
  5: [
    { id: 1, sender: '王建国', isSelf: false, content: 'USB-C数据线库存不足，需要补货200条', time: '11:00' },
    { id: 2, sender: '我', isSelf: true, content: '已确认，提交补货申请', time: '11:15' },
    { id: 3, sender: '王建国', isSelf: false, content: 'USB-C数据线补货申请已提交', time: '11:20' },
  ],
}
