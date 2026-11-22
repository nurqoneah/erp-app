<?php

namespace App\Core\Extensions;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry field tambahan dari modul lain untuk sebuah model.
 *
 * Model yang memakai trait `Extensible` otomatis menerima field ini (fillable + cast);
 * FormRequest dan API Resource modul pemilik model menggabungkan aturan validasi & nilainya.
 */
class ModelExtensions
{
    /** @var array<class-string, array<string, array{module: string, rules: array|Closure, cast: ?string, label: ?string}>> */
    private array $fields = [];

    /**
     * @param  class-string  $model
     */
    public function addField(string $model, string $module, string $name, array|Closure $rules, ?string $cast, ?string $label): void
    {
        $this->fields[$model][$name] = ['module' => $module, 'rules' => $rules, 'cast' => $cast, 'label' => $label];
    }

    /**
     * @param  class-string  $model
     * @return array<string, array{module: string, rules: array|Closure, cast: ?string, label: ?string}>
     */
    public function fields(string $model): array
    {
        return $this->fields[$model] ?? [];
    }

    /** Aturan validasi tambahan untuk FormRequest (closure dievaluasi per request). */
    public function rules(string $model): array
    {
        return array_map(
            fn (array $f) => $f['rules'] instanceof Closure ? ($f['rules'])() : $f['rules'],
            $this->fields($model),
        );
    }

    /** Label field untuk pesan validasi. */
    public function attributes(string $model): array
    {
        return array_filter(array_map(fn (array $f) => $f['label'], $this->fields($model)));
    }

    /** Nilai field tambahan untuk API Resource. */
    public function values(Model $record): array
    {
        $fields = $this->fields($record::class);
        if (! $fields) {
            return [];
        }

        // Pakai bentuk serialisasi agar format cast (mis. 'date:Y-m-d') ikut berlaku.
        $serialized = $record->attributesToArray();
        $values = [];
        foreach (array_keys($fields) as $name) {
            $values[$name] = $serialized[$name] ?? null;
        }

        return $values;
    }

    /** Hapus semua field milik modul (dipanggil saat modul di-uninstall). */
    public function forgetModule(string $module): void
    {
        foreach ($this->fields as $model => $fields) {
            $this->fields[$model] = array_filter($fields, fn (array $f) => $f['module'] !== $module);
        }
    }
}
