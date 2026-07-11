<template>
  <div class="rental-client">
    <!-- 客户类型选择 -->
    <div class="customer-type-bar">
      <label :class="{active: customerType==='gov'}" @click="customerType='gov'">🏛️ 政府</label>
      <label :class="{active: customerType==='biz'}" @click="customerType='biz'">👤 个人</label>
      <label :class="{active: customerType==='peer'}" @click="customerType='peer'">🤝 同行</label>
    </div>

    <!-- 设备分类 Tab -->
    <div class="category-tabs">
      <span
        v-for="c in categories" :key="c.key"
        :class="{active: currentCat===c.key}"
        @click="currentCat = c.key"
      >{{ c.label }}</span>
    </div>

    <!-- 设备列表 -->
    <div class="product-grid">
      <div v-for="p in filteredProducts" :key="p.id" class="product-card">
        <img v-if="p.image_main" :src="p.image_main" class="thumb" @error="$event.target.style.display='none'">
        <div v-else class="thumb placeholder">📦</div>
        <div class="info">
          <div class="name">{{ p.name }}</div>
          <div class="meta">
            <span class="cat-tag">{{ p.category }}</span>
            <span class="stock">剩余 {{ p.available_qty }} 件</span>
          </div>
          <div class="price-line">
            <span class="price">¥{{ Number(p.effective_price || 0).toFixed(0) }}</span>
            <span class="unit">/{{ p.unit || '件' }}/天</span>
          </div>
          <div class="qty-bar">
            <button @click="decQty(p.id)">-</button>
            <input v-model.number="qtyMap[p.id]" type="number" min="0" />
            <button @click="incQty(p.id)">+</button>
            <button class="add-btn" @click="addToCart(p)">加入询价单</button>
          </div>
        </div>
      </div>
      <div v-if="!filteredProducts.length" class="empty">该分类暂无设备</div>
    </div>

    <!-- 底部询价单 -->
    <transition name="slide-up">
      <div v-if="cart.length" class="cart-bar">
        <div class="cart-summary">
          <span>询价单 {{ cart.length }} 项 / 共 ¥{{ subtotal.toFixed(2) }}</span>
          <button class="submit" :disabled="busy" @click="submitInquiry">📤 一键询价</button>
        </div>
      </div>
    </transition>

    <!-- 询价表单弹窗 -->
    <div v-if="showForm" class="modal-mask" @click.self="showForm=false">
      <div class="modal">
        <h3>📋 填写活动信息</h3>
        <input v-model="form.customer_name" placeholder="客户姓名 / 单位" />
        <input v-model="form.customer_phone" placeholder="联系电话" />
        <select v-model="form.activity_type">
          <option value="gov_event">政府活动</option>
          <option value="biz_event">商业活动</option>
          <option value="wedding">婚礼</option>
          <option value="party">晚会</option>
          <option value="exhibition">展会</option>
        </select>
        <input v-model="form.activity_time_start" type="datetime-local" placeholder="活动时间起" />
        <input v-model="form.activity_time_end" type="datetime-local" placeholder="活动时间止" />
        <input v-model="form.setup_time" type="datetime-local" placeholder="搭建时间" />
        <input v-model="form.teardown_time" type="datetime-local" placeholder="撤场时间" />
        <input v-model="form.activity_location" placeholder="活动地点" />
        <textarea v-model="form.remark" placeholder="备注需求"></textarea>
        <div class="actions">
          <button @click="showForm=false">取消</button>
          <button class="primary" :disabled="busy" @click="confirmSubmit">提交询价</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const customerType = ref('biz')
const currentCat = ref('all')
const products = ref([])
const cart = ref([])
const qtyMap = ref({})
const showForm = ref(false)
const busy = ref(false)

const categories = [
  { key: 'all', label: '全部' },
  { key: '灯光设备', label: '灯光' },
  { key: '音响设备', label: '音响' },
  { key: 'LED屏幕', label: 'LED' },
  { key: '舞台搭建', label: '搭建' },
  { key: '活动配套', label: '配套' },
  { key: '人员服务', label: '人员' },
  { key: '演艺节目', label: '演艺' }
]

const form = ref({
  customer_name: '', customer_phone: '',
  activity_type: 'biz_event',
  activity_time_start: '', activity_time_end: '',
  setup_time: '', teardown_time: '',
  activity_location: '', remark: ''
})

onMounted(() => loadProducts())

watch(customerType, () => loadProducts())

async function loadProducts () {
  try {
    const r = await axios.get('/api/rental-public/products', { params: { customer_type: customerType.value } })
    products.value = r.data?.data || []
  } catch (e) { console.error(e) }
}

