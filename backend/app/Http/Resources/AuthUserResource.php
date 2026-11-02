<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public static $wrap = 'user';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->sort()->values()->all(),
            'permissions' => collect(Permission::cases())
                ->filter(fn (Permission $permission): bool => $this->resource->can($permission->value))
                ->map(fn (Permission $permission): string => $permission->value)
                ->sort()->values()->all(),
        ];
    }
}
