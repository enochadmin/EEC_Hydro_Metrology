<?php

namespace App\Http\Requests\Basins;

use App\Models\Basin;

class UpdateBasinRequest extends BasinRequest
{
    public function authorize(): bool
    {
        $basin = $this->route('basin');

        return $basin instanceof Basin
            && ($this->user()?->can('update', $basin) ?? false);
    }
}
