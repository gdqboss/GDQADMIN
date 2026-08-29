// ESM module - matches /home/gdq/server (package.json type=module)

const SERVER_PROFILE_FALLBACK_DEFAULTS = [
  { id: 1, name: 'Singapore Dev', endpoints: [
    { endpoint_type: 'h5',            label: 'H5 Client',      url: 'https://wecom.gdqshop.cn',        is_primary: 1, sort_order: 1 },
    { endpoint_type: 'admin_backend', label: 'Admin Backend',  url: 'https://wecom.gdqshop.cn',        is_primary: 1, sort_order: 2 },
    { endpoint_type: 'api_backend',   label: 'Backend API',    url: 'https://wecom.gdqshop.cn/api',    is_primary: 1, sort_order: 3 },
    { endpoint_type: 'minip_frontend',label: 'Minip Frontend', url: 'https://wecom.gdqshop.cn/minip/', is_primary: 1, sort_order: 4 }
  ]},
  { id: 2, name: 'Beijing Caimeite', endpoints: [
    { endpoint_type: 'h5',            label: 'H5 Client',      url: 'https://claw.gdqshop.cn',         is_primary: 1, sort_order: 1 },
    { endpoint_type: 'admin_backend', label: 'Admin Backend',  url: 'https://claw.gdqshop.cn',         is_primary: 1, sort_order: 2 },
    { endpoint_type: 'api_backend',   label: 'Backend API',    url: 'https://claw.gdqshop.cn/api',     is_primary: 1, sort_order: 3 }
  ]},
  { id: 3, name: 'Warehouse 3', endpoints: [
    { endpoint_type: 'h5',            label: 'H5 Client',      url: 'https://www.mywh3.com',           is_primary: 1, sort_order: 1 },
    { endpoint_type: 'admin_backend', label: 'Admin Backend',  url: 'https://www.mywh3.com',           is_primary: 1, sort_order: 2 },
    { endpoint_type: 'api_backend',   label: 'Backend API',    url: 'https://www.mywh3.com/api',       is_primary: 1, sort_order: 3 }
  ]},
  { id: 4, name: 'Shanghai Plaza', endpoints: [
    { endpoint_type: 'h5',            label: 'H5 Client',      url: 'https://gdqshop.cn',              is_primary: 1, sort_order: 1 },
    { endpoint_type: 'admin_backend', label: 'Admin Backend',  url: 'https://gdqshop.cn',              is_primary: 1, sort_order: 2 },
    { endpoint_type: 'api_backend',   label: 'Backend API',    url: 'https://gdqshop.cn/api',          is_primary: 1, sort_order: 3 }
  ]}
];

export async function buildEndpointsForServer(pool, serverProfileId) {
  if (!serverProfileId) return [];
  try {
    const [rows] = await pool.query(
      'SELECT id, endpoint_type, label, url, is_primary, env, sort_order, description, extra ' +
      'FROM server_endpoints WHERE server_profile_id = ? AND is_active = 1 ' +
      'ORDER BY sort_order ASC, id ASC',
      [serverProfileId]
    );
    if (rows && rows.length) {
      return rows.map(r => {
        let extra = null;
        try { extra = r.extra ? JSON.parse(r.extra) : null; } catch (_) { extra = null; }
        return {
          id: r.id, type: r.endpoint_type, label: r.label, url: r.url,
          is_primary: !!r.is_primary, env: r.env, sort_order: r.sort_order,
          description: r.description || '', extra
        };
      });
    }
  } catch (e) { /* silent fallback */ }
  const fb = SERVER_PROFILE_FALLBACK_DEFAULTS.find(s => s.id === serverProfileId);
  if (!fb) return [];
  return fb.endpoints.map((e, idx) => ({
    id: 'fallback-' + serverProfileId + '-' + idx,
    type: e.endpoint_type, label: e.label, url: e.url,
    is_primary: !!e.is_primary, env: 'production',
    sort_order: e.sort_order, description: '', extra: null
  }));
}