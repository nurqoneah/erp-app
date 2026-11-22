import { render, screen } from '@testing-library/react'
import { vi } from 'vitest'

import { registerSlot } from '@/app/extensions'
import { ExtensionSlot } from './ExtensionSlot'

const installed = vi.hoisted(() => ({ value: new Set<string>() as Set<string> | null }))
vi.mock('@/modules/base/api', () => ({ useInstalledModules: () => installed.value }))

registerSlot('test.form', { module: 'Beta', order: 20, component: () => <p>dari Beta</p> })
registerSlot('test.form', { module: 'Alpha', order: 10, component: ({ values }) => <p>Alpha: {String(values.code)}</p> })

const props = { values: { code: 'X1' }, set: () => {}, errors: {}, readOnly: false }

describe('ExtensionSlot', () => {
  it('hanya menampilkan perluasan dari modul terpasang, urut sesuai order', () => {
    installed.value = new Set(['Alpha', 'Beta'])
    render(<ExtensionSlot point="test.form" {...props} />)

    expect(screen.getAllByText(/Alpha|Beta/).map((el) => el.textContent)).toEqual(['Alpha: X1', 'dari Beta'])
  })

  it('menyembunyikan perluasan modul yang belum terpasang', () => {
    installed.value = new Set(['Beta'])
    render(<ExtensionSlot point="test.form" {...props} />)

    expect(screen.queryByText(/Alpha/)).not.toBeInTheDocument()
    expect(screen.getByText('dari Beta')).toBeInTheDocument()
  })

  it('tidak menampilkan apa pun selama daftar modul dimuat', () => {
    installed.value = null
    const { container } = render(<ExtensionSlot point="test.form" {...props} />)

    expect(container).toBeEmptyDOMElement()
  })
})
