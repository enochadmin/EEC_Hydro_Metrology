<?php

namespace App\Http\Requests\Basins;

use App\Models\SubBasin;

class UpdateSubBasinRequest extends SubBasinRequest
{
    public function authorize(): bool
    {
        $subBasin = $this->route('subBasin');

        return $subBasin instanceof SubBasin
            && ($this->user()?->can('update', $subBasin) ?? false);
    }
}
