<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Models\DownpaymentRate;
use App\Models\LoanCategory;
use App\Models\LoanInterestRate;
use App\Models\Penalty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLookupController extends Controller
{
    private const CATALOGS = [
        'loan-categories' => [LoanCategory::class, 'loan_categories', ['name']],
        'down-payment-rates' => [DownpaymentRate::class, 'downpayment_rates', ['name', 'rate']],
        'loan-interest-rates' => [LoanInterestRate::class, 'loan_interest_rates', ['name', 'rate']],
        'penalties' => [Penalty::class, 'penalties', ['name', 'rate', 'penalty_option', 'description']],
    ];

    public function index(Request $request, string $kind)
    {
        [$model, , $fields] = $this->catalog($kind);
        $query = $model::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $sort = (string) $request->query('sort', 'name');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $sortable = ['name', 'created_at'];
        if (in_array('rate', $fields, true)) $sortable[] = 'rate';
        if ($kind === 'penalties') $sortable = [...$sortable, 'penalty_option', 'description'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'name', $direction);

        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        return CatalogResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        [$model, $table, $fields] = $this->catalog($kind);
        $record = $model::query()->create($request->validate($this->rules($kind, $table, $fields)));

        return response()->json(['data' => $record], 201);
    }

    public function update(Request $request, string $kind, int $record): JsonResponse
    {
        [$model, $table, $fields] = $this->catalog($kind);
        $item = $model::query()->findOrFail($record);
        $item->update($request->validate($this->rules($kind, $table, $fields, $record)));

        return response()->json(['data' => $item->fresh()]);
    }

    public function destroy(string $kind, int $record): JsonResponse
    {
        [$model] = $this->catalog($kind);
        $item = $model::query()->findOrFail($record);

        if ($item->loans()->exists()) {
            return response()->json(['message' => 'This record is used by an existing loan and cannot be deleted.'], 409);
        }

        $item->delete();

        return response()->json(['data' => null], 204);
    }

    /** @return array{class-string<Model>, string, list<string>} */
    private function catalog(string $kind): array
    {
        abort_unless(isset(self::CATALOGS[$kind]), 404);

        return self::CATALOGS[$kind];
    }

    private function rules(string $kind, string $table, array $fields, ?int $record = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($record)],
        ];

        if (in_array('rate', $fields, true)) {
            $rules['rate'] = ['required', 'numeric', 'min:0', 'max:9999.999999'];
        }

        if ($kind === 'penalties') {
            $rules['penalty_option'] = ['required', Rule::in(['PER DAY', 'PER MONTH'])];
            $rules['description'] = ['required', 'string'];
        }

        return $rules;
    }
}
