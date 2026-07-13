/* eslint-env node */
module.exports = {
  root: true,
  env: { browser: true, es2022: true, node: true },
  parser: 'vue-eslint-parser',
  parserOptions: {
    parser: '@babel/eslint-parser',
    ecmaVersion: 2022,
    sourceType: 'module',
    requireConfigFile: false,
  },
  extends: [
    'eslint:recommended',
    'plugin:vue/vue3-recommended',
    'prettier',
  ],
  rules: {
    /**
     * 0.6 铁律：禁止 view 直接使用 ElementPlus 内部组件
     * 必须走 src/components/ui/ 抽象层（UiButton / UiTable / UiInput / UiForm / UiDialog）
     *
     * 白名单（豁免）：
     *   - src/components/ui/ 抽象层本身
     *   - src/main.js 注册中心
     *   - legacy/ 兼容期（待迁移老组件）
     *   - 无前缀 el-radio-group / el-checkbox-group 等 el-* 容器的 -group 后缀 (避免误伤)
     *
     * 必改清单：见 docs/MIGRATE_TO_UIKIT.md
     */
    'no-restricted-syntax': [
      'error',
      {
        selector: "VElement[name='el-button']",
        message: '❌ 禁止直接使用 el-button，请改用 <UiButton variant="primary">（0.6 铁律）',
      },
      {
        selector: "VElement[name='el-table']",
        message: '❌ 禁止直接使用 el-table，请改用 <UiTable :data="rows" :columns="cols">（0.6 铁律）',
      },
      {
        selector: "VElement[name='el-input']",
        message: '❌ 禁止直接使用 el-input，请改用 <UiInput v-model="x">（0.6 铁律）',
      },
      {
        selector: "VElement[name='el-form']",
        message: '❌ 禁止直接使用 el-form，请改用 <UiForm :model="form" :rules="rules">（0.6 铁律）',
      },
      {
        selector: "VElement[name='el-dialog']",
        message: '❌ 禁止直接使用 el-dialog，请改用 <UiDialog v-model="show">（0.6 铁律）',
      },
      {
        selector: "VElement[name='el-table-column']",
        message: '❌ 禁止直接使用 el-table-column，请在 UiTable :columns 数组里定义列（0.6 铁律）',
      },
      {
        selector: "VElement[name='el-form-item']",
        message: '❌ 禁止直接使用 el-form-item，请改用 <UiFormItem prop="name">（0.6 铁律）',
      },
    ],
  },
  overrides: [
    {
      // 抽象层内部允许使用 EP (因为它就是封装 EP 的)
      files: ['src/components/ui/**/*.vue'],
      rules: {
        'no-restricted-syntax': 'off',
      },
    },
    {
      // main.js 注册中心允许使用 EP 组件名
      files: ['src/main.js'],
      rules: {
        'no-restricted-syntax': 'off',
      },
    },
    {
      // 已淘汰的 modules/{1,2,3} profile build 工作目录副本 (未来才迁)
      files: ['src/modules/**/*.vue'],
      rules: {
        'no-restricted-syntax': 'off',
      },
    },
  ],
  ignorePatterns: [
    'dist/**',
    'node_modules/**',
    '*.min.js',
    'coverage/**',
  ],
}
