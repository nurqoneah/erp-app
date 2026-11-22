export type NumpadKey = '0' | '1' | '2' | '3' | '4' | '5' | '6' | '7' | '8' | '9' | '.' | 'backspace'

/** Terapkan tombol numpad ke buffer teks angka. */
export function applyKey(buffer: string, key: NumpadKey): string {
  if (key === 'backspace') return buffer.slice(0, -1)
  if (key === '.') return buffer.includes('.') ? buffer : (buffer || '0') + '.'
  if (buffer === '0') return key
  return buffer + key
}

/** Ubah tombol keyboard fisik menjadi tombol numpad. */
export function keyFromKeyboard(key: string): NumpadKey | null {
  if (key === 'Backspace') return 'backspace'
  return /^[0-9.]$/.test(key) ? (key as NumpadKey) : null
}
