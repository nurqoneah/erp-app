<?php

namespace Modules\Invoicing\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Invoicing\Http\Requests\InvoiceRequest;
use Modules\Invoicing\Http\Resources\InvoiceResource;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Models\InvoicePayment;
use Modules\Invoicing\Services\InvoiceService;

class InvoiceController extends Controller
{
    private const RELATIONS = ['partner', 'lines.product.uom', 'payments'];

    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Filter: ?type=out_invoice|in_invoice, ?state=, ?payment_state=, ?partner_id=, ?origin_type=&origin_id=, ?search=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()
            ->select('invoices.*')
            ->leftJoin('partners', 'partners.id', '=', 'invoices.partner_id')
            ->with('partner');

        foreach (['type', 'state', 'payment_state', 'partner_id'] as $filter) {
            $query->when($request->query($filter), fn ($q, $value) => $q->where("invoices.{$filter}", $value));
        }
        $query->when($request->query('origin_type'), fn ($q, $type) => $q->where('origin_type', $type)->where('origin_id', $request->query('origin_id')));

        return InvoiceResource::collection(ListQuery::paginate(
            $query, $request,
            ['invoices.number', 'invoices.origin', 'invoices.vendor_reference', 'partners.name'],
            ['invoices.invoice_date', 'invoices.due_date', 'invoices.number', 'invoices.amount_total', 'partners.name'],
            'invoices.id', 'desc',
        ));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(self::RELATIONS));
    }

    public function store(InvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $invoice = $this->invoices->create($data['type'], $data['partner_id'], $data['lines'], $data);

        return (new InvoiceResource($invoice->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $data = $request->validated();
        $this->invoices->updateDraft($invoice, $data, $data['lines']);

        return new InvoiceResource($invoice->refresh()->load(self::RELATIONS));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoices->delete($invoice);

        return response()->json(['message' => 'Faktur draf dihapus.']);
    }

    public function post(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->post($invoice)->load(self::RELATIONS));
    }

    public function cancel(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->cancel($invoice)->load(self::RELATIONS));
    }

    public function resetToDraft(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->resetToDraft($invoice)->load(self::RELATIONS));
    }

    public function registerPayment(Request $request, Invoice $invoice): InvoiceResource
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'method' => ['required', Rule::in(InvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], ['amount' => 'jumlah', 'date' => 'tanggal', 'method' => 'metode']);

        $this->invoices->registerPayment($invoice, (float) $data['amount'], $data['date'], $data['method'], $data['reference'] ?? null);

        return new InvoiceResource($invoice->refresh()->load(self::RELATIONS));
    }
}
