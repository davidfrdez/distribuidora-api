<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parentId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'displayOrder' => $this->displayOrder,
            'active' => $this->active,
            'children' => self::collection($this->whenLoaded('children')),
            'productsCount' => $this->whenCounted('products'),
        ];
    }
}
