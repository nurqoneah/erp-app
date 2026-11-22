import { useMemo, useRef, useState } from 'react'
import { ArrowLeftRight, History, LayoutGrid, LogOut, Search, ShoppingCart, User, X } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { round } from '@/components/documents/amounts'
import { errorMessage } from '@/lib/api'
import { formatMoney, formatNumber } from '@/lib/format'
import { cn } from '@/lib/utils'
import { useCompany } from '@/modules/base/api'
import { useRegisterData, useSubmitOrder } from '../api'
import type { PosOrder, PosProduct } from '../types'
import { cartTotals, useCart, type CartLine, type NumpadMode } from './cart'
import { CashMoveDialog, CloseSessionDialog, CustomerDialog } from './dialogs'
import { Numpad } from './Numpad'
import { applyKey, keyFromKeyboard, type NumpadKey } from './numpad'
import { OrdersScreen } from './OrdersScreen'
import { PaymentScreen, type PaymentLine } from './PaymentScreen'
import { Receipt } from './Receipt'
import { useBarcodeScanner } from './useBarcodeScanner'

type Screen = 'products' | 'payment' | 'receipt' | 'orders'

const numpadModes: { key: NumpadMode; label: string }[] = [
  { key: 'quantity', label: 'Jml' },
  { key: 'discount', label: '% Disk' },
  { key: 'unit_price', label: 'Harga' },
]

/** Layar kasir (layar penuh), mengikuti alur kasir Odoo. */
export function RegisterPage() {
  const { sessionId } = useParams()
  const id = Number(sessionId)
  const { data, isLoading, isError } = useRegisterData(id)

  if (isLoading) return <Skeleton className="m-6 h-[80svh]" />
  if (isError || !data) {
    return (
      <div className="p-12 text-center">
        <p className="mb-4">Sesi kasir tidak ditemukan.</p>
        <Button asChild>
          <Link to="/pos">Kembali ke dasbor POS</Link>
        </Button>
      </div>
    )
  }
  if (data.session.state !== 'opened') {
    return (
      <div className="p-12 text-center">
        <p className="mb-4">Sesi {data.session.name} tidak sedang berjalan.</p>
        <Button asChild>
          <Link to="/pos">Kembali ke dasbor POS</Link>
        </Button>
      </div>
    )
  }

  return <Register sessionId={id} data={data} />
}

