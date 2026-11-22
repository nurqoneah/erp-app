/** Tautan ke dokumen asal berdasarkan origin_type dari backend. */
const routes: Record<string, (id: number) => string> = {
  'sales.order': (id) => `/sales/orders/${id}`,
  'purchase.order': (id) => `/purchase/orders/${id}`,
}

export function originLink(type: string | null | undefined, id: number | null | undefined): string | null {
  return type && id && routes[type] ? routes[type](id) : null
}
