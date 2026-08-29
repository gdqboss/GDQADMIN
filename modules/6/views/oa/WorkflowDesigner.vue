<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">{{ $t('oa.workflowDesigner') }}</h1>
      <div class="flex gap-2">
        <button @click="showTemplates = true" class="px-4 py-2 border rounded hover:bg-gray-50">
          {{ $t('oa.templates') }}
        </button>
        <button @click="createWorkflow" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          {{ $t('common.create') }}
        </button>
      </div>
    </div>

    <!-- Workflow List -->
    <div class="grid grid-cols-1 gap-4 mb-6">
      <div v-for="workflow in workflows" :key="workflow.id"
           class="border rounded-lg p-4 hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <h3 class="text-lg font-semibold">{{ workflow.name }}</h3>
              <span class="px-2 py-1 text-xs rounded"
                    :class="workflow.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                {{ workflow.is_active ? $t('common.active') : $t('common.inactive') }}
              </span>
              <span v-if="workflow.is_template" class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">
                {{ $t('oa.template') }}
              </span>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
              <p>{{ $t('oa.workflowCode') }}: {{ workflow.code }}</p>
              <p>{{ $t('oa.category') }}: {{ workflow.category }}</p>
              <p>{{ $t('oa.version') }}: v{{ workflow.version }}</p>
              <p v-if="workflow.description" class="text-gray-500">{{ workflow.description }}</p>
              <p class="text-xs text-gray-400">{{ $t('common.creator') }}: {{ workflow.creator_name }} | {{ new Date(workflow.created_at).toLocaleString() }}</p>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="viewWorkflow(workflow)" class="text-blue-600 hover:underline text-sm">
              {{ $t('common.view') }}
            </button>
            <button @click="editWorkflow(workflow)" class="text-orange-600 hover:underline text-sm">
              {{ $t('common.edit') }}
            </button>
            <button @click="toggleWorkflowStatus(workflow)" class="text-gray-600 hover:underline text-sm">
              {{ workflow.is_active ? $t('common.disable') : $t('common.enable') }}
            </button>
            <button @click="startInstance(workflow)" class="text-green-600 hover:underline text-sm">
              {{ $t('oa.startWorkflow') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Workflow Instances -->
    <div class="mt-8">
      <h2 class="text-xl font-bold mb-4">{{ $t('oa.workflowInstances') }}</h2>
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.instanceTitle') }}</th>
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.workflow') }}</th>
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.initiator') }}</th>
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.currentNode') }}</th>
              <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('common.status') }}</th>
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $t('oa.startedAt') }}</th>
              <th class="px-4 py-3 text-center text-sm font-semibold">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="instance in instances" :key="instance.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">{{ instance.title }}</td>
              <td class="px-4 py-3 text-sm">{{ instance.workflow_name }}</td>
              <td class="px-4 py-3 text-sm">{{ instance.initiator_name }}</td>
              <td class="px-4 py-3 text-sm">{{ instance.current_node }}</td>
              <td class="px-4 py-3 text-sm text-center">
                <span class="px-2 py-1 text-xs rounded"
                      :class="{
                        'bg-blue-100 text-blue-800': instance.status === 'running',
                        'bg-green-100 text-green-800': instance.status === 'completed',
                        'bg-red-100 text-red-800': instance.status === 'terminated',
                        'bg-gray-100 text-gray-800': instance.status === 'suspended'
                      }">
                  {{ instance.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm">{{ new Date(instance.started_at).toLocaleString() }}</td>
              <td class="px-4 py-3 text-sm text-center">
                <button @click="viewInstance(instance)" class="text-blue-600 hover:underline">
                  {{ $t('common.detail') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-center">
        <button @click="loadMoreInstances" v-if="hasMoreInstances" class="px-4 py-2 border rounded hover:bg-gray-50">
          {{ $t('common.loadMore') }}
        </button>
      </div>
    </div>

    <!-- Templates Dialog -->
    <div v-if="showTemplates" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">{{ $t('oa.workflowTemplates') }}</h2>
        <div class="space-y-3">
          <div v-for="template in templates" :key="template.id"
               class="border rounded p-3 hover:bg-gray-50 cursor-pointer"
               @click="useTemplate(template)">
            <div class="font-semibold">{{ template.name }}</div>
            <div class="text-sm text-gray-600">{{ template.description }}</div>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="showTemplates = false" class="px-4 py-2 border rounded hover:bg-gray-50">
            {{ $t('common.close') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Workflow Designer Dialog -->
    <div v-if="showDesigner" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">{{ editingWorkflow ? $t('common.edit') : $t('common.create') }}{{ $t('oa.workflow') }}</h2>
        <form @submit.prevent="saveWorkflow" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.workflowName') }}</label>
              <input v-model="workflowForm.name" required class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">{{ $t('oa.workflowCode') }}</label>
              <input v-model="workflowForm.code" required :disabled="!!editingWorkflow" class="w-full border rounded px-3 py-2" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.category') }}</label>
            <select v-model="workflowForm.category" class="w-full border rounded px-3 py-2">
              <option value="approval">{{ $t('oa.approval') }}</option>
              <option value="notification">{{ $t('oa.notification') }}</option>
              <option value="automation">{{ $t('oa.automation') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('common.description') }}</label>
            <textarea v-model="workflowForm.description" rows="2" class="w-full border rounded px-3 py-2"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.flowConfig') }}</label>
            <textarea v-model="flowConfigText" rows="10" class="w-full border rounded px-3 py-2 font-mono text-sm"
                      placeholder='{"nodes":[{"id":"start","type":"start","name":"start"}],"edges":[]}'></textarea>
            <p class="text-xs text-gray-500 mt-1">{{ $t('oa.jsonFlowConfigHint') }}</p>
          </div>
          <div class="flex items-center gap-2">
            <input v-model="workflowForm.is_template" type="checkbox" id="is_template" />
            <label for="is_template" class="text-sm">{{ $t('oa.saveAsTemplate') }}</label>
          </div>
          <div class="flex gap-2 justify-end">
            <button type="button" @click="closeDesigner" class="px-4 py-2 border rounded hover:bg-gray-50">
              {{ $t('common.cancel') }}
            </button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              {{ $t('common.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Start Instance Dialog -->
    <div v-if="showStartDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">{{ $t('oa.startWorkflow') }}</h2>
        <form @submit.prevent="submitInstance" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.instanceTitle') }}</label>
            <input v-model="instanceForm.title" required class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.businessKey') }}</label>
            <input v-model="instanceForm.business_key" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">{{ $t('oa.businessType') }}</label>
            <input v-model="instanceForm.business_type" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="flex gap-2 justify-end">
            <button type="button" @click="showStartDialog = false" class="px-4 py-2 border rounded hover:bg-gray-50">
              {{ $t('common.cancel') }}
            </button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              {{ $t('oa.start') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const { t } = useI18n()
const workflows = ref([])
const templates = ref([])
const instances = ref([])
const showTemplates = ref(false)
const showDesigner = ref(false)
const showStartDialog = ref(false)
const editingWorkflow = ref(null)
const selectedWorkflow = ref(null)
const hasMoreInstances = ref(false)
const instancePage = ref(1)

const workflowForm = ref({
  name: '',
  code: '',
  category: 'approval',
  description: '',
  flow_config: { nodes: [], edges: [] },
  is_template: false
})

const instanceForm = ref({
  workflow_code: '',
  title: '',
  business_key: '',
  business_type: ''
})

const flowConfigText = computed({
  get: () => JSON.stringify(workflowForm.value.flow_config, null, 2),
  set: (val) => {
    try {
      workflowForm.value.flow_config = JSON.parse(val)
    } catch (e) {
      console.error('Invalid JSON')
    }
  }
})

onMounted(() => {
  fetchWorkflows()
  fetchInstances()
})

async function fetchWorkflows() {
  try {
    const res = await axios.get('/api/oa/workflows')
    if (res.data.code === 0) {
      workflows.value = res.data.data.filter(w => !w.is_template)
      templates.value = res.data.data.filter(w => w.is_template)
    }
  } catch (err) {
    console.error('Failed to fetch workflows:', err)
  }
}

async function fetchInstances() {
  try {
    const res = await axios.get('/api/oa/workflow-instances', {
      params: { page: instancePage.value, size: 20 }
    })
    if (res.data.code === 0) {
      if (instancePage.value === 1) {
        instances.value = res.data.data.list
      } else {
        instances.value.push(...res.data.data.list)
      }
      hasMoreInstances.value = instances.value.length < res.data.data.total
    }
  } catch (err) {
    console.error('Failed to fetch instances:', err)
  }
}

function createWorkflow() {
  editingWorkflow.value = null
  workflowForm.value = {
    name: '',
    code: '',
    category: 'approval',
    description: '',
    flow_config: { nodes: [], edges: [] },
    is_template: false
  }
  showDesigner.value = true
}

function editWorkflow(workflow) {
  editingWorkflow.value = workflow
  workflowForm.value = {
    name: workflow.name,
    code: workflow.code,
    category: workflow.category,
    description: workflow.description,
    flow_config: JSON.parse(workflow.flow_config),
    is_template: workflow.is_template
  }
  showDesigner.value = true
}

function viewWorkflow(workflow) {
  alert(`Workflow: ${workflow.name}\n\n${JSON.stringify(JSON.parse(workflow.flow_config), null, 2)}`)
}

async function saveWorkflow() {
  try {
    if (editingWorkflow.value) {
      await axios.put(`/api/oa/workflows/${editingWorkflow.value.id}`, workflowForm.value)
    } else {
      await axios.post('/api/oa/workflows', workflowForm.value)
    }
    closeDesigner()
    fetchWorkflows()
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save workflow')
  }
}

async function toggleWorkflowStatus(workflow) {
  try {
    await axios.put(`/api/oa/workflows/${workflow.id}`, { is_active: !workflow.is_active })
    fetchWorkflows()
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update status')
  }
}

function startInstance(workflow) {
  selectedWorkflow.value = workflow
  instanceForm.value = {
    workflow_code: workflow.code,
    title: '',
    business_key: '',
    business_type: ''
  }
  showStartDialog.value = true
}

async function submitInstance() {
  try {
    await axios.post('/api/oa/workflow-instances', instanceForm.value)
    showStartDialog.value = false
    instancePage.value = 1
    fetchInstances()
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to start workflow')
  }
}

function viewInstance(instance) {
  // Navigate to instance detail page or show modal
  window.location.href = `#/oa/workflow-instance/${instance.id}`
}

function useTemplate(template) {
  workflowForm.value = {
    name: template.name + ' (Copy)',
    code: template.code + '_COPY',
    category: template.category,
    description: template.description,
    flow_config: JSON.parse(template.flow_config),
    is_template: false
  }
  showTemplates.value = false
  showDesigner.value = true
}

function closeDesigner() {
  showDesigner.value = false
  editingWorkflow.value = null
}

function loadMoreInstances() {
  instancePage.value++
  fetchInstances()
}
</script>
