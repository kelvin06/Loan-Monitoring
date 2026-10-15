<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexUsersRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Requests\Api\V1\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(IndexUsersRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $users = User::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['role']) && $filters['role'] !== null, fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(array_key_exists('status', $filters) && $filters['status'] !== null, fn (Builder $query) => $query->where('is_active', $filters['status']))
            ->orderBy($sort, $direction)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user->refresh());
    }

    public function status(UpdateUserStatusRequest $request, User $user): UserResource
    {
        abort_if($request->user()->is($user), 422, 'You cannot deactivate your own account.');

        $user->update($request->validated());

        return new UserResource($user->refresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_if($user->loans()->exists(), 409, 'This user has loan records and cannot be deleted.');

        $user->delete();

        return response()->json(['data' => null], 204);
    }
}
