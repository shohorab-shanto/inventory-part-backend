<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_group' => $this->company_group,
            'factory_types' => $this->machine_types,
            'logo' => $this->logo_url,
            // 'status' => $this->contracts()->active()->count() ?? false,
            'trade_limit' => $this->trade_limit,
            'due_amount' => $this->due_amount,
            'status'=> $this->contracts()->active()->count(),
        ];
    }
}
