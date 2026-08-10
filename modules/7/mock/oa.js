// ===== 考勤数据 =====
export const attendanceRecords = [
  { id: 1, user: '张姐', date: '2026-02-20', clockIn: '08:55', clockOut: '18:10', status: 'normal', location: '深圳总仓' },
  { id: 2, user: '王建国', date: '2026-02-20', clockIn: '09:12', clockOut: '18:05', status: 'late', location: '深圳总仓' },
  { id: 3, user: '小李', date: '2026-02-20', clockIn: '08:48', clockOut: '17:30', status: 'early', location: '深圳总仓' },
  { id: 4, user: '李秀芳', date: '2026-02-20', clockIn: '08:30', clockOut: '18:00', status: 'normal', location: '义乌分仓' },
  { id: 5, user: 'Jack Chen', date: '2026-02-20', clockIn: null, clockOut: null, status: 'absent', location: '' },
  { id: 6, user: '张姐', date: '2026-02-19', clockIn: '08:50', clockOut: '18:20', status: 'normal', location: '深圳总仓' },
  { id: 7, user: '王建国', date: '2026-02-19', clockIn: '08:58', clockOut: '18:00', status: 'normal', location: '深圳总仓' },
  { id: 8, user: '小李', date: '2026-02-19', clockIn: '09:05', clockOut: '18:30', status: 'late', location: '外勤-客户拜访' },
]

export const leaveRecords = [
  { id: 1, user: '小李', type: '年假', startDate: '2026-02-24', endDate: '2026-02-25', days: 2, reason: '个人事务', status: 'pending', approver: '张姐' },
  { id: 2, user: 'Jack Chen', type: '病假', startDate: '2026-02-20', endDate: '2026-02-20', days: 1, reason: '身体不适', status: 'approved', approver: '刘总' },
  { id: 3, user: '王建国', type: '调休', startDate: '2026-02-17', endDate: '2026-02-17', days: 1, reason: '上周加班调休', status: 'approved', approver: '张姐' },
  { id: 4, user: '李秀芳', type: '事假', startDate: '2026-02-14', endDate: '2026-02-14', days: 1, reason: '家中有事', status: 'rejected', approver: '张姐' },
]

// ===== 工作日志 =====
export const workLogs = [
  {
    id: 1, user: '张姐', date: '2026-02-20', reportTo: '刘总',
    todayWork: '1. 完成本月采购计划初稿\n2. 与深圳科创确认 USB-C 扩展坞报价\n3. 处理 3 条库存预警，已提交补货申请',
    tomorrowPlan: '1. 跟进义乌分仓盘点结果\n2. 审核王建国提交的补货申请',
    issues: '国际站采集数据异常，需要技术排查',
  },
  {
    id: 2, user: '王建国', date: '2026-02-20', reportTo: '张姐',
    todayWork: '1. 深圳总仓入库 RK-20260220-001（无线鼠标 200 件）\n2. 处理出库单 CK-20260220-003\n3. 核对 SKU-003 和 SKU-005 库存',
    tomorrowPlan: '1. 继续处理待入库订单\n2. 配合义乌分仓盘点',
    issues: '',
  },
  {
    id: 3, user: '小李', date: '2026-02-20', reportTo: '张姐',
    todayWork: '1. 拜访客户-华南电子，确认下月订单意向\n2. 处理 2 笔出库申请\n3. 更新客户报价单',
    tomorrowPlan: '1. 跟进华南电子订单确认\n2. 整理本周销售数据',
    issues: 'SKU-001 无线鼠标库存不足，客户催货',
  },
  {
    id: 4, user: '张姐', date: '2026-02-19', reportTo: '刘总',
    todayWork: '1. 审批通过 2 笔采购申请\n2. 与供应商谈判 LED 台灯降价\n3. 整理本周采购汇总',
    tomorrowPlan: '1. 制定本月采购计划\n2. 确认 USB-C 扩展坞报价',
    issues: '',
  },
]
