<template>
  <div class="worklog-container p-4">
    <!-- Page Header -->
    <div class="mb-4">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-medium">
          {{ activeTab === 'templates' ? $t('logs.logTemplates') : $t('logs.workLogManage') }}
        </h2>
        <el-button 
          v-if="activeTab !== 'templates'" 
          type="primary" 
          @click="handleCreateLog"
        >
          <span class="material-symbols-outlined text-sm mr-1">add</span>
          {{ $t('logs.createLog') }}
        </el-button>
        <el-button 
          v-if="activeTab === 'templates' && isAdmin" 
          type="primary" 
          @click="handleCreateTemplate"
        >
          <span class="material-symbols-outlined text-sm mr-1">add</span>
          {{ $t('logs.createTemplate') }}
        </el-button>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2 mb-4">
        <button 
          v-for="tab in availableTabs" 
          :key="tab.value"
          @click="activeTab = tab.value"
          class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="activeTab === tab.value 
            ? 'bg-primary text-white' 
            : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Log Type Filter (only for my/received tabs) -->
      <div v-if="activeTab !== 'templates'" class="flex gap-3 mb-4">
        <div 
          v-for="type in logTypes" 
          :key="type.value"
          @click="logType = type.value"
          class="flex items-center gap-2 cursor-pointer"
        >
          <span 
            class="material-symbols-outlined text-base"
            :class="logType === type.value ? 'text-primary' : 'text-gray-400'"
          >
            {{ type.icon }}
          </span>
          <span class="text-sm" :class="logType === type.value ? 'text-primary font-medium' : 'text-gray-500'">
            {{ type.label }}
          </span>
        </div>
      </div>

      <!-- Search Date Range for received tab -->
      <div v-if="activeTab === 'received'" class="flex gap-3 mb-4">
        <el-date-picker
          v-model="searchDateRange"
          type="daterange"
          range-separator="~"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          @change="handleSearchChange"
        />
      </div>
    </div>

    <!-- List View for my/received -->
    <div v-if="activeTab !== 'templates'">
      <!-- Empty State -->
      <div v-if="!loading && logs.length === 0" class="text-center py-8 text-gray-400">
        <span class="material-symbols-outlined text-5xl mb-2">description</span>
        <p>{{ $t('common.noData') }}</p>
      </div>

      <!-- Log List -->
      <div v-else class="space-y-3">
        <div 
          v-for="log in logs" 
          :key="log.id"
          class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-4 cursor-pointer hover:shadow-md transition-shadow"
          @click="handleViewDetail(log)"
        >
          <div class="flex justify-between items-start mb-2">
            <div class="flex-1">
              <div class="font-medium text-text-primary">{{ log.title || log.log_type }}</div>
              <div class="text-xs text-text-secondary mt-1">
                {{ log.creator_name }} · {{ formatDateTime(log.created_at) }}
              </div>
            </div>
            <div class="flex gap-2">
              <span 
                v-if="log.log_type === 'work'" 
                class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700"
              >
                {{ $t('logs.typeWork') }}
              </span>
              <span 
                v-if="log.log_type === 'complaint'" 
                class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700"
              >
                {{ $t('logs.typeComplaint') }}
              </span>
              <span 
                v-if="log.log_type === 'share'" 
                class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700"
              >
                {{ $t('logs.typeShare') }}
              </span>
            </div>
          </div>
          <div v-if="log.content" class="text-sm text-text-secondary line-clamp-2">
            {{ log.content }}
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="total > 0" class="mt-4 flex justify-center">
          <el-pagination
            v-model:current-page="currentPage"
            :page-size="pageSize"
            :total="total"
            layout="prev, pager, next"
            @current-change="handlePageChange"
          />
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-8">
        <span class="material-symbols-outlined text-4xl animate-spin text-gray-400">progress_activity</span>
      </div>
    </div>

    <!-- Template Management (Admin only) -->
    <div v-if="activeTab === 'templates' && isAdmin">
      <!-- Template List -->
      <div class="space-y-3">
        <div 
          v-for="template in templates" 
          :key="template.id"
          class="bg-white rounded-lg border border-gray-100 shadow-card p-4 mb-4"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="font-medium text-text-primary">{{ template.name }}</div>
              <div class="text-xs text-text-secondary mt-1">{{ template.description || $t('common.noDescription') }}</div>
              <div class="text-xs text-text-secondary mt-1">
                {{ $t('logs.logType') }}: 
                <span v-if="template.log_type === 'work'">{{ $t('logs.typeWork') }}</span>
                <span v-if="template.log_type === 'complaint'">{{ $t('logs.typeComplaint') }}</span>
                <span v-if="template.log_type === 'share'">{{ $t('logs.typeShare') }}</span>
              </div>
              <div class="text-xs text-text-secondary mt-1">
                {{ $t('logs.fieldCount') }}: {{ template.fields?.length || 0 }}
              </div>
            </div>
            <div class="flex gap-2">
              <el-button size="small" @click="handleEditTemplate(template)">
                <span class="material-symbols-outlined text-sm">edit</span>
              </el-button>
              <el-button size="small" type="danger" @click="handleDeleteTemplate(template)">
                <span class="material-symbols-outlined text-sm">delete</span>
              </el-button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && templates.length === 0" class="text-center py-8 text-gray-400">
          <span class="material-symbols-outlined text-5xl mb-2">folder_open</span>
          <p>{{ $t('common.noData') }}</p>
        </div>
      </div>
    </div>

    <!-- Log Detail Dialog -->
    <el-dialog v-model="detailVisible" :title="$t('logs.logDetail')" width="600px">
      <div v-if="currentLog" class="space-y-3">
        <div class="flex justify-between items-center pb-3 border-b">
          <div class="font-medium text-lg">{{ currentLog.title || currentLog.log_type }}</div>
          <span 
            v-if="currentLog.log_type === 'work'" 
            class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700"
          >
            {{ $t('logs.typeWork') }}
          </span>
          <span 
            v-if="currentLog.log_type === 'complaint'" 
            class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700"
          >
            {{ $t('logs.typeComplaint') }}
          </span>
          <span 
            v-if="currentLog.log_type === 'share'" 
            class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700"
          >
            {{ $t('logs.typeShare') }}
          </span>
        </div>
        
        <div class="text-sm text-text-secondary">
          <span class="material-symbols-outlined text-sm align-middle mr-1">person</span>
          {{ currentLog.creator_name }}
        </div>
        <div class="text-sm text-text-secondary">
          <span class="material-symbols-outlined text-sm align-middle mr-1">schedule</span>
          {{ formatDateTime(currentLog.created_at) }}
        </div>
        
        <div v-if="currentLog.content" class="mt-4 p-3 bg-gray-100 rounded">
          <div class="text-sm text-text-primary whitespace-pre-wrap">{{ currentLog.content }}</div>
        </div>

        <!-- Dynamic Fields Display -->
        <div v-if="currentLog.fields_data" class="mt-4 space-y-2">
          <div v-for="(value, key) in currentLog.fields_data" :key="key" class="flex gap-2">
            <span class="text-sm text-text-secondary w-24">{{ key }}:</span>
            <span class="text-sm text-text-primary">{{ formatFieldValue(value) }}</span>
          </div>
        </div>

        <!-- Recipients for shared logs -->
        <div v-if="currentLog.recipients && currentLog.recipients.length > 0" class="mt-4">
          <div class="text-sm text-text-secondary mb-2">{{ $t('logs.recipients') }}:</div>
          <div class="flex flex-wrap gap-2">
            <span 
              v-for="recipient in currentLog.recipients" 
              :key="recipient.id || recipient"
              class="px-2 py-1 bg-gray-100 rounded text-xs"
            >
              {{ recipient.name || recipient }}
            </span>
          </div>
        </div>
      </div>
      
      <template #footer>
        <el-button @click="detailVisible = false">{{ $t('common.close') }}</el-button>
        <el-button v-if="activeTab === 'my'" type="primary" @click="handleEditLog(currentLog)">
          {{ $t('common.edit') }}
        </el-button>
      </template>
    </el-dialog>

    <!-- Create/Edit Log Form Dialog -->
    <el-dialog v-model="formVisible" :title="isEditingLog ? $t('logs.editLog') : $t('logs.createLog')" width="700px">
      <el-form :model="logForm" :rules="logFormRules" ref="logFormRef" label-width="100px" class="space-y-4">
        <!-- Log Type Selection -->
        <el-form-item :label="$t('logs.logType')" prop="log_type">
          <el-select v-model="logForm.log_type" :placeholder="$t('logs.selectLogType')" @change="handleLogTypeChange">
            <el-option :label="$t('logs.typeWork')" value="work" />
            <el-option :label="$t('logs.typeComplaint')" value="complaint" />
            <el-option :label="$t('logs.typeShare')" value="share" />
          </el-select>
        </el-form-item>

        <!-- Title -->
        <el-form-item :label="$t('logs.title')" prop="title">
          <el-input v-model="logForm.title" :placeholder="$t('logs.titlePlaceholder')" />
        </el-form-item>

        <!-- Content -->
        <el-form-item :label="$t('logs.content')" prop="content">
          <el-input 
            v-model="logForm.content" 
            type="textarea" 
            :rows="4" 
            :placeholder="$t('logs.contentPlaceholder')" 
          />
        </el-form-item>

        <!-- Template Fields (dynamic) -->
        <template v-if="selectedTemplate && selectedTemplate.fields">
          <el-form-item 
            v-for="field in selectedTemplate.fields" 
            :key="field.name"
            :label="field.label || field.name"
            :prop="'fields_data.' + field.name"
          >
            <!-- Text -->
            <el-input 
              v-if="field.type === 'text'" 
              v-model="logForm.fields_data[field.name]" 
              :placeholder="field.placeholder || ''"
            />
            
            <!-- Number -->
            <el-input 
              v-else-if="field.type === 'number'" 
              v-model="logForm.fields_data[field.name]" 
              type="number"
              :placeholder="field.placeholder || ''"
            />
            
            <!-- Date -->
            <el-date-picker
              v-else-if="field.type === 'date'"
              v-model="logForm.fields_data[field.name]"
              type="date"
              value-format="YYYY-MM-DD"
              :placeholder="field.placeholder || $t('logs.selectDate')"
            />
            
            <!-- Time -->
            <el-time-picker
              v-else-if="field.type === 'time'"
              v-model="logForm.fields_data[field.name]"
              value-format="HH:mm:ss"
              :placeholder="field.placeholder || $t('logs.selectTime')"
            />
            
            <!-- Time Range -->
            <div v-else-if="field.type === 'time_range'" class="flex gap-2">
              <el-time-picker
                v-model="logForm.fields_data[field.name + '_start']"
                value-format="HH:mm:ss"
                :placeholder="$t('logs.startTime')"
              />
              <span class="self-center">~</span>
              <el-time-picker
                v-model="logForm.fields_data[field.name + '_end']"
                value-format="HH:mm:ss"
                :placeholder="$t('logs.endTime')"
              />
            </div>
            
            <!-- Textarea -->
            <el-input 
              v-else-if="field.type === 'textarea'" 
              v-model="logForm.fields_data[field.name]" 
              type="textarea"
              :rows="3"
              :placeholder="field.placeholder || ''"
            />
            
            <!-- Select -->
            <el-select 
              v-else-if="field.type === 'select'" 
              v-model="logForm.fields_data[field.name]" 
              :placeholder="field.placeholder || $t('logs.selectOption')"
              clearable
            >
              <el-option 
                v-for="option in (field.options || [])" 
                :key="option.value || option" 
                :label="option.label || option" 
                :value="option.value || option" 
              />
            </el-select>
            
            <!-- Checkbox -->
            <div v-else-if="field.type === 'checkbox'" class="flex flex-wrap gap-2">
              <el-checkbox 
                v-for="option in (field.options || [])" 
                :key="option.value || option"
                v-model="logForm.fields_data[field.name]"
                :label="option.value || option"
              >
                {{ option.label || option }}
              </el-checkbox>
            </div>
            
            <!-- Radio -->
            <el-radio-group v-else-if="field.type === 'radio'" v-model="logForm.fields_data[field.name]">
              <el-radio 
                v-for="option in (field.options || [])" 
                :key="option.value || option"
                :value="option.value || option"
              >
                {{ option.label || option }}
              </el-radio>
            </el-radio-group>
            
            <!-- Rating -->
            <div v-else-if="field.type === 'rating'" class="flex gap-1">
              <span 
                v-for="star in 5" 
                :key="star"
                @click="logForm.fields_data[field.name] = star"
                class="material-symbols-outlined cursor-pointer text-xl"
                :class="star <= (logForm.fields_data[field.name] || 0) ? 'text-yellow-400' : 'text-gray-300'"
              >
                star
              </span>
            </div>
            
            <!-- Location -->
            <el-input 
              v-else-if="field.type === 'location'" 
              v-model="logForm.fields_data[field.name]" 
              :placeholder="$t('logs.locationPlaceholder')"
            >
              <template #append>
                <span class="material-symbols-outlined cursor-pointer">location_on</span>
              </template>
            </el-input>
            
            <!-- Image -->
            <el-input 
              v-else-if="field.type === 'image'" 
              v-model="logForm.fields_data[field.name]" 
              :placeholder="$t('logs.imagePlaceholder')"
            >
              <template #append>
                <span class="material-symbols-outlined cursor-pointer">image</span>
              </template>
            </el-input>
            
            <!-- Participants -->
            <el-select 
              v-else-if="field.type === 'participants'" 
              v-model="logForm.fields_data[field.name]" 
              multiple 
              :placeholder="$t('logs.selectParticipants')"
            >
              <el-option 
                v-for="user in participantsList" 
                :key="user.id" 
                :label="user.name" 
                :value="user.id" 
              />
            </el-select>

            <!-- Recipients -->
            <el-select 
              v-else-if="field.type === 'recipients'" 
              v-model="logForm.fields_data[field.name]" 
              multiple 
              :placeholder="$t('logs.selectRecipients')"
            >
              <el-option 
                v-for="user in recipientsOption" 
                :key="user.id" 
                :label="user.name" 
                :value="user.id" 
              />
            </el-select>
            
            <!-- Default text input fallback -->
            <el-input 
              v-else 
              v-model="logForm.fields_data[field.name]" 
              :placeholder="field.placeholder || ''"
            />
          </el-form-item>
        </template>

        <!-- Recipients (for share type) -->
        <el-form-item v-if="logForm.log_type === 'share'" :label="$t('logs.recipients')" prop="recipients">
          <el-select 
            v-model="logForm.recipients" 
            multiple 
            :placeholder="$t('logs.selectRecipients')"
            class="w-full"
          >
            <el-option 
              v-for="user in recipientsOption" 
              :key="user.id" 
              :label="user.name" 
              :value="user.id" 
            />
          </el-select>
        </el-form-item>

        <!-- Show options block indicator for recipients -->
        <div v-if="logForm.log_type === 'share'" class="text-xs text-text-secondary mt-1">
          {{ $t('logs.shareTip') }}
        </div>
      </el-form>
      
      <template #footer>
        <el-button @click="formVisible = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmitLog">
          {{ $t('common.submit') }}
        </el-button>
      </template>
    </el-dialog>

    <!-- Create/Edit Template Dialog -->
    <el-dialog 
      v-model="templateFormVisible" 
      :title="isEditingTemplate ? $t('logs.editTemplate') : $t('logs.createTemplate')" 
      width="800px"
    >
      <el-form :model="templateForm" :rules="templateFormRules" ref="templateFormRef" label-width="100px">
        <!-- Template Name -->
        <el-form-item :label="$t('logs.templateName')" prop="name">
          <el-input v-model="templateForm.name" :placeholder="$t('logs.templateNamePlaceholder')" />
        </el-form-item>

        <!-- Description -->
        <el-form-item :label="$t('logs.description')" prop="description">
          <el-input 
            v-model="templateForm.description" 
            type="textarea" 
            :rows="2" 
            :placeholder="$t('logs.descriptionPlaceholder')" 
          />
        </el-form-item>

        <!-- Log Type -->
        <el-form-item :label="$t('logs.logType')" prop="log_type">
          <el-select v-model="templateForm.log_type" :placeholder="$t('logs.selectLogType')">
            <el-option :label="$t('logs.typeWork')" value="work" />
            <el-option :label="$t('logs.typeComplaint')" value="complaint" />
            <el-option :label="$t('logs.typeShare')" value="share" />
          </el-select>
        </el-form-item>

        <!-- Fields Configuration -->
        <el-form-item :label="$t('logs.fields')">
          <div class="w-full space-y-3">
            <div 
              v-for="(field, index) in templateForm.fields" 
              :key="index"
              class="p-3 bg-gray-100 rounded border border-gray-200"
            >
              <div class="flex gap-2 mb-2">
                <el-input 
                  v-model="field.name" 
                  :placeholder="$t('logs.fieldName')"
                  class="flex-1"
                />
                <el-select 
                  v-model="field.type" 
                  :placeholder="$t('logs.fieldType')"
                  class="w-32"
                  @change="handleFieldTypeChange(field)"
                >
                  <el-option label="Text" value="text" />
                  <el-option label="Number" value="number" />
                  <el-option label="Date" value="date" />
                  <el-option label="Time" value="time" />
                  <el-option label="Time Range" value="time_range" />
                  <el-option label="Textarea" value="textarea" />
                  <el-option label="Select" value="select" />
                  <el-option label="Checkbox" value="checkbox" />
                  <el-option label="Radio" value="radio" />
                  <el-option label="Rating" value="rating" />
                  <el-option label="Location" value="location" />
                  <el-option label="Image" value="image" />
                  <el-option label="Participants" value="participants" />
                  <el-option label="Recipients" value="recipients" />
                </el-select>
                <el-button type="danger" @click="handleRemoveField(index)" :disabled="templateForm.fields.length <= 1">
                  <span class="material-symbols-outlined text-sm">delete</span>
                </el-button>
              </div>
              
              <div class="flex gap-2">
                <el-input 
                  v-model="field.label" 
                  :placeholder="$t('logs.fieldLabel')"
                  class="flex-1"
                />
                <el-input 
                  v-model="field.placeholder" 
                  :placeholder="$t('logs.fieldPlaceholder')"
                  class="flex-1"
                />
              </div>

              <!-- Options for select/checkbox/radio -->
              <div v-if="['select', 'checkbox', 'radio'].includes(field.type)" class="mt-2">
                <el-input
                  v-model="field.optionsText"
                  type="textarea"
                  :rows="2"
                  :placeholder="$t('logs.optionsPlaceholder')"
                  @blur="handleOptionsTextChange(field)"
                />
                <div class="text-xs text-text-secondary mt-1">{{ $t('logs.optionsTip') }}</div>
              </div>
            </div>
            
            <el-button type="dashed" @click="handleAddField" class="w-full">
              <span class="material-symbols-outlined text-sm mr-1">add</span>
              {{ $t('logs.addField') }}
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="templateFormVisible = false">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmitTemplate">
          {{ $t('common.submit') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/stores/user'
import request from '@/api/request'
const get = (url, config) => request.get(url, config)
const post = (url, data, config) => request.post(url, data, config)
const put = (url, data, config) => request.put(url, data, config)
const del = (url, config) => request.delete(url, config)
import { ElMessage, ElMessageBox } from 'element-plus'

const { t } = useI18n()
const userStore = useUserStore()

// Tab system
const activeTab = ref('my')
const logType = ref('work')

// Main state
const logs = ref([])
const templates = ref([])
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const loading = ref(false)
const searchDateRange = ref(null)

// Modal states
const detailVisible = ref(false)
const formVisible = ref(false)
const templateFormVisible = ref(false)

// Current items
const currentLog = ref(null)
const selectedTemplate = ref(null)

// Submit loading
const submitLoading = ref(false)

// Form refs
const logFormRef = ref(null)
const templateFormRef = ref(null)

// Editing flags
const isEditingLog = ref(false)
const isEditingTemplate = ref(false)

// Admin check - userStore.userRole is a string ('admin'|'operator'|etc)
const isAdmin = computed(() => {
  const role = userStore.userRole || userStore.userInfo?.role || ''
  return role === 'admin' || role === 'administrator' || role === '超级管理员'
})

// Available tabs
const availableTabs = computed(() => {
  const tabs = [
    { value: 'my', label: t('logs.myLogs') },
    { value: 'received', label: t('logs.receivedLogs') },
  ]
  if (isAdmin.value) {
    tabs.push({ value: 'templates', label: t('logs.logTemplates') })
  }
  return tabs
})

// Log types
const logTypes = computed(() => [
  { value: 'work', label: t('logs.typeWork'), icon: 'work' },
  { value: 'complaint', label: t('logs.typeComplaint'), icon: 'error' },
  { value: 'share', label: t('logs.typeShare'), icon: 'share' },
])

// Participants list for select fields
const participantsList = ref([])

// Recipients options - using descriptive name to avoid Fl collision
const recipientsOption = ref([])

// Log form
const logForm = reactive({
  log_type: 'work',
  title: '',
  content: '',
  recipients: [],
  fields_data: {},
})

// Log form rules
const logFormRules = {
  log_type: [{ required: true, message: t('logs.logTypeRequired'), trigger: 'change' }],
  title: [{ required: true, message: t('logs.titleRequired'), trigger: 'blur' }],
}

// Template form
const templateForm = reactive({
  name: '',
  description: '',
  log_type: 'work',
  fields: [
    { name: '', label: '', type: 'text', placeholder: '', options: [], optionsText: '' }
  ],
})

// Template form rules
const templateFormRules = {
  name: [{ required: true, message: t('logs.templateNameRequired'), trigger: 'blur' }],
  log_type: [{ required: true, message: t('logs.logTypeRequired'), trigger: 'change' }],
}

// Watch activeTab to reload data
watch(activeTab, () => {
  currentPage.value = 1
  loadData()
})

// Watch logType to reload data
watch(logType, () => {
  currentPage.value = 1
  loadData()
})

// Format date time
const formatDateTime = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleString()
}

// Format field value for display
const formatFieldValue = (value) => {
  if (Array.isArray(value)) return value.join(', ')
  if (typeof value === 'object') return JSON.stringify(value)
  return value
}

// Load logs data
const loadData = async () => {
  loading.value = true
  try {
    if (activeTab.value === 'templates') {
      await loadTemplates()
    } else {
      await loadLogs()
    }
  } catch (error) {
    ElMessage.error(error.message || t('common.loadFailed'))
  } finally {
    loading.value = false
  }
}

// Load logs
const loadLogs = async () => {
  const params = {
    page: currentPage.value,
    pageSize: pageSize.value,
    log_type: logType.value,
  }
  
  if (activeTab.value === 'my') {
    params.created_by = userStore.userInfo?.id
  } else if (activeTab.value === 'received') {
    params.recipient_id = userStore.userInfo?.id
    if (searchDateRange.value) {
      params.start_date = searchDateRange.value[0]
      params.end_date = searchDateRange.value[1]
    }
  }
  
  const result = await get('/oa/work-logs', params)
  logs.value = result.data || result || []
  total.value = result.total || logs.value.length
}

// Load templates
const loadTemplates = async () => {
  const result = await get('/oa/work-log-templates')
  templates.value = result.data || result || []
}

// Load participants
const loadParticipants = async () => {
  try {
    const result = await get('/users/subordinates', { page: 1, pageSize: 100 })
    participantsList.value = result.data || result || []
  } catch (error) {
    // Fallback to users list
    try {
      const result = await get('/users/list', { page: 1, pageSize: 100 })
      participantsList.value = result.data || result || []
    } catch (e) {
      participantsList.value = []
    }
  }
}

// Load recipients (for share type)
const loadRecipients = async () => {
  try {
    const result = await get('/users/list', { page: 1, pageSize: 100 })
    recipientsOption.value = result.data || result || []
  } catch (error) {
    recipientsOption.value = []
  }
}

// Load templates for form dropdown
const loadTemplatesForForm = async () => {
  const result = await get('/oa/work-log-templates', { log_type: logType.value })
  return result.data || result || []
}

// Handle search change
const handleSearchChange = () => {
  currentPage.value = 1
  loadData()
}

// Handle page change
const handlePageChange = (page) => {
  currentPage.value = page
  loadData()
}

// View log detail
const handleViewDetail = (log) => {
  currentLog.value = log
  detailVisible.value = true
}

// Handle create log
const handleCreateLog = () => {
  isEditingLog.value = false
  resetLogForm()
  formVisible.value = true
}

// Handle edit log
const handleEditLog = (log) => {
  isEditingLog.value = true
  currentLog.value = log
  Object.assign(logForm, {
    log_type: log.log_type || 'work',
    title: log.title || '',
    content: log.content || '',
    recipients: log.recipients?.map(r => r.id || r) || [],
    fields_data: log.fields_data || {},
  })
  
  // Load template if exists
  if (log.template_id) {
    const template = templates.value.find(t => t.id === log.template_id)
    if (template) {
      selectedTemplate.value = template
    }
  }
  
  formVisible.value = true
  detailVisible.value = false
}

// Handle log type change in form
const handleLogTypeChange = async () => {
  // Reset form when type changes
  logForm.title = ''
  logForm.content = ''
  logForm.fields_data = {}
  logForm.recipients = []
  selectedTemplate.value = null
}

// Reset log form
const resetLogForm = () => {
  logForm.log_type = 'work'
  logForm.title = ''
  logForm.content = ''
  logForm.recipients = []
  logForm.fields_data = {}
  selectedTemplate.value = null
}

// Handle submit log
const handleSubmitLog = async () => {
  if (!logFormRef.value) return
  
  await logFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitLoading.value = true
    try {
      const data = {
        log_type: logForm.log_type,
        title: logForm.title,
        content: logForm.content,
        fields_data: logForm.fields_data,
        recipients: logForm.recipients,
        template_id: selectedTemplate.value?.id,
      }
      
      if (isEditingLog.value && currentLog.value) {
        await put(`/oa/work-logs/${currentLog.value.id}`, data)
        ElMessage.success(t('common.updateSuccess'))
      } else {
        await post('/oa/work-logs', data)
        ElMessage.success(t('common.createSuccess'))
      }
      
      formVisible.value = false
      loadData()
    } catch (error) {
      ElMessage.error(error.message || t('common.operationFailed'))
    } finally {
      submitLoading.value = false
    }
  })
}

