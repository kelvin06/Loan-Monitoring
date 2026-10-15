<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate' => $this->when(isset($this->rate), $this->rate),
            'penalty_option' => $this->when(isset($this->penalty_option), $this->penalty_option),
            'description' => $this->when(isset($this->description), $this->description),
            'created_at' => $this->created_at,
        ];
    }
}