function Register({ sessionId, data }: { sessionId: number; data: NonNullable<ReturnType<typeof useRegisterData>['data']> }) {
  const navigate = useNavigate()
  const { data: company } = useCompany()
  const [cart, dispatch] = useCart(sessionId)
  const submit = useSubmitOrder(sessionId)
  const [screen, setScreen] = useState<Screen>('products')
  const [receipt, setReceipt] = useState<PosOrder | null>(null)
  const [dialog, setDialog] = useState<'customer' | 'cash' | 'close' | null>(null)
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState<number | null>(null)
  const [mode, setMode] = useState<NumpadMode>('quantity')
  // Buffer numpad per baris & mode: ketikan pertama mengganti nilai lama (seperti Odoo).
  const buffer = useRef<{ key: string | null; mode: NumpadMode; value: string }>({ key: null, mode: 'quantity', value: '' })

  const products = data.products
  const byBarcode = useMemo(() => new Map(products.filter((p) => p.barcode).map((p) => [p.barcode!, p])), [products])
  const categories = useMemo(() => {
    const map = new Map<number, string>()
    products.forEach((p) => p.category && map.set(p.category_id, p.category.split(' / ').at(-1)!))
    return [...map.entries()]
  }, [products])
  const visible = products.filter((p) => {
    if (category && p.category_id !== category) return false
    const term = search.trim().toLowerCase()
    return !term || p.name.toLowerCase().includes(term) || p.sku?.toLowerCase().includes(term) || p.barcode === term
  })

  const totals = cartTotals(cart.lines)
  const selected = cart.lines.find((l) => l.key === cart.selectedKey) ?? null
  const isRefund = !!cart.refundOf

  function addProduct(product: PosProduct) {
    if (isRefund) {
      toast.error('Selesaikan atau batalkan retur dulu.')
      return
    }
    dispatch({ type: 'add', product: { id: product.id, name: product.name, uom: product.uom, price: Number(product.price) } })
    buffer.current = { key: null, mode, value: '' }
  }

  function pressNumpad(key: NumpadKey) {
    if (!selected) return
    const field = isRefund && mode !== 'quantity' ? 'quantity' : mode
    const sameTarget = buffer.current.key === selected.key && buffer.current.mode === field
    const current = sameTarget ? buffer.current.value : ''

    // Backspace pada jumlah yang sudah kosong menghapus baris (seperti Odoo).
    if (key === 'backspace' && field === 'quantity' && (sameTarget ? current === '' : selected.quantity === 0)) {
      dispatch({ type: 'remove', key: selected.key })
      buffer.current = { key: null, mode: field, value: '' }
      return
    }

    const next = applyKey(current, key)
    buffer.current = { key: selected.key, mode: field, value: next }
    let value = Number(next || 0)
    if (field === 'discount') value = Math.min(value, 100)
    if (isRefund) value = -Math.min(value, selected.refund_max ?? Math.abs(selected.quantity))
    dispatch({ type: 'set', key: selected.key, field, value })
  }

  useBarcodeScanner({
    enabled: screen === 'products' && dialog === null,
    onScan: (code) => {
      const product = byBarcode.get(code)
      if (!product) {
        toast.error(`Barcode ${code} tidak dikenal`)
        return false
      }
      addProduct(product)
      setSearch('')
      return true
    },
    onKey: (key) => {
      const numpadKey = keyFromKeyboard(key)
      if (numpadKey) pressNumpad(numpadKey)
    },
  })

  async function validate(payments: PaymentLine[]) {
    try {
      const order = await submit.mutateAsync({
        partner_id: cart.partner?.id ?? null,
        to_invoice: cart.toInvoice,
        notes: null,
        lines: cart.lines.map((l) => ({
          product_id: l.product.id,
          quantity: l.quantity,
          unit_price: l.unit_price,
          discount: l.discount,
          refunded_line_id: l.refunded_line_id ?? null,
        })),
        payments: payments.map((p) => ({ payment_method_id: p.method.id, amount: Number(p.amount) })),
      })
      dispatch({ type: 'clear' })
      setReceipt(order)
      setScreen('receipt')
    } catch (error) {
      toast.error(errorMessage(error))
    }
  }

  function loadRefund(order: PosOrder, lines: Omit<CartLine, 'key'>[]) {
    dispatch({ type: 'loadRefund', order: { id: order.id, name: order.name }, lines, partner: order.partner ?? null })
    setScreen('products')
    setMode('quantity')
    toast.info(`Retur dari ${order.name}`)
  }

  return (
    <div className="bg-muted/40 flex h-svh flex-col">
      <header className="bg-card flex h-12 shrink-0 items-center gap-1 border-b px-2">
        <Button variant="ghost" size="icon" asChild aria-label="Kembali ke aplikasi">
          <Link to="/pos">
            <LayoutGrid />
          </Link>
        </Button>
        <div className="mr-auto min-w-0 truncate text-sm">
          <span className="font-semibold">{data.config.name}</span>
          <span className="text-muted-foreground hidden sm:inline"> · {data.session.name}</span>
        </div>
        <Button variant="ghost" size="sm" onClick={() => setScreen(screen === 'orders' ? 'products' : 'orders')} aria-pressed={screen === 'orders'}>
          <History /> <span className="hidden sm:inline">Pesanan</span>
        </Button>
        {data.config.cash_control && (
          <Button variant="ghost" size="sm" onClick={() => setDialog('cash')}>
            <ArrowLeftRight /> <span className="hidden sm:inline">Kas masuk/keluar</span>
          </Button>
        )}
        <Button variant="ghost" size="sm" onClick={() => setDialog('close')}>
          <LogOut /> <span className="hidden sm:inline">Tutup kasir</span>
        </Button>
      </header>

      <main className="min-h-0 flex-1">
        {screen === 'products' && (
          <div className="grid h-full min-h-0 lg:grid-cols-[minmax(340px,420px)_minmax(0,1fr)]">
            {/* Keranjang */}
            <section className="bg-card order-2 flex min-h-0 flex-col border-r lg:order-1" aria-label="Keranjang">
              {isRefund && (
                <div className="flex items-center justify-between bg-amber-100 px-3 py-2 text-sm text-amber-900">
                  <span>Retur dari {cart.refundOf!.name}</span>
                  <Button variant="ghost" size="sm" onClick={() => dispatch({ type: 'clear' })}>
                    <X /> Batal
                  </Button>
                </div>
              )}
              <ul className="min-h-24 flex-1 divide-y overflow-y-auto" data-testid="cart">
                {cart.lines.length === 0 && (
                  <li className="text-muted-foreground flex h-full flex-col items-center justify-center gap-2 p-8 text-sm">
                    <ShoppingCart className="size-8" />
                    Keranjang kosong. Pilih produk atau scan barcode.
                  </li>
                )}
                {cart.lines.map((line) => (
                  <li key={line.key}>
                    <button
                      type="button"
                      onClick={() => {
                        dispatch({ type: 'select', key: line.key })
                        buffer.current = { key: null, mode, value: '' }
                      }}
                      className={cn('w-full px-3 py-2 text-left', line.key === cart.selectedKey && 'bg-accent')}
                    >
                      <div className="flex justify-between gap-2">
                        <span className="font-medium">{line.product.name}</span>
                        <span className="font-semibold tabular-nums">{formatMoney(round(line.quantity * line.unit_price * (1 - line.discount / 100)))}</span>
                      </div>
                      <div className="text-muted-foreground text-xs tabular-nums">
                        {formatNumber(line.quantity)} {line.product.uom} x {formatMoney(line.unit_price)}
                        {line.discount > 0 && ` · diskon ${formatNumber(line.discount)}%`}
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
              <div className="border-t p-3">
                <div className="mb-2 flex items-baseline justify-between">
                  <span className="text-muted-foreground text-sm">{isRefund ? 'Total retur' : 'Total'}</span>
                  <span className="text-2xl font-bold tabular-nums" data-testid="cart-total">
                    {formatMoney(totals.total)}
                  </span>
                </div>
                <div className="grid grid-cols-[1fr_auto] gap-2">
                  <Numpad
                    onKey={pressNumpad}
                    modes={isRefund ? [numpadModes[0]] : numpadModes}
                    mode={mode}
                    onMode={(m) => {
                      setMode(m as NumpadMode)
                      buffer.current = { key: null, mode: m as NumpadMode, value: '' }
                    }}
                  />
                  <div className="grid w-28 grid-rows-[auto_1fr] gap-2">
                    <Button variant="outline" className="h-12 flex-col gap-0 text-xs" onClick={() => setDialog('customer')}>
                      <User />
                      <span className="max-w-24 truncate">{cart.partner?.name ?? 'Pelanggan'}</span>
                    </Button>
                    <Button className="h-full text-base" disabled={cart.lines.length === 0} onClick={() => setScreen('payment')}>
                      {isRefund ? 'Kembalikan' : 'Bayar'}
                    </Button>
                  </div>
                </div>
              </div>
            </section>

            {/* Produk */}
            <section className="order-1 flex min-h-0 flex-col gap-2 p-3 lg:order-2" aria-label="Produk">
              <div className="relative">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                <Input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Cari produk / scan barcode…"
                  className="bg-card pl-8"
                  aria-label="Cari produk"
                />
              </div>
              {categories.length > 1 && (
                <div className="flex gap-1.5 overflow-x-auto pb-1">
                  {[[null, 'Semua'] as const, ...categories].map(([catId, label]) => (
                    <button
                      key={catId ?? 'all'}
                      type="button"
                      onClick={() => setCategory(catId)}
                      className={cn(
                        'shrink-0 rounded-full border px-3 py-1 text-sm',
                        category === catId ? 'bg-primary text-primary-foreground border-primary' : 'bg-card',
                      )}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              )}
              <div className="grid min-h-0 flex-1 auto-rows-min grid-cols-[repeat(auto-fill,minmax(130px,1fr))] gap-2 overflow-y-auto" data-testid="product-grid">
                {visible.map((product) => (
                  <button
                    key={product.id}
                    type="button"
                    onClick={() => addProduct(product)}
                    className="bg-card hover:border-primary flex min-h-24 flex-col justify-between rounded-lg border p-2.5 text-left shadow-xs transition-colors active:scale-[0.98]"
                  >
                    <span className="line-clamp-2 text-sm font-medium">{product.name}</span>
                    <span className="text-primary mt-1 text-sm font-semibold tabular-nums">{formatMoney(product.price)}</span>
                  </button>
                ))}
                {visible.length === 0 && <p className="text-muted-foreground col-span-full p-8 text-center text-sm">Produk tidak ditemukan.</p>}
              </div>
            </section>
          </div>
        )}

        {screen === 'payment' && (
          <PaymentScreen
            total={totals.total}
            methods={data.payment_methods}
            partnerName={cart.partner?.name ?? null}
            toInvoice={cart.toInvoice}
            isRefund={isRefund}
            submitting={submit.isPending}
            onToggleInvoice={() => {
              if (!cart.partner && !cart.toInvoice) {
                toast.info('Pilih pelanggan untuk faktur')
                setDialog('customer')
              }
              dispatch({ type: 'toInvoice', value: !cart.toInvoice })
            }}
            onChooseCustomer={() => setDialog('customer')}
            onBack={() => setScreen('products')}
            onValidate={validate}
          />
        )}

        {screen === 'receipt' && receipt && (
          <div className="flex h-full flex-col items-center gap-4 overflow-y-auto p-6">
            <div className="text-center">
              <div className="text-muted-foreground text-sm">{receipt.is_refund ? 'Retur selesai' : 'Pembayaran berhasil'}</div>
              {Number(receipt.amount_return) > 0 && (
                <div className="text-3xl font-bold text-emerald-600 tabular-nums" data-testid="receipt-change">
                  Kembalian {formatMoney(receipt.amount_return)}
                </div>
              )}
            </div>
            <Receipt order={receipt} companyName={company?.name ?? data.config.name} header={data.config.receipt_header} footer={data.config.receipt_footer} />
            <div className="flex gap-2">
              <Button variant="outline" size="lg" onClick={() => window.print()}>
                Cetak struk
              </Button>
              <Button
                size="lg"
                onClick={() => {
                  setReceipt(null)
                  setScreen('products')
                }}
                autoFocus
              >
                Pesanan baru
              </Button>
            </div>
          </div>
        )}

        {screen === 'orders' && (
          <OrdersScreen
            configId={data.config.id}
            onBack={() => setScreen('products')}
            onReprint={(order) => {
              setReceipt(order)
              setScreen('receipt')
            }}
            onRefund={loadRefund}
          />
        )}
      </main>

      {dialog === 'customer' && (
        <CustomerDialog current={cart.partner} onSelect={(partner) => dispatch({ type: 'partner', partner })} onClose={() => setDialog(null)} />
      )}
      {dialog === 'cash' && <CashMoveDialog sessionId={sessionId} onClose={() => setDialog(null)} />}
      {dialog === 'close' && (
        <CloseSessionDialog
          sessionId={sessionId}
          onClose={() => setDialog(null)}
          onClosed={() => {
            dispatch({ type: 'clear' })
            navigate('/pos')
          }}
        />
      )}
    </div>
  )
}
