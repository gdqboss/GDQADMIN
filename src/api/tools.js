import request from './request'

export function getToolsList(params) {
  return request.get('/tools', { params })
}

export function getTool(id) {
  return request.get(`/tools/${id}`)
}

export function createTool(data) {
  return request.post('/tools', data)
}

export function updateTool(id, data) {
  return request.put(`/tools/${id}`, data)
}

export function deleteTool(id) {
  return request.delete(`/tools/${id}`)
}

export const addTool = createTool

// auto stubs
export const getToolList = getToolsList
