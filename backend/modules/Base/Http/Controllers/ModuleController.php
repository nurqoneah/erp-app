<?php

namespace Modules\Base\Http\Controllers;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ModuleController extends Controller
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * Semua modul beserta statusnya (halaman "Apps").
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->modules->all()->map(fn (Module $m) => [
                'name' => $m->name,
                'slug' => $m->slug,
                'label' => $m->label,
                'description' => $m->description,
                'version' => $m->version,
                'icon' => $m->icon,
                'depends' => $m->depends,
                'required' => $m->required,
                'enabled' => $this->modules->isEnabled($m->name),
            ])->values(),
        ]);
    }

    /**
     * Menu aplikasi untuk user yang login: hanya modul aktif & item yang boleh diakses.
     */
    public function menu(Request $request): JsonResponse
    {
        $user = $request->user();

        $menu = $this->modules->enabled()
            ->map(fn (Module $m) => [
                'module' => $m->name,
                'slug' => $m->slug,
                'label' => $m->label,
                'icon' => $m->icon,
                'items' => collect($m->menu)
                    ->filter(fn (array $item) => empty($item['permission']) || $user->can($item['permission']))
                    ->map(fn (array $item) => ['label' => $item['label'], 'path' => $item['path']])
                    ->values(),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();

        return response()->json(['data' => $menu]);
    }

    public function enable(string $module): JsonResponse
    {
        $this->authorize('base.module.manage');
        abort_unless($this->modules->find($module), 404, 'Modul tidak ditemukan.');

        try {
            $activated = $this->modules->enable($module);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Modul diaktifkan.', 'activated' => $activated]);
    }

    public function disable(string $module): JsonResponse
    {
        $this->authorize('base.module.manage');
        abort_unless($this->modules->find($module), 404, 'Modul tidak ditemukan.');

        try {
            $this->modules->disable($module);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Modul dinonaktifkan.']);
    }
}
