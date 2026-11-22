<?php

namespace Modules\Base\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Base\Models\Company;

class CompanyController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => Company::current()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:16'],
            'country' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
        ], [], ['name' => 'nama perusahaan', 'tax_id' => 'NPWP', 'currency' => 'mata uang']);

        $company = Company::current();
        $company->update($data);

        return response()->json(['data' => $company]);
    }
}
