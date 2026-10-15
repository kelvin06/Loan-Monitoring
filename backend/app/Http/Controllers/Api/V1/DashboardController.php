<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanLedger;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function admin(): JsonResponse
    {
        $dueLedgers = LoanLedger::query()
            ->with(['loan.user'])
            ->whereNull('payment_date')
            ->whereDate('due_date', '<=', today()->addDays(3))
            ->orderBy('due_date')
            ->limit(25)
            ->get()
            ->map(fn (LoanLedger $ledger): array => [
                'id' => $ledger->id,
                'loan_id' => $ledger->loan_id,
                'loan_name' => $ledger->loan->loan_name,
                'user_name' => $ledger->loan->user->name,
                'amount' => $ledger->amount,
                'due_date' => $ledger->due_date->format('Y-m-d'),
            ]);

        return response()->json([
            'data' => [
                'total_users' => User::query()->count(),
                'active_loans' => Loan::query()->whereHas('ledgers', fn ($query) => $query->whereNull('payment_date'))->count(),
                'due_loans' => $dueLedgers,
            ],
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $dueLedgers = LoanLedger::query()
            ->with('loan')
            ->whereHas('loan', fn ($query) => $query->where('user_id', $user->id))
            ->whereNull('payment_date')
            ->whereDate('due_date', '<=', today()->addDays(3))
            ->orderBy('due_date')
            ->limit(25)
            ->get()
            ->map(fn (LoanLedger $ledger): array => [
                'id' => $ledger->id,
                'loan_id' => $ledger->loan_id,
                'loan_name' => $ledger->loan->loan_name,
                'user_name' => $user->name,
                'amount' => $ledger->amount,
                'due_date' => $ledger->due_date->format('Y-m-d'),
            ]);

        return response()->json([
            'data' => [
                'account_status' => $user->is_active ? 'active' : 'inactive',
                'active_loans' => $user->loans()->whereHas('ledgers', fn ($query) => $query->whereNull('payment_date'))->count(),
                'near_due_loans' => $dueLedgers,
            ],
        ]);
    }
}
