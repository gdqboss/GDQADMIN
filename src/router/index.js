import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/products'
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/login/Login.vue')
  },
  {
    path: '/products',
    name: 'ProductList',
    component: () => import('../modules/products/components/ProductList.vue')
  },
  {
    path: '/products/new',
    name: 'ProductNew',
    component: () => import('../modules/products/components/ProductNew.vue')
  },
  {
    path: '/products/:id/edit',
    name: 'ProductEdit',
    component: () => import('../modules/products/components/ProductEdit.vue')
  },
  {
    path: '/products/:id/skus',
    name: 'ProductSkus',
    component: () => import('../modules/products/components/ProductSkus.vue')
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/products'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
