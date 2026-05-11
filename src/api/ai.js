import request from './request'

export function getAIList(params) {
  return request.get('/ai', { params })
}

export function getAI(id) {
  return request.get(`/ai/${id}`)
}

export function createAI(data) {
  return request.post('/ai', data)
}

export function updateAI(id, data) {
  return request.put(`/ai/${id}`, data)
}

export function deleteAI(id) {
  return request.delete(`/ai/${id}`)
}
export const getAiResponseList = getAIList
