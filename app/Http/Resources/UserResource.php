<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'whatsapp_number'     => $this->whatsapp_number,
            'email_verified'      => $this->email_verified_at !== null,
            'whatsapp_verified'   => $this->whatsapp_verified_at !== null,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
