export function parsePagination(query) {
  let page = parseInt(query.page, 10)
  let size = parseInt(query.size, 10)
  if (!Number.isFinite(page) || page < 1) page = 1
  if (!Number.isFinite(size) || size < 1) size = 20
  if (size > 100) size = 100
  return { page, size }
}
