import type { ReactNode } from 'react'
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { Page } from '@/lib/pagination'
import { cn } from '@/lib/utils'

export type Column<T> = {
  key: string
  header: string
  cell: (row: T) => ReactNode
  /** Nama kolom sort di API; kalau diisi, header bisa diklik. */
  sortKey?: string
  className?: string
  /** Sembunyikan di layar kecil. */
  hideOnMobile?: boolean
}

type DataTableProps<T> = {
  columns: Column<T>[]
  page?: Page<T>
  rows?: T[]
  isLoading?: boolean
  rowKey: (row: T) => string | number
  onRowClick?: (row: T) => void
  sort?: string
  direction?: 'asc' | 'desc'
  onSortChange?: (sort: string, direction: 'asc' | 'desc') => void
  onPageChange?: (page: number) => void
  empty?: ReactNode
}

/**
 * Tabel list standar: sort di server, paginasi, baris bisa diklik (seperti list view Odoo).
 */
export function DataTable<T>({
  columns,
  page,
  rows,
  isLoading,
  rowKey,
  onRowClick,
  sort,
  direction = 'asc',
  onSortChange,
  onPageChange,
  empty = 'Belum ada data.',
}: DataTableProps<T>) {
  const data = rows ?? page?.data ?? []
  const meta = page?.meta

  function toggleSort(key: string) {
    if (!onSortChange) return
    onSortChange(key, sort === key && direction === 'asc' ? 'desc' : 'asc')
  }

  return (
    <div className="bg-card rounded-lg border">
      <Table>
        <TableHeader>
          <TableRow className="hover:bg-transparent">
            {columns.map((col) => (
              <TableHead key={col.key} className={cn(col.hideOnMobile && 'hidden md:table-cell', col.className)}>
                {col.sortKey && onSortChange ? (
                  <button
                    type="button"
                    onClick={() => toggleSort(col.sortKey!)}
                    className="hover:text-foreground inline-flex items-center gap-1 uppercase"
                  >
                    {col.header}
                    {sort === col.sortKey &&
                      (direction === 'asc' ? <ArrowUp className="size-3" /> : <ArrowDown className="size-3" />)}
                  </button>
                ) : (
                  col.header
                )}
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading &&
            Array.from({ length: 5 }).map((_, i) => (
              <TableRow key={i}>
                {columns.map((col) => (
                  <TableCell key={col.key} className={cn(col.hideOnMobile && 'hidden md:table-cell')}>
                    <Skeleton className="h-4 w-24" />
                  </TableCell>
                ))}
              </TableRow>
            ))}

          {!isLoading && data.length === 0 && (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columns.length} className="text-muted-foreground py-12 text-center">
                {empty}
              </TableCell>
            </TableRow>
          )}

          {!isLoading &&
            data.map((row) => (
              <TableRow
                key={rowKey(row)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={cn(onRowClick && 'cursor-pointer')}
              >
                {columns.map((col) => (
                  <TableCell key={col.key} className={cn(col.hideOnMobile && 'hidden md:table-cell', col.className)}>
                    {col.cell(row)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
        </TableBody>
      </Table>

      {meta && meta.total > 0 && onPageChange && (
        <div className="text-muted-foreground flex items-center justify-between gap-2 border-t px-3 py-2 text-sm">
          <span>
            {meta.from}–{meta.to} dari {meta.total}
          </span>
          <div className="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              disabled={meta.current_page <= 1}
              onClick={() => onPageChange(meta.current_page - 1)}
              aria-label="Halaman sebelumnya"
            >
              <ChevronLeft />
            </Button>
            <span className="tabular-nums">
              {meta.current_page} / {meta.last_page}
            </span>
            <Button
              variant="ghost"
              size="icon"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => onPageChange(meta.current_page + 1)}
              aria-label="Halaman berikutnya"
            >
              <ChevronRight />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
