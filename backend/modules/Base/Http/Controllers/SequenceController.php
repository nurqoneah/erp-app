<?php

namespace Modules\Base\Http\Controllers;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Base\Models\Sequence;

class SequenceController extends Controller
{
    public function index(ModuleManager $modules): JsonResponse
    {
        $enabled = $modules->enabledNames();

        $sequences = Sequence::query()
            ->where(fn ($q) => $q->whereNull('module')->orWhereIn('module', $enabled))
            ->orderBy('code')
            ->get()
            ->map(fn (Sequence $s) => $this->present($s, $modules));

        return response()->json(['data' => $sequences]);
    }

    public function update(Request $request, Sequence $sequence, ModuleManager $modules): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'padding' => ['required', 'integer', 'between:1,10'],
            'next_number' => ['required', 'integer', 'min:1'],
            'reset' => ['required', Rule::in(Sequence::RESETS)],
        ], [], ['next_number' => 'nomor berikutnya', 'padding' => 'jumlah digit']);

        $data['prefix'] ??= '';
        $data['suffix'] ??= '';
        $sequence->update($data);

        return response()->json(['data' => $this->present($sequence, $modules)]);
    }

    private function present(Sequence $sequence, ModuleManager $modules): array
    {
        return [
            'id' => $sequence->id,
            'code' => $sequence->code,
            'name' => $sequence->name,
            'module' => $sequence->module,
            'module_label' => $sequence->module ? $modules->find($sequence->module)?->label : null,
            'prefix' => $sequence->prefix,
            'suffix' => $sequence->suffix,
            'padding' => $sequence->padding,
            'next_number' => $sequence->next_number,
            'reset' => $sequence->reset,
            'preview' => $sequence->preview(),
        ];
    }
}
