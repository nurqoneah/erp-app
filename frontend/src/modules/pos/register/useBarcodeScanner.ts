import { useEffect, useLayoutEffect, useRef } from 'react'

/** Jeda antar karakter maksimal untuk dianggap ketikan barcode scanner. */
const SCAN_GAP_MS = 50
/** Berapa lama tombol angka ditahan sebelum dianggap ketikan manusia untuk numpad. */
const FLUSH_DELAY_MS = 60

/**
 * Menangkap input barcode scanner (mengetik sangat cepat lalu Enter) di mana pun fokusnya.
 *
 * Tombol angka saat tidak sedang mengetik di input diteruskan ke `onKey` (numpad), tetapi
 * ditahan sebentar dulu: bila ternyata bagian dari scan barcode, tombol itu dibuang agar
 * angka barcode tidak masuk ke jumlah / harga di keranjang.
 */
export function useBarcodeScanner({
  onScan,
  onKey,
  enabled = true,
}: {
  onScan: (code: string) => boolean
  onKey?: (key: string) => void
  enabled?: boolean
}) {
  const handlers = useRef({ onScan, onKey })
  useLayoutEffect(() => {
    handlers.current = { onScan, onKey }
  })

  useEffect(() => {
    if (!enabled) return
    let code = ''
    let lastAt = 0
    let pending: string[] = []
    let timer: ReturnType<typeof setTimeout> | undefined

    const flush = () => {
      pending.forEach((key) => handlers.current.onKey?.(key))
      pending = []
    }

    function handle(event: KeyboardEvent) {
      const now = Date.now()
      const fast = now - lastAt < SCAN_GAP_MS
      lastAt = now
      const target = event.target as HTMLElement
      const typing = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable

      if (event.key === 'Enter') {
        const scanned = code
        code = ''
        if (scanned.length >= 4) {
          // Hasil scan (dikenal atau tidak): angkanya bukan untuk numpad.
          clearTimeout(timer)
          pending = []
          if (handlers.current.onScan(scanned)) {
            event.preventDefault()
            if (typing && target instanceof HTMLInputElement) target.value = ''
          }
          return
        }
        flush()
        return
      }

      if (event.key.length === 1) code = fast ? code + event.key : event.key

      if (!typing && !event.ctrlKey && !event.metaKey && /^[0-9.]$|^Backspace$/.test(event.key)) {
        if (fast && pending.length === 0 && code.length > 1) {
          // Bagian dari rangkaian cepat yang sudah diteruskan: kemungkinan scanner, jangan diteruskan.
          return
        }
        pending.push(event.key)
        clearTimeout(timer)
        timer = setTimeout(flush, FLUSH_DELAY_MS)
      }
    }

    window.addEventListener('keydown', handle)
    return () => {
      window.removeEventListener('keydown', handle)
      clearTimeout(timer)
    }
  }, [enabled])
}
