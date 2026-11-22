<?php

namespace Modules\Pos\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasAudit;

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK = 'bank';

    public const TYPES = [self::TYPE_CASH, self::TYPE_BANK];

    protected $table = 'pos_payment_methods';

    protected $fillable = ['name', 'type', 'sequence', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sequence' => 'integer'];
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }
}
