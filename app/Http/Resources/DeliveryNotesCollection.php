<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNotesCollection extends JsonResource
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
            'dn_number' => $this->dn_number,
            'remarks'=>$this->remarks,
            'invoice'=>$this->invoice,
            'part_items'=>$this->partItems,
            'created_at'=>$this->created_at
        ];
    }
}
