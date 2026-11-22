<?php

namespace App\Core\Extensions;

use Closure;

/**
 * Builder untuk ModuleProvider::extendModel().
 */
class ModelExtension
{
    /**
     * @param  class-string  $model
     */
    public function __construct(
        private readonly ModelExtensions $registry,
        private readonly string $model,
        private readonly string $module,
    ) {}

    /**
     * @param  array|Closure  $rules  aturan validasi Laravel, mis. ['nullable', 'string', 'max:30'],
     *                                atau closure yang mengembalikan array (untuk aturan yang bergantung pada request)
     * @param  string|null  $cast  cast Eloquent, mis. 'date', 'boolean', 'decimal:2'
     */
    public function field(string $name, array|Closure $rules = ['nullable'], ?string $cast = null, ?string $label = null): static
    {
        $this->registry->addField($this->model, $this->module, $name, $rules, $cast, $label);

        return $this;
    }
}
