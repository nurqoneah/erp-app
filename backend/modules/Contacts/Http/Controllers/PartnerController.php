<?php

namespace Modules\Contacts\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Contacts\Http\Requests\PartnerRequest;
use Modules\Contacts\Http\Resources\PartnerResource;
use Modules\Contacts\Models\Partner;

class PartnerController extends Controller
{
    /**
     * Filter: ?search=, ?type=company|person, ?role=customer|vendor, ?active=1|0|all, ?parent_id=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Partner::query()->with('parent');
        ListQuery::filterActive($query, $request);

        $query->when($request->query('type'), fn ($q, $type) => $q->where('type', $type));
        $query->when($request->query('parent_id'), fn ($q, $id) => $q->where('parent_id', $id));
        $query->when($request->query('role'), fn ($q, $role) => match ($role) {
            'customer' => $q->where('is_customer', true),
            'vendor' => $q->where('is_vendor', true),
            default => $q,
        });

        return PartnerResource::collection(ListQuery::paginate(
            $query,
            $request,
            ['name', 'email', 'phone', 'mobile', 'ref', 'city', 'tax_id'],
            ['name', 'email', 'city', 'created_at'],
            'name',
        ));
    }

    public function show(Partner $partner): PartnerResource
    {
        return new PartnerResource($partner->load(['parent', 'contacts' => fn ($q) => $q->orderBy('name')]));
    }

    public function store(PartnerRequest $request): JsonResponse
    {
        $partner = Partner::query()->create($this->normalize($request->validated()));

        return (new PartnerResource($partner->load('parent')))->response()->setStatusCode(201);
    }

    public function update(PartnerRequest $request, Partner $partner): PartnerResource
    {
        $data = $this->normalize($request->validated());

        if ($partner->isCompany() && $data['type'] === Partner::TYPE_PERSON && $partner->contacts()->exists()) {
            abort(422, 'Perusahaan ini masih punya kontak di bawahnya, jadi tipenya tidak bisa diubah.');
        }

        $partner->update($data);

        return new PartnerResource($partner->load('parent'));
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(['message' => 'Kontak dihapus.']);
    }

    private function normalize(array $data): array
    {
        if ($data['type'] === Partner::TYPE_COMPANY) {
            $data['parent_id'] = null;
            $data['job_title'] = null;
        }

        return $data;
    }
}
