<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api.js'
import { ElMessage } from 'element-plus'

// 2026-08-23 江小鱼 — 寺内容管理 admin 后端骨架页面
// 来源: 波哥指示"admin 加寺内容管理菜单 (牌位/历史/僧侣/居士楼等)"
// 当前是占位页: 列出 13 个寺相关 DB 表 + 提示后续每个表加 CRUD UI
//
// 涉及表 (SGP DB `gdq`):
//   temple_ancestors          牌位/祖先信息
//   temple_monks               僧侣
//   temple_monk_schedules      僧侣排班
//   temple_sutras              经文
//   temple_sutra_lines         经文行
//   temple_activities          活动
//   temple_memorial_events     纪念法会
//   temple_donations           供奉
//   temple_galleries           相册
//   temple_intros              介绍
//   temple_cinerary_caskets    灵柩
//   temple_edit_requests       编辑请求
//   temple_scan_logs           扫码日志
//
// 当前步骤: 仅占位入口 + 表清单, 后续按波哥指示逐表加 CRUD UI

const tables = [
  { key: 'ancestors',     label: '牌位 / 祖先',     route: '/admin/temple/ancestors',    desc: '信徒祖先牌位登记, 供奉关系' },
  { key: 'monks',         label: '僧侣',             route: '/admin/temple/monks',        desc: '寺院僧侣资料, 法号, 职位' },
  { key: 'monk_schedules',label: '僧侣排班',         route: '/admin/temple/monk-schedules', desc: '僧侣值日 / 值班安排' },
  { key: 'sutras',        label: '经文',             route: '/admin/temple/sutras',       desc: '经文标题 / 分类' },
  { key: 'sutra_lines',   label: '经文行',           route: '/admin/temple/sutra-lines',  desc: '经文具体行 / 段落' },
  { key: 'activities',    label: '活动',             route: '/admin/temple/activities',   desc: '寺院活动公告 / 法会安排' },
  { key: 'memorial_events', label: '纪念法会',       route: '/admin/temple/memorial-events', desc: '追思 / 纪念法会' },
  { key: 'donations',     label: '供奉',             route: '/admin/temple/donations',    desc: '供奉记录 / 香油钱' },
  { key: 'galleries',     label: '相册',             route: '/admin/temple/galleries',    desc: '寺院相册图集' },
  { key: 'intros',        label: '寺院介绍',         route: '/admin/temple/intros',       desc: '寺院介绍 / 历史沿革' },
  { key: 'cinerary',      label: '灵柩',             route: '/admin/temple/cinerary',     desc: '灵柩寄存 / 寄存记录' },
  { key: 'edit_requests', label: '编辑请求',         route: '/admin/temple/edit-requests', desc: '客户前端编辑请求审核' },
  { key: 'scan_logs',     label: '扫码日志',         route: '/admin/temple/scan-logs',    desc: '牌位扫码访问记录' },
]

const stats = ref({})
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  // 后续: 用通用 /api/admin/stats 拉每个表的 count
  // 当前先用占位
  setTimeout(() => { loading.value = false }, 300)
})
</script>

<template>
  <div class="p-4 sm:p-6">
    <div class="mb-6">
      <h1 class="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="material-symbols-outlined text-[28px] text-primary">temple_buddhist</span>
        寺内容管理
      </h1>
      <p class="text-sm text-gray-500 mt-2">
        海丰大道庵 (profile 11) 内容管理后台 — 管理牌位 / 僧侣 / 经文 / 活动 / 供奉 / 相册 等内容
      </p>
      <p class="text-xs text-amber-600 mt-1">
        ⚠ 当前是骨架页面 — 13 个内容模块入口已就位, 但每个模块的 CRUD UI 尚未实装. 后续按波哥指示逐个加.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <router-link
        v-for="t in tables"
        :key="t.key"
        :to="t.route"
        class="block bg-white rounded-lg border border-gray-200 p-4 hover:border-primary hover:shadow-md transition-all"
      >
        <div class="flex items-start justify-between mb-2">
          <h3 class="text-base font-semibold text-gray-900">{{ t.label }}</h3>
          <span class="material-symbols-outlined text-[20px] text-gray-400">chevron_right</span>
        </div>
        <p class="text-xs text-gray-500">{{ t.desc }}</p>
        <div class="mt-3 text-[10px] text-gray-400 font-mono">table: temple_{{ t.key.replace('_', '-') }}</div>
      </router-link>
    </div>
  </div>
</template>