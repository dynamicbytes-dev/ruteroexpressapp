<?php

namespace App\Http\Resources;

use App\Models\Recharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recarga vista por su dueño. El comprobante no se expone: es privado y solo
 * lo abre el auditor desde el panel.
 *
 * @mixin Recharge
 */
class RechargeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_bs' => (float) $this->amount_bs,
            'exchange_rate' => $this->exchange_rate === null ? null : (float) $this->exchange_rate,
            'amount_usd' => $this->amount_usd === null ? null : (float) $this->amount_usd,
            'bank' => $this->bank,
            'reference' => $this->reference,
            'transferred_at' => $this->transferred_at?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