// Handle create template
const handleCreateTemplate = () => {
  isEditingTemplate.value = false
  resetTemplateForm()
  templateFormVisible.value = true
}

// Handle edit template
const handleEditTemplate = (template) => {
  isEditingTemplate.value = true
  Object.assign(templateForm, {
    id: template.id,
    name: template.name,
    description: template.description || '',
    log_type: template.log_type || 'work',
    fields: template.fields?.map(f => ({
      ...f,
      optionsText: Array.isArray(f.options) ? f.options.join(', ') : (f.options || ''),
    })) || [{ name: '', label: '', type: 'text', placeholder: '', options: [], optionsText: '' }],
  })
  templateFormVisible.value = true
}

// Reset template form
const resetTemplateForm = () => {
  templateForm.id = null
  templateForm.name = ''
  templateForm.description = ''
  templateForm.log_type = 'work'
  templateForm.fields = [
    { name: '', label: '', type: 'text', placeholder: '', options: [], optionsText: '' }
  ]
}

// Handle add field
const handleAddField = () => {
  templateForm.fields.push({
    name: '',
    label: '',
    type: 'text',
    placeholder: '',
    options: [],
    optionsText: '',
  })
}

// Handle remove field
const handleRemoveField = (index) => {
  templateForm.fields.splice(index, 1)
}

