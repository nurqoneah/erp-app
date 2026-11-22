<?php

namespace Modules\Inventory\Services;

use App\Core\Support\LineAmounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Base\Services\SequenceService;
use Modules\Inventory\Events\PickingDone;
use Modules\Inventory\Exceptions\InventoryException;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Models\Warehouse;
use Modules\Product\Models\Product;

/**
 * API inventori untuk modul lain dan controller.
 *
 * Hanya produk bertipe "storable" yang stoknya dilacak di stock_quants. Produk "consumable"
 * tetap dicatat perpindahannya, tetapi tidak mengurangi/menambah stok. Jasa tidak bisa dipindahkan.
 */
class InventoryService
{
    public function __construct(private readonly SequenceService $sequences) {}

    public function defaultWarehouse(): Warehouse
    {
        return Warehouse::query()->where('is_active', true)->orderBy('id')->first()
            ?? throw new InventoryException('Belum ada gudang aktif.');
    }

    /**
     * Buat gudang beserta lokasinya: "{KODE}" (view) dan "{KODE}/Stok" (internal).
     */
    public function createWarehouse(string $name, string $code): Warehouse
    {
        return DB::transaction(function () use ($name, $code) {
            $warehouse = Warehouse::query()->create(['name' => $name, 'code' => strtoupper($code)]);
            $view = StockLocation::query()->create([
                'name' => $warehouse->code, 'usage' => StockLocation::USAGE_VIEW, 'warehouse_id' => $warehouse->id,
            ]);
            $stock = StockLocation::query()->create([
                'name' => 'Stok', 'parent_id' => $view->id, 'usage' => StockLocation::USAGE_INTERNAL, 'warehouse_id' => $warehouse->id,
            ]);
            $warehouse->update(['view_location_id' => $view->id, 'stock_location_id' => $stock->id]);

            return $warehouse->load('stockLocation');
        });
    }

    public function virtualLocation(string $usage): StockLocation
    {
        return StockLocation::query()->where('usage', $usage)->whereNull('warehouse_id')->orderBy('id')->first()
            ?? throw new InventoryException("Lokasi virtual [{$usage}] tidak ditemukan.");
    }

    /**
     * Lokasi asal & tujuan default untuk tipe transfer di gudang tertentu.
     *
     * @return array{0: StockLocation, 1: StockLocation}
     */
    public function defaultLocations(string $type, Warehouse $warehouse): array
    {
        $stock = $warehouse->stockLocation ?? throw new InventoryException("Gudang {$warehouse->name} belum punya lokasi stok.");

        return match ($type) {
            Picking::TYPE_RECEIPT => [$this->virtualLocation(StockLocation::USAGE_SUPPLIER), $stock],
            Picking::TYPE_DELIVERY => [$stock, $this->virtualLocation(StockLocation::USAGE_CUSTOMER)],
            default => [$stock, $stock],
        };
    }

