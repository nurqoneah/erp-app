import { renderHook } from '@testing-library/react'
import { vi } from 'vitest'

import { useBarcodeScanner } from './useBarcodeScanner'

function press(key: string) {
  window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }))
}

describe('useBarcodeScanner', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('scan cepat + Enter memanggil onScan dan angkanya tidak masuk numpad', () => {
    const onScan = vi.fn(() => true)
    const onKey = vi.fn()
    renderHook(() => useBarcodeScanner({ onScan, onKey }))

    for (const ch of '8990001') {
      press(ch)
      vi.advanceTimersByTime(5)
    }
    press('Enter')
    vi.advanceTimersByTime(200)

    expect(onScan).toHaveBeenCalledWith('8990001')
    expect(onKey).not.toHaveBeenCalled()
  })

  it('ketikan manusia diteruskan ke numpad', () => {
    const onScan = vi.fn(() => true)
    const onKey = vi.fn()
    renderHook(() => useBarcodeScanner({ onScan, onKey }))

    press('3')
    vi.advanceTimersByTime(300)
    press('Backspace')
    vi.advanceTimersByTime(300)

    expect(onKey.mock.calls.map((c) => c[0])).toEqual(['3', 'Backspace'])
    expect(onScan).not.toHaveBeenCalled()
  })

  it('kode yang tidak dikenali juga tidak masuk ke numpad', () => {
    const onScan = vi.fn(() => false)
    const onKey = vi.fn()
    renderHook(() => useBarcodeScanner({ onScan, onKey }))

    for (const ch of '1234') {
      press(ch)
      vi.advanceTimersByTime(5)
    }
    press('Enter')

    vi.advanceTimersByTime(200)
    expect(onScan).toHaveBeenCalledWith('1234')
    expect(onKey).not.toHaveBeenCalled()
  })
})
