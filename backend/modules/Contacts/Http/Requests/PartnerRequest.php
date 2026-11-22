<?php

namespace Modules\Contacts\Http\Requests;

use App\Core\Extensions\ModelExtensions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Contacts\Models\Partner;

class PartnerRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Partner|null $partner */
        $partner = $this->route('partner');

        return [
            ...app(ModelExtensions::class)->rules(Partner::class),
            'type' => ['required', Rule::in([Partner::TYPE_COMPANY, Partner::TYPE_PERSON])],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'prohibited_if:type,company',
                Rule::exists('partners', 'id')
                    ->where('type', Partner::TYPE_COMPANY)
                    ->whereNull('deleted_at'),
                Rule::notIn(array_filter([$partner?->id])),
            ],
            'ref' => ['nullable', 'string', 'max:64'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:16'],
            'country' => ['nullable', 'string', 'max:255'],
            'is_customer' => ['boolean'],
            'is_vendor' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            ...app(ModelExtensions::class)->attributes(Partner::class),
            'name' => 'nama',
            'parent_id' => 'perusahaan',
            'tax_id' => 'NPWP',
            'phone' => 'telepon',
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.prohibited_if' => 'Kontak bertipe perusahaan tidak bisa punya perusahaan induk.',
            'parent_id.exists' => 'Perusahaan induk harus kontak bertipe perusahaan.',
        ];
    }
}
