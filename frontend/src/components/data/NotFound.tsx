import { Link } from 'react-router'

import { Button } from '@/components/ui/button'

export function NotFound({ backTo }: { backTo: string }) {
  return (
    <div className="px-4 py-24 text-center">
      <h1 className="text-xl font-semibold">Data tidak ditemukan</h1>
      <p className="text-muted-foreground mt-1 text-sm">Data ini mungkin sudah dihapus.</p>
      <Button asChild variant="outline" className="mt-6">
        <Link to={backTo}>Kembali ke daftar</Link>
      </Button>
    </div>
  )
}
