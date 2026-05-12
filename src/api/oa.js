import request from './request'

// ========== Attendance ==========
export const clockIn = (data) => request.post('/oa/attendance/clock', data)
export const getMyAttendanceToday = () => request.get('/oa/attendance/my-today')
export const getAttendanceTodaySummary = () => request.get('/oa/attendance/today-summary')
export const getAttendanceList = (params) => request.get('/oa/attendance', { params })
export const getAttendanceDetail = (id) => request.get(`/oa/attendance/${id}`)
export const explainAttendance = (id, data) => request.post(`/oa/attendance/${id}/explain`, data)
export const approveAttendance = (id, data) => request.put(`/oa/attendance/${id}/approve`, data)

// ========== Auto Clock ==========
export const getAutoClockPermission = () => request.get('/oa/auto-clock/permission')
export const applyAutoClock = (data) => request.post('/oa/auto-clock/permission', data)
export const getAutoClockPermissions = (params) => request.get('/oa/auto-clock/permissions', { params })
export const approveAutoClock = (id, data) => request.put(`/oa/auto-clock/permission/${id}`, data)

// ========== Work Logs ==========
export const getWorkLogTemplates = () => request.get('/oa/work-log-templates')
export const createWorkLogTemplate = (data) => request.post('/oa/work-log-templates', data)
export const updateWorkLogTemplate = (id, data) => request.put(`/oa/work-log-templates/${id}`, data)
export const deleteWorkLogTemplate = (id) => request.delete(`/oa/work-log-templates/${id}`)
export const createWorkLog = (data) => request.post('/oa/work-logs', data)
export const getWorkLogs = (params) => request.get('/oa/work-logs', { params })
export const getWorkLogDetail = (id) => request.get(`/oa/work-logs/${id}`)
export const updateWorkLog = (id, data) => request.put(`/oa/work-logs/${id}`, data)
export const readWorkLog = (id) => request.post(`/oa/work-logs/${id}/read`)
export const commentWorkLog = (id, data) => request.post(`/oa/work-logs/${id}/comment`, data)
export const likeWorkLog = (id) => request.post(`/oa/work-logs/${id}/like`)
export const getWorkLogInteractions = (id) => request.get(`/oa/work-logs/${id}/interactions`)
export const getMyWorkLogToday = () => request.get('/oa/work-logs/my-today')
export const getWorkLogTodaySummary = () => request.get('/oa/work-logs/today-summary')

// ========== Leave ==========
export const createLeave = (data) => request.post('/oa/leave', data)
export const getLeaveList = (params) => request.get('/oa/leave', { params })
export const getLeavePending = (params) => request.get('/oa/leave/pending', { params })
export const approveLeave = (id, data) => request.put(`/oa/leave/${id}/approve`, data)
export const deleteLeave = (id) => request.delete(`/oa/leave/${id}`)

// ========== Approvals ==========
export const getApprovalTypes = () => request.get('/oa/approval-types')
export const createApproval = (data) => request.post('/oa/approvals', data)
export const getApprovals = (params) => request.get('/oa/approvals', { params })
export const getApprovalDetail = (id) => request.get(`/oa/approvals/${id}`)
export const approveApproval = (id, data) => request.post(`/oa/approvals/${id}/approve`, data)
export const rejectApproval = (id, data) => request.post(`/oa/approvals/${id}/reject`, data)
export const withdrawApproval = (id) => request.post(`/oa/approvals/${id}/withdraw`)

// ========== Shifts ==========
export const getShifts = (params) => request.get('/oa/shifts', { params })
export const createShift = (data) => request.post('/oa/shifts', data)
export const updateShift = (id, data) => request.put(`/oa/shifts/${id}`, data)

// ========== Schedules ==========
export const getSchedules = (params) => request.get('/oa/schedules', { params })
export const createSchedule = (data) => request.post('/oa/schedules', data)
export const updateSchedule = (id, data) => request.put(`/oa/schedules/${id}`, data)
export const deleteSchedule = (id) => request.delete(`/oa/schedules/${id}`, data)
export const swapSchedule = (data) => request.post('/oa/schedules/swap', data)
export const approveScheduleSwap = (id, data) => request.post(`/oa/schedules/swap/${id}/approve`, data)

// ========== Attendance Summary ==========
export const getAttendanceSummary = (params) => request.get('/oa/attendance/summary', { params })

// ========== Attendance Rules ==========
export const getAttendanceRules = () => request.get('/oa/attendance-rules')
export const createAttendanceRule = (data) => request.post('/oa/attendance-rules', data)
export const updateAttendanceRule = (id, data) => request.put(`/oa/attendance-rules/${id}`, data)
export const deleteAttendanceRule = (id) => request.delete(`/oa/attendance-rules/${id}`)

// ========== Dashboard ==========
export const getOADashboard = () => request.get('/oa/dashboard')

// ========== Departments ==========
export const getDepartments = () => request.get('/oa/departments')
export const createDepartment = (data) => request.post('/oa/departments', data)
export const updateDepartment = (id, data) => request.put(`/oa/departments/${id}`, data)
export const deleteDepartment = (id) => request.delete(`/oa/departments/${id}`)

// ========== Employees ==========
export const getEmployees = (params) => request.get('/oa/employees', { params })
export const getEmployeeDetail = (id) => request.get(`/oa/employees/${id}`)
export const getEmployeeQRCode = (code) => request.get(`/oa/employees/qrcode/${code}`)

// ========== Job Levels ==========
export const getJobLevels = () => request.get('/oa/job-levels')
