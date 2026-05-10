<template>
  <div class="login-page">
    <div class="login-card">
      <h1>彩美特管理系统</h1>
      <el-form ref="formRef" :model="form" :rules="rules" @submit.prevent="handleLogin">
        <el-form-item prop="username">
          <el-input
            v-model="form.username"
            placeholder="请输入邮箱或手机号"
            prefix-icon="User"
            size="large"
          />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码"
            prefix-icon="Lock"
            show-password
            size="large"
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        <el-form-item>
          <el-checkbox v-model="form.remember">记住账号密码</el-checkbox>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" size="large" style="width: 100%" :loading="loading" @click="handleLogin">
            登录
          </el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import request from '../../api/request.js'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({
  username: '',
  password: '',
  remember: false
})

const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

const handleLogin = async () => {
  try {
    await formRef.value?.validate()
    loading.value = true
    const { username, password } = form
    const res = await request.post('/auth/login', { email: username, password })
    localStorage.setItem('token', res.token)
    localStorage.setItem('userInfo', JSON.stringify(res.user))
    ElMessage.success('登录成功')
    router.push('/products')
  } catch (e) {
    if (e) ElMessage.error(e.message || '登录失败')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.login-card {
  background: white;
  padding: 40px;
  border-radius: 12px;
  width: 400px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.login-card h1 {
  text-align: center;
  margin-bottom: 30px;
  color: #333;
  font-size: 24px;
}
</style>