// Handle field type change
const handleFieldTypeChange = (field) => {
  // Reset options when type changes
  field.options = []
  field.optionsText = ''
}

// Handle options text change (convert comma-separated string to array)
const handleOptionsTextChange = (field) => {
  if (field.optionsText) {
    field.options = field.optionsText.split(',').map(s => s.trim()).filter(s => s)
  } else {
    field.options = []
  }
}

// Handle submit template
const handleSubmitTemplate = async () => {
  if (!templateFormRef.value) return
  
  await templateFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitLoading.value = true
    try {
      const data = {
        name: templateForm.name,
        description: templateForm.description,
        log_type: templateForm.log_type,
        fields: templateForm.fields.map(f => ({
          name: f.name,
          label: f.label || f.name,
          type: f.type,
          placeholder: f.placeholder || '',
          options: f.options || [],
        })),
      }
      
      if (isEditingTemplate.value && templateForm.id) {
        await put(`/oa/work-log-templates/${templateForm.id}`, data)
        ElMessage.success(t('common.updateSuccess'))
      } else {
        await post('/oa/work-log-templates', data)
        ElMessage.success(t('common.createSuccess'))
      }
      
      templateFormVisible.value = false
      loadData()
    } catch (error) {
      ElMessage.error(error.message || t('common.operationFailed'))
    } finally {
      submitLoading.value = false
    }
  })
}

