/**
 * Middleware: detect ?lang=en query param and set req.lang
 * Also accepts header X-Lang: en
 */
export function langMiddleware(req, res, next) {
  req.lang = req.query.lang || req.headers['x-lang'] || 'zh'
  next()
}
