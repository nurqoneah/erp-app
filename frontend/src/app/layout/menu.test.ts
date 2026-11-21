import { findActiveGroup } from './menu'
import type { MenuGroup } from '@/modules/base/types'

const groups: MenuGroup[] = [
  { module: 'Product', slug: 'product', label: 'Produk', icon: 'package', items: [
    { label: 'Produk', path: '/products' },
    { label: 'Kategori', path: '/products/categories' },
  ] },
  { module: 'Pos', slug: 'pos', label: 'Point of Sale', icon: 'monitor', items: [
    { label: 'Kasir', path: '/pos' },
    { label: 'Sesi', path: '/pos/sessions' },
  ] },
]

describe('findActiveGroup', () => {
  it('cocokkan path persis dan sub-path', () => {
    expect(findActiveGroup(groups, '/products')?.module).toBe('Product')
    expect(findActiveGroup(groups, '/products/categories/5')?.module).toBe('Product')
    expect(findActiveGroup(groups, '/pos/sessions')?.module).toBe('Pos')
  })

  it('tidak salah cocok untuk prefix yang mirip', () => {
    expect(findActiveGroup(groups, '/possible')).toBeUndefined()
    expect(findActiveGroup(groups, '/')).toBeUndefined()
  })
})