// Handle delete template
const handleDeleteTemplate = async (template) => {
  try {
    await ElMessageBox.confirm(
      t('logs.deleteTemplateConfirm'),
      t('common.confirm'),
      { type: 'warning' }
    )
    await del(`/oa/work-log-templates/${template.id}`)
    ElMessage.success(t('common.deleteSuccess'))
    loadData()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || t('common.deleteFailed'))
    }
  }
}

// Initialize default templates (admin only)
const initDefaultTemplates = async () => {
  try {
    await post('/oa/work-log-templates/init-defaults')
    loadData()
  } catch (error) {
    // Ignore if already initialized
  }
}

// On mount
onMounted(() => {
  loadData()
  loadParticipants()
  loadRecipients()
  
  // Admin can initialize default templates if none exist
  if (isAdmin.value && templates.value.length === 0) {
    // Could call initDefaultTemplates() here if needed
  }
})
</script>

<style scoped>
.worklog-container {
  background-color: var(--color-bg-light);
  min-height: 100%;
}

.shadow-card {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

:deep(.el-dialog__body) {
  padding-top: 15px;
}

:deep(.el-form-item) {
  margin-bottom: 18px;
}

:deep(.el-radio),
:deep(.el-checkbox) {
  margin-right: 15px;
}
</style>
