<?php

namespace App\Http\Requests\Basins;

use App\Models\SubBasin;

class StoreSubBasinRequest extends SubBasinRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SubBasin::class) ?? false;
    }
}
