<?php

namespace Modules\Base\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Base\Models\Sequence;
use RuntimeException;

class SequenceService
{
    /**
     * Ambil nomor dokumen berikutnya secara aman untuk transaksi bersamaan (row lock).
     */
    public function next(string $code, ?Carbon $date = null): string
    {
        $date ??= Carbon::now();

        return DB::transaction(function () use ($code, $date) {
            /** @var Sequence|null $sequence */
            $sequence = Sequence::query()->where('code', $code)->lockForUpdate()->first();

            if (! $sequence) {
                throw new RuntimeException("Penomoran [{$code}] belum dibuat. Pastikan modulnya aktif.");
            }

            $key = $sequence->resetKey($date);
            if ($sequence->last_reset_key !== null && $sequence->last_reset_key !== $key) {
                $sequence->next_number = 1;
            }

            $number = $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->last_reset_key = $key;
            $sequence->saveQuietly();

            return $sequence->format($number, $date);
        });
    }
}