    /**
     * Buat transfer draf.
     *
     * @param  list<array{product_id: int, quantity: float|string, origin_line_type?: ?string, origin_line_id?: ?int}>  $lines
     * @param  array{partner_id?: ?int, warehouse_id?: ?int, source_location_id?: ?int, dest_location_id?: ?int,
     *               scheduled_date?: ?string, origin?: ?string, origin_type?: ?string, origin_id?: ?int, notes?: ?string}  $attributes
     */
    public function createPicking(string $type, array $lines, array $attributes = []): Picking
    {
        if (! in_array($type, Picking::TYPES, true)) {
            throw new InventoryException("Tipe transfer [{$type}] tidak dikenal.");
        }

        return DB::transaction(function () use ($type, $lines, $attributes) {
            $warehouse = isset($attributes['warehouse_id'])
                ? Warehouse::query()->findOrFail($attributes['warehouse_id'])
                : $this->defaultWarehouse();
            [$source, $dest] = $this->defaultLocations($type, $warehouse);

            $picking = Picking::query()->create([
                'reference' => $this->sequences->next(Picking::SEQUENCES[$type]),
                'type' => $type,
                'state' => Picking::STATE_DRAFT,
                'warehouse_id' => $warehouse->id,
                'partner_id' => $attributes['partner_id'] ?? null,
                'source_location_id' => $attributes['source_location_id'] ?? $source->id,
                'dest_location_id' => $attributes['dest_location_id'] ?? $dest->id,
                'scheduled_date' => $attributes['scheduled_date'] ?? now()->toDateString(),
                'origin' => $attributes['origin'] ?? null,
                'origin_type' => $attributes['origin_type'] ?? null,
                'origin_id' => $attributes['origin_id'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->replaceMoves($picking, $lines);

            return $picking->load('moves');
        });
    }

    /**
     * Ganti baris transfer draf.
     *
     * @param  list<array{product_id: int, quantity: float|string, origin_line_type?: ?string, origin_line_id?: ?int}>  $lines
     */
    public function replaceMoves(Picking $picking, array $lines): void
    {
        $this->ensureDraft($picking);

        $products = Product::withTrashed()->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        foreach ($lines as $line) {
            $product = $products[$line['product_id']] ?? throw new InventoryException('Produk tidak ditemukan.');
            if ($product->type === Product::TYPE_SERVICE) {
                throw new InventoryException("{$product->name} adalah jasa dan tidak bisa dipindahkan.");
            }
            if (LineAmounts::quantity($line['quantity']) <= 0) {
                throw new InventoryException("Jumlah {$product->name} harus lebih dari 0.");
            }
        }

        $picking->moves()->delete();
        foreach ($lines as $line) {
            $picking->moves()->create([
                'reference' => $picking->reference,
                'product_id' => $line['product_id'],
                'quantity' => LineAmounts::quantity($line['quantity']),
                'source_location_id' => $picking->source_location_id,
                'dest_location_id' => $picking->dest_location_id,
                'state' => Picking::STATE_DRAFT,
                'origin_line_type' => $line['origin_line_type'] ?? null,
                'origin_line_id' => $line['origin_line_id'] ?? null,
            ]);
        }
    }

    /**
     * Validasi transfer: stok berpindah. Gagal bila stok di lokasi asal tidak cukup,
     * kecuali $allowNegative (dipakai POS: seperti Odoo, kasir tidak diblokir walau stok kosong).
     */
    public function validate(Picking $picking, bool $allowNegative = false): Picking
    {
        DB::transaction(function () use ($picking, $allowNegative) {
            $picking = Picking::query()->lockForUpdate()->findOrFail($picking->id);
            $this->ensureDraft($picking);

            $moves = $picking->moves()->with(['product', 'sourceLocation', 'destLocation'])->get();
            if ($moves->isEmpty()) {
                throw new InventoryException('Transfer belum punya baris produk.');
            }
            if ($picking->source_location_id === $picking->dest_location_id) {
                throw new InventoryException('Lokasi asal dan tujuan tidak boleh sama.');
            }

            $this->applyMoves($moves, $allowNegative);

            $picking->update(['state' => Picking::STATE_DONE, 'done_at' => now()]);
        });

        $picking->refresh();
        event(new PickingDone($picking));

        return $picking;
    }

    public function cancel(Picking $picking): Picking
    {
        DB::transaction(function () use ($picking) {
            $this->ensureDraft($picking);
            $picking->moves()->update(['state' => Picking::STATE_CANCELLED]);
            $picking->update(['state' => Picking::STATE_CANCELLED]);
        });

        return $picking->refresh();
    }

    /**
     * Penyesuaian stok (stock opname): set jumlah terhitung per produk di sebuah lokasi internal.
     *
     * @param  array<int, float|string>  $counted  product_id => jumlah terhitung
     * @return Collection<int, StockMove> perpindahan yang dibuat (produk yang jumlahnya tidak berubah dilewati)
     */
    public function adjust(StockLocation $location, array $counted, ?string $reason = null): Collection
    {
        if (! $location->isInternal()) {
            throw new InventoryException('Penyesuaian stok hanya untuk lokasi internal.');
        }

        return DB::transaction(function () use ($location, $counted, $reason) {
            $virtual = $this->virtualLocation(StockLocation::USAGE_INVENTORY);
            $products = Product::query()->whereIn('id', array_keys($counted))->get()->keyBy('id');
            $reference = 'ADJ/'.now()->format('Ymd').($reason ? ' - '.$reason : '');
            $moves = collect();

            foreach ($counted as $productId => $quantity) {
                $product = $products[$productId] ?? throw new InventoryException('Produk tidak ditemukan.');
                if ($product->type !== Product::TYPE_STORABLE) {
                    throw new InventoryException("Stok {$product->name} tidak dilacak (bukan barang stok).");
                }
                $quantity = LineAmounts::quantity($quantity);
                if ($quantity < 0) {
                    throw new InventoryException("Jumlah terhitung {$product->name} tidak boleh negatif.");
                }

                $difference = LineAmounts::quantity($quantity - $this->quantityAt($product->id, $location->id, lock: true));
                if ($difference == 0) {
                    continue;
                }

                $move = StockMove::query()->create([
                    'reference' => $reference,
                    'product_id' => $product->id,
                    'quantity' => abs($difference),
                    'source_location_id' => $difference > 0 ? $virtual->id : $location->id,
                    'dest_location_id' => $difference > 0 ? $location->id : $virtual->id,
                    'state' => Picking::STATE_DRAFT,
                ]);
                $moves->push($move);
            }

            $this->applyMoves($moves->each->load(['product', 'sourceLocation', 'destLocation']));

            return $moves;
        });
    }

    /** Stok produk di satu lokasi. */
    public function quantityAt(int $productId, int $locationId, bool $lock = false): float
    {
        $query = StockQuant::query()->where('product_id', $productId)->where('location_id', $locationId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return (float) ($query->value('quantity') ?? 0);
    }

    /**
     * Stok tersedia per produk di semua lokasi internal (atau satu gudang).
     *
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    public function onHand(array $productIds, ?int $warehouseId = null): array
    {
        return StockQuant::query()
            ->join('stock_locations', 'stock_locations.id', '=', 'stock_quants.location_id')
            ->where('stock_locations.usage', StockLocation::USAGE_INTERNAL)
            ->when($warehouseId, fn ($q) => $q->where('stock_locations.warehouse_id', $warehouseId))
            ->whereIn('stock_quants.product_id', $productIds)
            ->groupBy('stock_quants.product_id')
            ->selectRaw('stock_quants.product_id, sum(stock_quants.quantity) as qty')
            ->pluck('qty', 'product_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }

    /**
     * @param  Collection<int, StockMove>  $moves
     */
    private function applyMoves(Collection $moves, bool $allowNegative = false): void
    {
        $shortages = [];

        foreach ($moves as $move) {
            if ($move->product->type === Product::TYPE_STORABLE) {
                if ($move->sourceLocation->isInternal() && ! $allowNegative) {
                    $available = $this->quantityAt($move->product_id, $move->source_location_id, lock: true);
                    if ($available + 1e-9 < (float) $move->quantity) {
                        $shortages[] = sprintf(
                            '%s (tersedia %s, dibutuhkan %s)',
                            $move->product->name,
                            $this->formatQty($available),
                            $this->formatQty((float) $move->quantity),
                        );

                        continue;
                    }
                }
                if ($move->sourceLocation->isInternal()) {
                    $this->changeQuant($move->product_id, $move->source_location_id, -(float) $move->quantity);
                }
                if ($move->destLocation->isInternal()) {
                    $this->changeQuant($move->product_id, $move->dest_location_id, (float) $move->quantity);
                }
            }
        }

        if ($shortages) {
            throw new InventoryException('Stok tidak cukup di lokasi asal: '.implode('; ', $shortages).'.');
        }

        foreach ($moves as $move) {
            $move->update(['state' => Picking::STATE_DONE, 'done_at' => now()]);
        }
    }

    private function changeQuant(int $productId, int $locationId, float $delta): void
    {
        $quant = StockQuant::query()->where('product_id', $productId)->where('location_id', $locationId)->lockForUpdate()->first()
            ?? new StockQuant(['product_id' => $productId, 'location_id' => $locationId, 'quantity' => 0]);
        $quant->quantity = LineAmounts::quantity((float) $quant->quantity + $delta);
        $quant->save();
    }

    private function ensureDraft(Picking $picking): void
    {
        if (! $picking->isDraft()) {
            throw new InventoryException("Transfer {$picking->reference} sudah {$this->stateLabel($picking->state)}.");
        }
    }

    private function stateLabel(string $state): string
    {
        return [Picking::STATE_DONE => 'selesai', Picking::STATE_CANCELLED => 'dibatalkan'][$state] ?? $state;
    }

    private function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, ',', '.'), '0'), ',');
    }
}
