<?php

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contacts\Models\Partner;

/** @mixin Partner */
class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'display_name' => $this->parent ? $this->parent->name.', '.$this->name : $this->name,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent
                ? ['id' => $this->parent->id, 'name' => $this->parent->name]
                : null),
            'ref' => $this->ref,
            'job_title' => $this->job_title,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
            'is_customer' => $this->is_customer,
            'is_vendor' => $this->is_vendor,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'contacts' => PartnerResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