const filteredProducts = computed(() => {
  if (currentCat.value === 'all') return products.value
  return products.value.filter(p => p.category === currentCat.value)
})

function incQty (id) { qtyMap.value[id] = (qtyMap.value[id] || 0) + 1 }
function decQty (id) { qtyMap.value[id] = Math.max(0, (qtyMap.value[id] || 0) - 1) }

function addToCart (p) {
  const qty = qtyMap.value[p.id] || 1
  const existing = cart.value.find(c => c.product_id === p.id)
  if (existing) existing.qty += qty
  else cart.value.push({
    product_id: p.id, product_name: p.name,
    category: p.category, qty, days: 1,
    unit_price: Number(p.effective_price || 0)
  })
  qtyMap.value[p.id] = 0
}

const subtotal = computed(() => cart.value.reduce((s, c) => s + c.qty * c.days * Number(c.unit_price || 0), 0))

function submitInquiry () { showForm.value = true }

async function confirmSubmit () {
  if (!form.value.customer_name || !form.value.customer_phone) { alert('请填姓名和电话'); return }
  if (!form.value.activity_time_start || !form.value.activity_time_end) { alert('请填活动时间'); return }
  busy.value = true
  try {
    const r = await axios.post('/api/rental-public/inquiry', {
      ...form.value,
      customer_type: customerType.value,
      items: cart.value
    })
    if (r.data?.ok) {
      alert(`✅ 询价单已提交 (订单号 ${r.data.data.order_no})\n共 ¥${r.data.data.subtotal.toFixed(2)},后台将尽快联系您`)
      cart.value = []; showForm.value = false
    } else { alert(r.data?.error || '提交失败') }
  } catch (e) { alert(e.message) }
  busy.value = false
}
</script>

<style scoped>
.rental-client { padding: 12px; padding-bottom: 80px; }
.customer-type-bar { display: flex; gap: 8px; margin-bottom: 12px; background: #f5f5f5; padding: 6px; border-radius: 8px; }
.customer-type-bar label { flex: 1; text-align: center; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 14px; }
.customer-type-bar label.active { background: #c00; color: #fff; }
.category-tabs { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 12px; padding-bottom: 4px; }
.category-tabs span { padding: 6px 14px; background: #f0f0f0; border-radius: 16px; font-size: 13px; cursor: pointer; white-space: nowrap; }
.category-tabs span.active { background: #c00; color: #fff; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
.product-card { background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
.thumb { width: 100%; height: 120px; object-fit: cover; }
.placeholder { display: flex; align-items: center; justify-content: center; background: #f5f5f5; font-size: 40px; }
.info { padding: 8px; }
.name { font-weight: 600; font-size: 13px; margin-bottom: 4px; height: 36px; overflow: hidden; }
.meta { display: flex; gap: 6px; font-size: 11px; color: #888; margin-bottom: 4px; }
.cat-tag { background: #f0f0f0; padding: 1px 6px; border-radius: 3px; }
.price-line { margin: 6px 0; }
.price { color: #c00; font-size: 18px; font-weight: bold; }
.unit { font-size: 11px; color: #888; }
.qty-bar { display: flex; gap: 4px; align-items: center; margin-top: 6px; }
.qty-bar button { padding: 4px 8px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
.qty-bar input { width: 36px; text-align: center; border: 1px solid #ddd; border-radius: 4px; padding: 2px; }
.add-btn { background: #c00; color: #fff; border: none; flex: 1; font-size: 12px; }
.empty { text-align: center; padding: 40px; color: #999; }
.cart-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; box-shadow: 0 -2px 8px rgba(0,0,0,.1); padding: 12px; }
.cart-summary { display: flex; justify-content: space-between; align-items: center; }
.cart-summary .submit { background: #c00; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; }
.cart-summary .submit:disabled { opacity: .5; }
.modal-mask { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 100; padding: 20px; }
.modal { background: #fff; border-radius: 8px; padding: 20px; width: 100%; max-width: 420px; max-height: 90vh; overflow: auto; }
.modal h3 { margin: 0 0 12px; }
.modal input, .modal select, .modal textarea { width: 100%; padding: 8px; margin-bottom: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
.modal textarea { min-height: 60px; }
.actions { display: flex; gap: 8px; }
.actions button { flex: 1; padding: 10px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
.actions .primary { background: #c00; color: #fff; border-color: #c00; }
.slide-up-enter-active, .slide-up-leave-active { transition: all .3s; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
