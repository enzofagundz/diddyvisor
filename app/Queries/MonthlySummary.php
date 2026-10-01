<?php

namespace App\Queries;

use App\Enums\BillStatus;
use App\Models\House;
use Illuminate\Support\Facades\DB;

class MonthlySummary
{
    public function __invoke(House $house, string $month): array
    {
        $bills = $house->bills()->where('competence', $month.'-01');
        $overview = (clone $bills)->selectRaw('COUNT(*) AS count, COALESCE(SUM(total_cents),0) AS total, COUNT(*) FILTER (WHERE status = 2) AS paid, COUNT(*) FILTER (WHERE status <> 2) AS pending')->first();
        $shares = DB::table('bill_shares')->join('bills', 'bills.id', '=', 'bill_shares.bill_id')->where('bills.house_id', $house->id)->where('bills.competence', $month.'-01');
        $totals = (clone $shares)->selectRaw('COALESCE(SUM(CASE WHEN is_paid THEN amount_cents ELSE 0 END),0) AS paid, COALESCE(SUM(CASE WHEN NOT is_paid THEN amount_cents ELSE 0 END),0) AS pending')->first();
        $byMember = (clone $shares)->selectRaw('membership_id, SUM(amount_cents) AS total, SUM(CASE WHEN is_paid THEN amount_cents ELSE 0 END) AS paid, SUM(CASE WHEN NOT is_paid THEN amount_cents ELSE 0 END) AS pending')->groupBy('membership_id')->get();
        $members = $house->memberships()->with('user')->whereIn('id', $byMember->pluck('membership_id'))->get()->keyBy('id');

        return ['overview' => $overview, 'totals' => $totals, 'upcoming' => (clone $bills)->where('status', '!=', BillStatus::Paid)->orderBy('due_date')->orderBy('id')->limit(5)->get(), 'members' => $byMember->map(fn ($row) => ['membership_id' => $row->membership_id, 'name' => $members[$row->membership_id]->label(), 'total' => $row->total, 'paid' => $row->paid, 'pending' => $row->pending])];
    }
}
