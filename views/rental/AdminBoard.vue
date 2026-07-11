<template>
  <div class="rental-admin">
    <header class="topbar">
      <h2>📊 租赁报价看板</h2>
      <div class="actions">
        <select v-model="filter.customer_type" @change="loadList">
          <option value="">全部客户类型</option>
          <option value="gov">政府</option>
          <option value="biz">个人</option>
          <option value="peer">同行</option>
        </select>
        <select v-model="filter.status" @change="loadList">
          <option value="">全部状态</option>
          <option value="pending_approval">待报价</option>
          <option value="quoted">已报价</option>
          <option value="confirmed">已确认</option>
          <option value="signed">已签约</option>
          <option value="completed">已完工</option>
          <option value="archived">已归档</option>
          <option value="cancelled">已取消</option>
        </select>
        <a :href="excelUrl" download class="btn-excel">📥 导出 Excel</a>
      </div>
    </header>

    <table class="quote-table">
      <thead>
        <tr>
          <th>订单号</th><th>客户</th><th>类型</th><th>活动</th>
          <th>金额</th><th>状态</th><th>下单时间</th><th>操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in list" :key="row.id">
          <td><a @click.prevent="openDetail(row)">{{ row.order_no }}</a></td>
          <td>{{ row.customer_name }}<br><small>{{ row.customer_phone }}</small></td>
          <td><span :class="['type-tag', row.customer_type]">{{ customerTypeLabel(row.customer_type) }}</span></td>
          <td><small>{{ row.activity_location || '-' }}</small></td>
          <td><b style="color:#c00">¥{{ Number(row.pre_tax_total || 0).toFixed(2) }}</b></td>
          <td><span :class="['status-tag', row.status]">{{ statusLabel(row.status) }}</span></td>
          <td><small>{{ row.created_at?.slice(0,16) }}</small></td>
          <td>
            <button @click="openDetail(row)">详情</button>
            <button @click="genQuote(row, 'gov')">政府报价</button>
            <button @click="genQuote(row, 'biz')">商业报价</button>
            <button @click="genQuote(row, 'peer')">同行报价</button>
          </td>
        </tr>
        <tr v-if="!list.length"><td colspan="8" class="empty">暂无询价单</td></tr>
      </tbody>
    </table>

    <!-- 详情抽屉 -->
    <div v-if="detail" class="drawer-mask" @click.self="detail=null">
      <div class="drawer">
        <header>
          <h3>询价单 {{ detail.order_no }}</h3>
          <button @click="detail=null">✕</button>
        </header>
        <section class="info-grid">
          <div><label>客户</label>{{ detail.customer_name }} ({{ detail.customer_phone }})</div>
          <div><label>类型</label><span :class="['type-tag', detail.customer_type]">{{ customerTypeLabel(detail.customer_type) }}</span></div>
          <div><label>活动</label>{{ detail.activity_type }} · {{ detail.activity_location }}</div>
          <div><label>时间</label>{{ detail.activity_time_start?.slice(0,16) }} ~ {{ detail.activity_time_end?.slice(0,16) }}</div>
          <div><label>小计</label>¥{{ Number(detail.subtotal || 0).toFixed(2) }}</div>
          <div><label>折扣</label>{{ detail.discount_rate }} (-¥{{ Number(detail.discount_amount || 0).toFixed(2) }})</div>
          <div><label>税前</label>¥{{ Number(detail.pre_tax_total || 0).toFixed(2) }}</div>
          <div><label>状态</label><span :class="['status-tag', detail.status]">{{ statusLabel(detail.status) }}</span></div>
        </section>

        <h4>📋 物料清单</h4>
        <table class="items-table">
          <thead><tr><th>序号</th><th>物料</th><th>数量</th><th>天数</th><th>单价</th><th>小计</th></tr></thead>
          <tbody>
            <tr v-for="(it, i) in detail.items" :key="it.id">
              <td>{{ i + 1 }}</td>
              <td>{{ it.product_name }}</td>
              <td>{{ it.qty }}</td>
              <td>{{ it.days }}</td>
              <td>¥{{ Number(it.unit_price).toFixed(2) }}</td>
              <td>¥{{ Number(it.subtotal).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- 操作按钮 -->
        <div class="action-buttons">
          <button v-if="['draft','pending_approval'].includes(detail.status)" @click="audit(true)">✅ 批准</button>
          <button v-if="['draft','pending_approval'].includes(detail.status)" @click="audit(false)">❌ 驳回</button>
          <button @click="applyDiscount">💰 应用折扣</button>
          <button @click="advanceStatus">⏭️ 推进状态</button>
          <button @click="cancel" class="danger">取消订单</button>
        </div>

        <!-- 折扣输入 -->
        <div v-if="showDiscount" class="discount-form">
          <label>折扣率 (0-1) <input v-model.number="discountRate" type="number" step="0.05" min="0" max="1" /></label>
          <input v-model="discountNote" placeholder="备注(可选)" />
          <button @click="confirmDiscount" class="primary">确认</button>
          <button @click="showDiscount=false">取消</button>
        </div>

        <!-- 审计日志 -->
        <h4>📜 审计日志</h4>
        <ul class="audit-list">
          <li v-for="l in detail.logs" :key="l.id">
            <span class="action">{{ l.action }}</span>
            <span class="msg">{{ l.diff_summary }}</span>
            <span class="time">{{ l.created_at?.slice(0,19) }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const filter = ref({ customer_type: '', status: '' })
const list = ref([])
const detail = ref(null)
const showDiscount = ref(false)
const discountRate = ref(0.9)
const discountNote = ref('')

onMounted(() => loadList())

const excelUrl = computed(() => {
  const p = new URLSearchParams()
  if (filter.value.customer_type) p.set('customer_type', filter.value.customer_type)
  if (filter.value.status) p.set('status', filter.value.status)
  const tok = localStorage.getItem('caimeite_token')
  return `/api/rental/export-excel?${p.toString()}&token=${tok}`
})

async function loadList () {
  const r = await axios.get('/api/rental/inquiries', { params: filter.value })
  list.value = r.data?.data?.list || []
}

async function openDetail (row) {
  const r = await axios.get(`/api/rental/detail/${row.id}`)
  detail.value = r.data?.data
}

function customerTypeLabel (t) { return { gov: '政府', biz: '个人', peer: '同行' }[t] || t }
function statusLabel (s) {
  const m = { draft: '草稿', pending_approval: '待报价', quoted: '已报价', confirmed: '已确认', signed: '已签约', completed: '已完工', archived: '已归档', cancelled: '已取消' }
  return m[s] || s
}

async function audit (ok) {
  const r = await axios.post(`/api/rental/${detail.value.id}/audit`, { approve: ok, note: '' })
  if (r.data?.ok) { detail.value = null; loadList() } else alert(r.data?.error)
}
async function applyDiscount () { showDiscount.value = true }
async function confirmDiscount () {
  const r = await axios.post(`/api/rental/${detail.value.id}/apply-discount`, { discount_rate: discountRate.value, note: discountNote.value })
  if (r.data?.ok) { alert(`✅ 已应用折扣 -¥${r.data.data.discount_amount}`); showDiscount.value = false; openDetail(detail.value) }
  else alert(r.data?.error)
}
async function advanceStatus () {
  const flow = { draft: 'pending_approval', pending_approval: 'quoted', quoted: 'confirmed', confirmed: 'signed', signed: 'completed', completed: 'archived' }
  const next = flow[detail.value.status]
  if (!next) return alert('已是最终状态')
  const r = await axios.put(`/api/rental/${detail.value.id}/status`, { status: next })
  if (r.data?.ok) { openDetail(detail.value); loadList() } else alert(r.data?.error)
}
async function cancel () {
  if (!confirm('确认取消订单?')) return
  const r = await axios.post(`/api/rental/${detail.value.id}/cancel`, {})
  if (r.data?.ok) { detail.value = null; loadList() } else alert(r.data?.error)
}
function genQuote (row, template) {
  window.open(`/api/rental/${row.id}/quote-html?template=${template}`, '_blank')
}
</script>

<style scoped>
.rental-admin { padding: 16px; }
.topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.topbar h2 { margin: 0; font-size: 18px; }
.actions { display: flex; gap: 8px; align-items: center; }
.actions select { padding: 6px 10px; border-radius: 4px; border: 1px solid #ddd; }
.btn-excel { padding: 6px 14px; background: #5cb85c; color: #fff; border-radius: 4px; text-decoration: none; font-size: 13px; }
.quote-table { width: 100%; border-collapse: collapse; background: #fff; }
.quote-table th, .quote-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 13px; text-align: left; }
.quote-table th { background: #f5f5f5; font-weight: 600; }
.quote-table button { padding: 4px 10px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 4px; }
.quote-table button:hover { background: #f5f5f5; }
.type-tag { padding: 2px 8px; border-radius: 3px; font-size: 11px; }
.type-tag.gov { background: #ffe0e0; color: #c00; }
.type-tag.biz { background: #e0f0ff; color: #0066cc; }
.type-tag.peer { background: #f0f0e0; color: #888800; }
.status-tag { padding: 2px 8px; border-radius: 3px; font-size: 11px; background: #f0f0f0; }
.status-tag.completed, .status-tag.archived { background: #e0ffe0; color: #060; }
.status-tag.cancelled { background: #ffe0e0; color: #c00; }
.empty { text-align: center; padding: 40px; color: #999; }
.drawer-mask { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; justify-content: flex-end; z-index: 100; }
.drawer { width: 600px; max-width: 100%; background: #fff; padding: 24px; overflow-y: auto; }
.drawer header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 16px; }
.drawer header button { background: none; border: none; font-size: 18px; cursor: pointer; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.info-grid div label { display: block; font-size: 11px; color: #888; margin-bottom: 2px; }
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.items-table th, .items-table td { padding: 6px 10px; border: 1px solid #eee; font-size: 13px; }
.items-table th { background: #f5f5f5; }
.action-buttons { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.action-buttons button { padding: 8px 16px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
.action-buttons .danger { color: #c00; border-color: #c00; }
.discount-form { background: #fff8e1; padding: 12px; border-radius: 4px; margin-bottom: 20px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.discount-form input { padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; }
.discount-form button { padding: 6px 14px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
.discount-form button.primary { background: #c00; color: #fff; border-color: #c00; }
.audit-list { list-style: none; padding: 0; margin: 0; }
.audit-list li { padding: 6px 0; border-bottom: 1px dashed #eee; font-size: 13px; display: flex; gap: 10px; }
.audit-list .action { background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
.audit-list .time { color: #999; font-size: 11px; margin-left: auto; }
</style>
