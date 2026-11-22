<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Resources\PosSessionResource;
use Modules\Pos\Models\PosConfig;
use Modules\Pos\Services\PosService;

class PosConfigController extends Controller
{
    public function __construct(private readonly PosService $pos) {}

    /**
     * Daftar POS untuk dasbor: status sesi aktif, kas akhir terakhir, dan penjualan sesi aktif.
     */
    public function index(Request $request): JsonResponse
    {
        $configs = PosConfig::query()
            ->with(['paymentMethods', 'warehouse', 'currentSession.user', 'lastClosedSession'])
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $configs->map(fn (PosConfig $c) => $this->present($c))]);
    }

    public function show(PosConfig $config): JsonResponse
    {
        return response()->json(['data' => $this->present($config->load(['paymentMethods', 'warehouse', 'currentSession.user', 'lastClosedSession']))]);
    }

    public function store(Request $request): JsonResponse
    {
        $config = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $config = PosConfig::query()->create($data);
            $config->paymentMethods()->sync($data['payment_method_ids']);

            return $config;
        });

        return $this->show($config)->setStatusCode(201);
    }

    public function update(Request $request, PosConfig $config): JsonResponse
    {
        DB::transaction(function () use ($request, $config) {
            $data = $this->validated($request);
            $config->update($data);
            $config->paymentMethods()->sync($data['payment_method_ids']);
        });

        return $this->show($config->refresh());
    }

    /** Buka sesi baru (Kontrol Pembukaan) atau kembalikan sesi yang sedang berjalan. */
    public function open(Request $request, PosConfig $config): JsonResponse
    {
        $session = $this->pos->openSession($config, $request->user());

        return response()->json(['data' => new PosSessionResource($session->load(['config', 'user']))]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')],
            'payment_method_ids' => ['required', 'array', 'min:1'],
            'payment_method_ids.*' => [Rule::exists('pos_payment_methods', 'id')],
            'receipt_header' => ['nullable', 'string', 'max:1000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'set_maximum_difference' => ['boolean'],
            'amount_authorized_diff' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nama', 'warehouse_id' => 'gudang', 'payment_method_ids' => 'metode pembayaran']);
    }

    private function present(PosConfig $config): array
    {
        $session = $config->currentSession;

        return [
            'id' => $config->id,
            'name' => $config->name,
            'warehouse' => ['id' => $config->warehouse->id, 'name' => $config->warehouse->name],
            'payment_methods' => $config->paymentMethods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'type' => $m->type]),
            'receipt_header' => $config->receipt_header,
            'receipt_footer' => $config->receipt_footer,
            'set_maximum_difference' => $config->set_maximum_difference,
            'amount_authorized_diff' => $config->amount_authorized_diff,
            'is_active' => $config->is_active,
            'cash_control' => $config->cashControl(),
            'current_session' => $session ? [
                'id' => $session->id,
                'name' => $session->name,
                'state' => $session->state,
                'user' => $session->user->name,
                'opened_at' => $session->opened_at,
            ] : null,
            'last_closing' => $config->lastClosedSession ? [
                'closed_at' => $config->lastClosedSession->closed_at,
                'cash' => $config->lastClosedSession->cash_register_balance_end_real,
            ] : null,
        ];
    }
}
