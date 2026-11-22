<?php

namespace Modules\Base\Http\Controllers;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Halaman "Apps": install, upgrade, dan uninstall modul.
 */
class ModuleController extends Controller
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->modules->all()->map(fn (Module $m) => [
                'name' => $m->name,
                'slug' => $m->slug,
                'label' => $m->label,
                'description' => $m->description,
                'category' => $m->category,
                'author' => $m->author,
                'version' => $m->version,
                'installed_version' => $this->modules->installedVersion($m),
                'icon' => $m->icon,
                'depends' => $m->depends,
                'required' => $m->required,
                'state' => $this->modules->state($m),
                'installed' => $this->modules->isInstalled($m->name),
            ])->values(),
        ]);
    }

    /**
     * Menu aplikasi untuk user yang login: hanya modul terpasang & item yang boleh diakses.
     */
    public function menu(Request $request): JsonResponse
    {
        $user = $request->user();

        $menu = $this->modules->installed()
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

    public function install(string $module): JsonResponse
    {
        $this->authorize('base.module.manage');
        $target = $this->findOrFail($module);

        return $this->attempt(function () use ($target) {
            $installed = $this->modules->install($target->name);

            return ['message' => "{$target->label} terpasang.", 'installed' => $installed];
        });
    }

    public function upgrade(string $module): JsonResponse
    {
        $this->authorize('base.module.manage');
        $target = $this->findOrFail($module);

        return $this->attempt(function () use ($target) {
            $this->modules->upgrade($target->name);

            return ['message' => "{$target->label} di-upgrade."];
        });
    }

    /**
     * Modul yang akan ikut dihapus bila modul ini di-uninstall (untuk dialog konfirmasi).
     */
    public function uninstallPlan(string $module): JsonResponse
    {
        $this->authorize('base.module.manage');
        $target = $this->findOrFail($module);

        return $this->attempt(fn () => [
            'data' => array_map(
                fn (Module $m) => ['name' => $m->name, 'label' => $m->label],
                $this->modules->uninstallPlan($target->name),
            ),
        ]);
    }

    public function uninstall(Request $request, string $module): JsonResponse
    {
        $this->authorize('base.module.manage');
        $target = $this->findOrFail($module);

        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Konfirmasi bahwa data modul akan dihapus permanen.',
        ]);

        return $this->attempt(function () use ($target) {
            $removed = $this->modules->uninstall($target->name);

            return ['message' => "{$target->label} di-uninstall.", 'uninstalled' => $removed];
        });
    }

    private function findOrFail(string $name): Module
    {
        $module = $this->modules->find($name);
        abort_unless($module, 404, 'Modul tidak ditemukan.');

        return $module;
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
