<?php

namespace App\Http\Resources;

use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retiro visto por el conductor. El comprobante del pago lo sube el auditor y
 * queda en el panel; aquí solo se informa el estado.
 *
 * @mixin Withdrawal
 */
class WithdrawalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_usd' => (float) $this->amount_usd,
            'bank' => $this->bank,
            'account_number' => $this->account_number,
            'account_holder' => $this->account_holder,
            'account_id' => $this->account_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
