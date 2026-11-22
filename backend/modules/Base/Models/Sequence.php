<?php

namespace Modules\Base\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Penomoran dokumen, mis. SO/2026/0001. Ambil nomor lewat SequenceService::next().
 */
class Sequence extends Model
{
    use HasAudit;

    public const RESET_NEVER = 'never';

    public const RESET_YEARLY = 'yearly';

    public const RESET_MONTHLY = 'monthly';

    public const RESETS = [self::RESET_NEVER, self::RESET_YEARLY, self::RESET_MONTHLY];

    protected $fillable = [
        'code', 'name', 'module', 'prefix', 'suffix', 'padding', 'next_number', 'reset', 'last_reset_key',
    ];

    protected function casts(): array
    {
        return ['padding' => 'integer', 'next_number' => 'integer'];
    }

    public function resetKey(Carbon $date): string
    {
        return match ($this->reset) {
            self::RESET_YEARLY => $date->format('Y'),
            self::RESET_MONTHLY => $date->format('Y-m'),
            default => '',
        };
    }

    /** Nomor yang akan dihasilkan untuk $number pada tanggal $date. */
    public function format(int $number, Carbon $date): string
    {
        $placeholders = [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
        ];

        return strtr($this->prefix, $placeholders)
            .str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT)
            .strtr($this->suffix, $placeholders);
    }

    /** Pratinjau nomor berikutnya (tanpa memakai nomor). */
    public function preview(?Carbon $date = null): string
    {
        $date ??= Carbon::now();
        $number = $this->last_reset_key !== null && $this->resetKey($date) !== $this->last_reset_key
            ? 1
            : $this->next_number;

        return $this->format($number, $date);
    }
}
