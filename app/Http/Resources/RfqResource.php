<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'delivery_deadline' => $this->delivery_deadline,
            'bidding_deadline' => $this->bidding_deadline,
            'terms_conditions' => $this->terms_conditions,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'email' => $this->company->email,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'quantity' => $item->quantity,
                        'specifications' => $item->specifications,
                        'notes' => $item->notes,
                        'item' => [
                            'id' => $item->item->id,
                            'name' => $item->item->name,
                            'description' => $item->item->description,
                            'unit' => $item->item->unit,
                        ],
                    ];
                });
            }),
            'suppliers' => $this->whenLoaded('suppliers', function () {
                return $this->suppliers->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'email' => $supplier->email,
                    ];
                });
            }),
            'bids' => $this->whenLoaded('bids', function () {
                return $this->bids->map(function ($bid) {
                    return [
                        'id' => $bid->id,
                        'total_amount' => $bid->total_amount,
                        'status' => $bid->status,
                        'submitted_at' => $bid->submitted_at,
                        'supplier_company' => [
                            'id' => $bid->supplierCompany->id,
                            'name' => $bid->supplierCompany->name,
                        ],
                    ];
                });
            }),
        ];
    }
}
