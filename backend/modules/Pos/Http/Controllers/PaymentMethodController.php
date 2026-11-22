<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Pos\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PaymentMethod::query()->orderBy('sequence')->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => PaymentMethod::query()->create($this->validated($request))], 201);
    }

    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update($this->validated($request));

        return response()->json(['data' => $paymentMethod]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(PaymentMethod::TYPES)],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nama', 'type' => 'tipe']);
    }
}
