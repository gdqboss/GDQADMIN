<script setup>

import { ref, onMounted } from "vue"
import PageHeader from "../../components/PageHeader.vue"
import api from "../../services/api.js"
import { ElMessage } from "element-plus"

const loading = ref(false)
const orders = ref([])
const currentPage = ref(1)
const pageSize = ref(20)
const total = ref(0)

async function fetchOrders() {
  loading.value = true
  try {
    const res = await api.get("/collage/orders", { params: { page: currentPage.value, size: pageSize.value } })
    if (res.code === 0) {
      orders.value = res.data.list || []
      total.value = res.data.total || 0
    }
  } catch (e) { ElMessage.error(e.message) }
  finally { loading.value = false }
}

function formatPrice(p) { return p != null ? "¥ " + parseFloat(p).toFixed(2) : "¥ 0.00" }
function formatDate(d) {
  if (!d) return "-"
  return new Date(d * 1000).toLocaleString("zh-CN", { hour12: false })
}

onMounted(fetchOrders)
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <PageHeader title="拼团订单" subtitle="拼团订单管理" />
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <el-table :data="orders" stripe v-loading="loading">
        <el-table-column label="订单号" prop="ordernum" min-width="150" />
        <el-table-column label="商品" prop="proname" min-width="140" />
        <el-table-column label="联系人" prop="linkman" width="100" />
        <el-table-column label="电话" prop="tel" width="120" />
        <el-table-column label="金额" width="90" align="right">
          <template #default="{ row }"><span class="text-blue-600 font-medium">{{ formatPrice(row.money) }}</span></template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status == 0 ? 'warning' : row.status == 1 ? 'success' : 'info'" size="small">
              {{ ["待付款","已付款","已完成"][row.status] || row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="时间" min-width="150">
          <template #default="{ row }">{{ formatDate(row.createtime) }}</template>
        </el-table-column>
      </el-table>
      <div class="p-4 flex justify-end">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10,20,50]"
          layout="total,sizes,prev,pager,next"
          @current-change="fetchOrders"
          @size-change="fetchOrders"
        />
      </div>
    </div>
  </div>
</template>
