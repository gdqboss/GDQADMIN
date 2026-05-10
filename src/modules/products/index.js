import { ref, reactive } from 'vue'

const ProductList = {
  template: `<div>Product List</div>`,
  setup() {
    const loading = ref(false)
    const products = ref([])
    const pagination = reactive({ page: 1, size: 10, total: 0 })
    return { loading, products, pagination }
  },
}

const ProductNew = {
  template: `<div>New Product</div>`,
}

const ProductEdit = {
  template: `<div>Edit Product</div>`,
  props: ['id'],
}

const ProductSkus = {
  template: `<div>Product SKUs</div>`,
  props: ['id'],
}

const routes = [
  { path: '/products', component: ProductList },
  { path: '/products/new', component: ProductNew },
  { path: '/products/:id/edit', component: ProductEdit, props: true },
  { path: '/products/:id/skus', component: ProductSkus, props: true },
]

export default { routes }
