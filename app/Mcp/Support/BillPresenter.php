<?php

namespace App\Mcp\Support;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\BillShare;
use App\Support\Money;

/**
 * Serializa contas e partes no contrato do MCP: valores em centavos e em
 * decimal pt-BR, status como string estável.
 */
final class BillPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Bill $bill): array
    {
        return [
            'id' => $bill->id,
            'name' => $bill->name,
            'month' => $bill->competence->format('Y-m'),
            'due_date' => $bill->due_date->toDateString(),
            'total' => Money::decimal($bill->total_cents),
            'total_cents' => $bill->total_cents,
            'status' => self::status($bill->status),
            'overdue' => $bill->isOverdue(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Bill $bill): array
    {
        return [
            ...self::summary($bill),
            'shares' => $bill->shares
                ->map(fn (BillShare $share): array => self::share($share))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function share(BillShare $share): array
    {
        return [
            'membership_id' => $share->membership_id,
            'name' => $share->membership->label(),
            'amount' => Money::decimal($share->amount_cents),
            'amount_cents' => $share->amount_cents,
            'is_paid' => $share->is_paid,
        ];
    }

    public static function status(BillStatus $status): string
    {
        return match ($status) {
            BillStatus::Pending => 'pending',
            BillStatus::Partial => 'partial',
            BillStatus::Paid => 'paid',
        };
    }

    public static function statusFromLabel(string $label): ?BillStatus
    {
        return match ($label) {
            'pending' => BillStatus::Pending,
            'partial' => BillStatus::Partial,
            'paid' => BillStatus::Paid,
            default => null,
        };
    }
}
