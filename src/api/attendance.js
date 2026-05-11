import request from './request'

export function getAttendanceList(params) {
  return request.get('/attendance', { params })
}

export function getAttendance(id) {
  return request.get(`/attendance/${id}`)
}

export function createAttendance(data) {
  return request.post('/attendance', data)
}

export function updateAttendance(id, data) {
  return request.put(`/attendance/${id}`, data)
}

export function deleteAttendance(id) {
  return request.delete(`/attendance/${id}`)
}
export const addAttendance = createAttendance
