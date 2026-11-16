<?php

namespace App\Http\Requests\Basins;

use App\Models\Basin;

class StoreBasinRequest extends BasinRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Basin::class) ?? false;
    }
}
