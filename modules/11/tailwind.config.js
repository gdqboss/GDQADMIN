/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#409eff',
        'primary-light': '#ecf5ff',
        'primary-hover': '#2b8be0',
        success: '#67c23a',
        warning: '#e6a23c',
        danger: '#f56c6c',
        info: '#909399',
        'bg-light': '#f0f2f5',
        'text-primary': '#303133',
        'text-secondary': '#909399',
        'text-regular': '#606266',
        border: '#dcdfe6',
        'sidebar': '#001529',
        'sidebar-header': '#002140',
      },
    },
  },
  plugins: [],
}
