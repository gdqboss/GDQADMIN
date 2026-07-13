<!--
  UiTable.vue — UI 抽象层 (0.6 铁律)
  严禁 view 直接写 <el-table>, 必须走 <UiTable :data="rows" :columns="cols">
  列定义通过 columns prop 传入, 不在 view 里散写 <el-table-column>

  用法:
  <UiTable :data="list" :columns="[
    { prop: 'name', label: '名称', width: 120 },
    { prop: 'price', label: '价格', formatter: r => '¥' + r.price },
    { label: '操作', actions: [
      { label: '编辑', onClick: row => edit(row) },
      { label: '删除', variant: 'danger', onClick: row => del(row) }
    ]}
  ]" />

  Props:
  - data: 表格数据数组
  - columns: 列定义数组 (见上方用法)
  - loading / border / stripe / size / height / maxHeight
  - selection: 是否显示多选列
  - pagination: { page, pageSize, total, onChange } 不传 = 客户端分页
-->
<template>
  <div class="ui-table-wrapper">
    <el-table
      v-loading="loading"
      :data="pagedData"
      :border="border"
      :stripe="stripe"
      :size="size"
      :height="height"
      :max-height="maxHeight"
      :empty-text="emptyText"
      @selection-change="onSelectionChange"
    >
      <el-table-column v-if="selection" type="selection" width="48" />
      <el-table-column
        v-for="(col, idx) in normalizedColumns"
        :key="idx"
        :prop="col.prop"
        :label="col.label"
        :width="col.width"
        :min-width="col.minWidth"
        :align="col.align || 'left'"
        :fixed="col.fixed"
        :sortable="col.sortable"
        :show-overflow-tooltip="col.tooltip !== false"
      >
        <template #default="{ row }">
          <span v-if="col.formatter">{{ col.formatter(row) }}</span>
          <span v-else-if="col.tag">
            <el-tag :type="col.tagType ? col.tagType(row) : 'default'" size="small">
              {{ col.tag(row) }}
            </el-tag>
          </span>
          <span v-else-if="col.actions">
            <UiButton
              v-for="(act, ai) in col.actions"
              :key="ai"
              :variant="act.variant || 'default'"
              :size="act.size || 'small'"
              :disabled="act.disabled && act.disabled(row)"
              :text="act.text !== false"
              @click="act.onClick(row)"
            >{{ act.label }}</UiButton>
          </span>
          <span v-else>{{ row[col.prop] }}</span>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-if="pagination"
      :current-page="pagination.page"
      :page-size="pagination.pageSize"
      :total="pagination.total"
      :page-sizes="[10, 20, 50, 100]"
      layout="total, sizes, prev, pager, next, jumper"
      class="ui-table-pagination"
      @current-change="p => pagination.onChange && pagination.onChange(p)"
      @size-change="s => pagination.onSizeChange && pagination.onSizeChange(s)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ElTable, ElTableColumn, ElPagination, ElTag } from 'element-plus'
import UiButton from './UiButton.vue'

const props = defineProps({
  data: { type: Array, required: true },
  columns: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  border: { type: Boolean, default: true },
  stripe: { type: Boolean, default: false },
  size: { type: String, default: 'default' },
  height: { type: [String, Number], default: null },
  maxHeight: { type: [String, Number], default: null },
  emptyText: { type: String, default: '暂无数据' },
  selection: { type: Boolean, default: false },
  pagination: { type: Object, default: null }, // { page, pageSize, total, onChange, onSizeChange }
})

const emit = defineEmits(['selection-change'])

const normalizedColumns = computed(() => props.columns)
const pagedData = computed(() => {
  if (!props.pagination) return props.data
  const { page, pageSize } = props.pagination
  return props.data.slice((page - 1) * pageSize, page * pageSize)
})

function onSelectionChange(rows) {
  emit('selection-change', rows)
}
</script>

<style scoped>
.ui-table-wrapper { width: 100%; }
.ui-table-pagination { margin-top: 16px; justify-content: flex-end; display: flex; }
</style>
