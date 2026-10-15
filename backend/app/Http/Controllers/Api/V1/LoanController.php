<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLoanRequest;
use App\Http\Resources\LoanLedgerResource;
use App\Http\Resources\LoanResource;
use App\Models\DownpaymentRate;
use App\Models\Loan;
use App\Models\LoanLedger;
use App\Models\LoanInterestRate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::query()
            ->with(['user', 'category', 'downpaymentRate', 'interestRate', 'penalty'])
            ->withSum(['ledgers as balance' => fn (Builder $query) => $query->whereNull('payment_date')], 'amount');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('loan_name', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($categoryId = $request->integer('loan_category_id')) {
            $query->where('loan_category_id', $categoryId);
        }

        $sort = (string) $request->query('sort', 'id');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['loan_name', 'loan_amount', 'monthly_amortization', 'months_to_pay', 'start_date', 'balance', 'id'], true)) {
            $query->orderBy($sort === 'id' ? 'loans.id' : $sort, $direction);
        } elseif ($sort === 'user_name') {
            $query->join('users', 'users.id', '=', 'loans.user_id')->orderBy('users.name', $direction)->select('loans.*');
        } elseif ($sort === 'loan_category_name') {
            $query->join('loan_categories', 'loan_categories.id', '=', 'loans.loan_category_id')->orderBy('loan_categories.name', $direction)->select('loans.*');
        } else {
            $query->latest('loans.id');
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return LoanResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function userLedgers(Request $request)
    {
        $query = LoanLedger::query()
            ->with('loan')
            ->whereHas('loan', fn (Builder $loan) => $loan->where('user_id', $request->user()->id))
            ->join('loans', 'loans.id', '=', 'loan_ledgers.loan_id')
            ->select('loan_ledgers.*');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('loans.loan_name', 'like', '%'.$search.'%');
        }

        if ($request->query('status') === 'paid') $query->whereNotNull('loan_ledgers.payment_date');
        if ($request->query('status') === 'unpaid') $query->whereNull('loan_ledgers.payment_date');

        $sort = (string) $request->query('sort', 'due_date');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $sortColumn = match ($sort) {
            'loan_name' => 'loans.loan_name',
            'amount', 'due_date', 'payment_date' => 'loan_ledgers.'.$sort,
            default => 'loan_ledgers.due_date',
        };
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return LoanLedgerResource::collection($query->orderBy($sortColumn, $direction)->paginate($perPage)->withQueryString());
    }

    public function show(Loan $loan): LoanResource
    {
        return new LoanResource($loan
            ->load(['user', 'category', 'downpaymentRate', 'interestRate', 'penalty', 'ledgers'])
            ->loadSum(['ledgers as balance' => fn (Builder $query) => $query->whereNull('payment_date')], 'amount'));
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $loan = DB::transaction(function () use ($request): Loan {
            $loan = $this->persistLoan(new Loan, $request->validated());
            $this->generateLedger($loan);

            return $loan;
        });

        return (new LoanResource($loan
            ->load(['user', 'category', 'downpaymentRate', 'interestRate', 'penalty', 'ledgers'])
            ->loadSum(['ledgers as balance' => fn (Builder $query) => $query->whereNull('payment_date')], 'amount')))
            ->response()->setStatusCode(201);
    }

    public function update(StoreLoanRequest $request, Loan $loan): LoanResource|JsonResponse
    {
        if ($loan->ledgers()->whereNotNull('payment_date')->exists()) {
            return response()->json(['message' => 'A loan with recorded payments cannot be edited.'], 409);
        }

        $loan = DB::transaction(function () use ($request, $loan): Loan {
            $loan->ledgers()->delete();
            $loan = $this->persistLoan($loan, $request->validated());
            $this->generateLedger($loan);

            return $loan;
        });

        return new LoanResource($loan
            ->load(['user', 'category', 'downpaymentRate', 'interestRate', 'penalty', 'ledgers'])
            ->loadSum(['ledgers as balance' => fn (Builder $query) => $query->whereNull('payment_date')], 'amount'));
    }

    public function destroy(Loan $loan): JsonResponse
    {
        $loan->delete();

        return response()->json(['data' => null], 204);
    }

    public function recordPayment(Request $request, LoanLedger $ledger): LoanLedgerResource
    {
        return $this->savePayment($request, $ledger);
    }

    public function recordUserPayment(Request $request, LoanLedger $ledger): LoanLedgerResource
    {
        abort_unless($ledger->loan()->where('user_id', $request->user()->id)->exists(), 404);

        return $this->savePayment($request, $ledger);
    }

    private function savePayment(Request $request, LoanLedger $ledger): LoanLedgerResource
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_reference' => ['required', 'image', 'max:5120'],
        ]);

        if ($ledger->payment_date) {
            abort(409, 'A payment has already been recorded for this installment.');
        }

        $newPath = $request->file('payment_reference')->store('payment-references', 'local');
        $oldPath = $ledger->payment_reference;

        $ledger->update([
            'payment_date' => $validated['payment_date'],
            'payment_reference' => $newPath,
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return new LoanLedgerResource($ledger->fresh());
    }

    public function paymentReference(LoanLedger $ledger)
    {
        abort_unless($ledger->payment_reference && Storage::disk('local')->exists($ledger->payment_reference), 404);

        return response()->file(Storage::disk('local')->path($ledger->payment_reference), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function persistLoan(Loan $loan, array $data): Loan
    {
        $amount = (float) $data['loan_amount'];
        $months = (int) $data['months_to_pay'];
        $downpaymentRate = (float) DownpaymentRate::query()->findOrFail($data['downpayment_rate_id'])->rate;
        $interestRate = (float) LoanInterestRate::query()->findOrFail($data['loan_interest_rate_id'])->rate;
        $data['monthly_amortization'] = round(($interestRate * $amount) + ($amount / $months), 4);
        $data['down_payment'] = round($amount * $downpaymentRate, 4);
        $loan->fill($data)->save();

        return $loan;
    }

    private function generateLedger(Loan $loan): void
    {
        $firstDueDate = CarbonImmutable::parse($loan->start_date->format('Y-m-d'));

        for ($month = 0; $month < $loan->months_to_pay; $month++) {
            $loan->ledgers()->create([
                'amount' => $loan->monthly_amortization,
                'due_date' => $firstDueDate->addMonthsNoOverflow($month)->toDateString(),
            ]);
        }
    }
}
