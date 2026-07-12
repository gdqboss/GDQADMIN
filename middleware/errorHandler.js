export function errorHandler(err, req, res, _next) {
  console.error(err)
  const status = err.status || 500
  const message = process.env.NODE_ENV === 'production'
    ? '服务器内部错误'
    : (err.message || '服务器内部错误')
  res.status(status).json({ code: status, message })
}
