import { documentTotals, lineAmounts } from './amounts'

describe('lineAmounts', () => {
  it('sama dengan perhitungan backend', () => {
    expect(lineAmounts(3, 33000, 10, 11)).toEqual({ subtotal: 89100, tax: 9801, total: 98901 })
    expect(lineAmounts('1', '0.333', 0, 12)).toEqual({ subtotal: 0.33, tax: 0.04, total: 0.37 })
  })

  it('menjumlah baris yang sudah dibulatkan', () => {
    expect(documentTotals([{ quantity: 1, unit_price: 0.105 }, { quantity: 1, unit_price: 0.105 }])).toEqual({ untaxed: 0.22, tax: 0, total: 0.22 })
  })
})
