import type { ReactNode } from 'react'
import { Plus, Trash2 } from 'lucide-react'

import { RecordPicker, type PickerOption } from '@/components/form/RecordPicker'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatMoney, formatNumber } from '@/lib/format'
import type { ListParams } from '@/lib/pagination'
import { cn } from '@/lib/utils'
import { searchProducts } from '@/modules/product/api'
import { lineAmounts } from './amounts'
import { newLine, type EditableLine } from './lines'

type LinesEditorProps = {
  lines: EditableLine[]
  onChange: (lines: EditableLine[]) => void
  readOnly?: boolean
  /** Tampilkan kolom harga, diskon, pajak, dan subtotal. */
  withPrices?: boolean
  /** Harga default saat produk dipilih. */
  priceField?: 'sale_price' | 'cost'
  /** Filter pencarian produk, mis. { sold: 1 }. */
  productFilters?: ListParams
  /** Boleh baris tanpa produk (hanya deskripsi), mis. di faktur. */
  allowNoProduct?: boolean
  /** Error validasi dari server, kunci seperti "lines.0.quantity". */
  errors?: Record<string, string>
  /** Kolom tambahan hanya-baca, mis. jumlah terkirim. */
  extraColumns?: { header: string; cell: (line: EditableLine, index: number) => ReactNode }[]
  pickerKey: string
}

export function LinesEditor({
  lines,
  onChange,
  readOnly,
  withPrices = true,
  priceField = 'sale_price',
  productFilters,
  allowNoProduct,
  errors = {},
  extraColumns = [],
  pickerKey,
}: LinesEditorProps) {
  function update(index: number, changes: Partial<EditableLine>) {
    onChange(lines.map((line, i) => (i === index ? { ...line, ...changes } : line)))
  }

  function pickProduct(index: number, product: PickerOption | null) {
    const data = product?.data ?? {}
    update(index, {
      product,
      ...(product && withPrices
        ? { unit_price: String(Number(data[priceField] ?? 0)), description: String(data.name ?? product.label) }
        : {}),
    })
  }

  const err = (index: number, field: string) => errors[`lines.${index}.${field}`]
  const numberInput = (index: number, field: 'quantity' | 'unit_price' | 'discount' | 'tax_rate', className = 'w-24') => (
    <Input
      type="number"
      min={0}
      step="any"
      value={lines[index][field]}
      onChange={(e) => update(index, { [field]: e.target.value })}
      aria-invalid={!!err(index, field)}
      aria-label={field}
      title={err(index, field)}
      className={cn('h-8 text-right', className)}
    />
  )

  return (
    <div className="grid min-w-0 gap-2">
      <div className="min-w-0 rounded-md border">
        <Table>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="min-w-56">Produk</TableHead>
              {withPrices && <TableHead className="min-w-40">Deskripsi</TableHead>}
              <TableHead className="text-right">Jumlah</TableHead>
              {extraColumns.map((col) => (
                <TableHead key={col.header} className="text-right">
                  {col.header}
                </TableHead>
              ))}
              {withPrices && (
                <>
                  <TableHead className="text-right">Harga</TableHead>
                  <TableHead className="text-right">Diskon %</TableHead>
                  <TableHead className="text-right">Pajak %</TableHead>
                  <TableHead className="text-right">Subtotal</TableHead>
                </>
              )}
              {!readOnly && <TableHead className="w-10" />}
            </TableRow>
          </TableHeader>
          <TableBody>
            {lines.length === 0 && (
              <TableRow className="hover:bg-transparent">
                <TableCell colSpan={9} className="text-muted-foreground py-6 text-center">
                  Belum ada baris.
                </TableCell>
              </TableRow>
            )}
            {lines.map((line, index) => {
              const amounts = lineAmounts(line.quantity, line.unit_price, line.discount, line.tax_rate)
              return (
                <TableRow key={line.key} className="align-top">
                  <TableCell>
                    {readOnly ? (
                      <span className="font-medium">{line.product?.label ?? '—'}</span>
                    ) : (
                      <RecordPicker
                        value={line.product}
                        onChange={(product) => pickProduct(index, product)}
                        queryKey={pickerKey}
                        search={(term) => searchProducts(term, productFilters)}
                        placeholder={allowNoProduct ? 'Tanpa produk' : 'Pilih produk…'}
                        clearable={!!allowNoProduct}
                        invalid={!!err(index, 'product_id')}
                      />
                    )}
                    {err(index, 'product_id') && <p className="text-destructive mt-1 text-xs">{err(index, 'product_id')}</p>}
                  </TableCell>
                  {withPrices && (
                    <TableCell>
                      {readOnly ? (
                        <span className="text-muted-foreground whitespace-normal">{line.description}</span>
                      ) : (
                        <Input
                          value={line.description}
                          onChange={(e) => update(index, { description: e.target.value })}
                          aria-label="Deskripsi"
                          aria-invalid={!!err(index, 'description')}
                          className="h-8"
                        />
                      )}
                    </TableCell>
                  )}
                  <TableCell className="text-right">
                    {readOnly ? (
                      <span className="tabular-nums">
                        {formatNumber(line.quantity)} {line.product?.hint}
                      </span>
                    ) : (
                      numberInput(index, 'quantity')
                    )}
                  </TableCell>
                  {extraColumns.map((col) => (
                    <TableCell key={col.header} className="text-right tabular-nums">
                      {col.cell(line, index)}
                    </TableCell>
                  ))}
                  {withPrices && (
                    <>
                      <TableCell className="text-right tabular-nums">
                        {readOnly ? formatMoney(line.unit_price) : numberInput(index, 'unit_price', 'w-32')}
                      </TableCell>
                      <TableCell className="text-right tabular-nums">
                        {readOnly ? formatNumber(line.discount) : numberInput(index, 'discount', 'w-20')}
                      </TableCell>
                      <TableCell className="text-right tabular-nums">
                        {readOnly ? formatNumber(line.tax_rate) : numberInput(index, 'tax_rate', 'w-20')}
                      </TableCell>
                      <TableCell className="pt-4 text-right font-medium tabular-nums">{formatMoney(amounts.subtotal)}</TableCell>
                    </>
                  )}
                  {!readOnly && (
                    <TableCell>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => onChange(lines.filter((_, i) => i !== index))}
                        aria-label="Hapus baris"
                      >
                        <Trash2 />
                      </Button>
                    </TableCell>
                  )}
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>
      {errors.lines && <p className="text-destructive text-sm">{errors.lines}</p>}
      {!readOnly && (
        <Button type="button" variant="outline" size="sm" className="w-fit" onClick={() => onChange([...lines, newLine()])}>
          <Plus /> Tambah baris
        </Button>
      )}
    </div>
  )
}
