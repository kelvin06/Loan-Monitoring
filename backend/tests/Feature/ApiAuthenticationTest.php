<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

function makeAccount(string $role = 'user', array $attributes = []): User
{
    static $nextId = 1;

    return User::query()->create(array_merge([
        'name' => 'Test '.ucfirst($role),
        'email' => $role.'-'.($nextId++).'@example.test',
        'password' => 'a-valid-test-password',
        'role' => $role,
        'is_active' => true,
    ], $attributes));
}

it('requires authentication for protected APIs', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->getJson('/api/v1/user/profile')->assertUnauthorized();
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
});

it('validates login input before looking up credentials', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email', 'password' => 'anything'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects incorrect passwords and inactive accounts at login', function () {
    $user = makeAccount('user', ['email' => 'login-user@example.test']);
    $admin = makeAccount('admin', ['email' => 'login-admin@example.test']);
    $inactiveUser = makeAccount('user', [
        'email' => 'inactive-user@example.test',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/admin/login', [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/login', [
        'email' => $inactiveUser->email,
        'password' => 'a-valid-test-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('issues a revocable mobile token and returns only the safe user resource', function () {
    $user = makeAccount(attributes: ['email' => 'member@example.test']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'a-valid-test-password',
    ])->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonMissingPath('data.user.password');

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.role', 'user')
        ->assertJsonMissingPath('data.user.password');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

it('keeps admin and user credentials in their separate login portals', function () {
    $user = makeAccount('user', ['email' => 'member@example.test']);
    $admin = makeAccount('admin', ['email' => 'admin@example.test']);

    $this->postJson('/api/v1/auth/admin/login', [
        'email' => $user->email,
        'password' => 'a-valid-test-password',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/login', [
        'email' => $admin->email,
        'password' => 'a-valid-test-password',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/admin/login', [
        'email' => $admin->email,
        'password' => 'a-valid-test-password',
    ])->assertOk()->assertJsonPath('data.user.role', 'admin');
});

it('isolates user and administrator endpoints by role', function () {
    $user = makeAccount('user');
    $admin = makeAccount('admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/user/dashboard')
        ->assertOk()
        ->assertJsonPath('data.account_status', 'active');
    $this->getJson('/api/v1/admin/dashboard')->assertForbidden();

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/user/dashboard')->assertForbidden();
});

it('denies protected access to inactive users', function () {
    $user = makeAccount(attributes: ['is_active' => false]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/user/dashboard')->assertForbidden();
});

it('updates only the authenticated user profile and checks current password', function () {
    $user = makeAccount(attributes: ['email' => 'owner@example.test']);
    $otherUser = makeAccount(attributes: ['email' => 'other@example.test']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/user/profile', [
            'name' => 'Updated Name',
            'current_password' => 'wrong-password',
            'password' => 'a-new-test-password',
            'password_confirmation' => 'a-new-test-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->putJson('/api/v1/user/profile', [
        'name' => 'Updated Name',
        'current_password' => 'a-valid-test-password',
        'password' => 'a-new-test-password',
        'password_confirmation' => 'a-new-test-password',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Name');

    expect(Hash::check('a-new-test-password', $user->fresh()->password))->toBeTrue()
        ->and($otherUser->fresh()->name)->not->toBe('Updated Name');
});

it('creates, filters, updates, deactivates, and deletes users through admin routes', function () {
    $admin = makeAccount('admin');
    $this->actingAs($admin, 'sanctum');

    $created = $this->postJson('/api/v1/admin/users', [
        'name' => 'Managed User',
        'email' => 'managed@example.test',
        'password' => 'a-valid-test-password',
        'role' => 'user',
    ])->assertCreated()->assertJsonPath('data.email', 'managed@example.test');
    $id = $created->json('data.id');

    $this->getJson('/api/v1/admin/users?search=managed&role=user&status=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('meta.total', 1);

    $this->putJson("/api/v1/admin/users/{$id}", ['name' => 'Renamed User'])
        ->assertOk()->assertJsonPath('data.name', 'Renamed User');
    $this->patchJson("/api/v1/admin/users/{$id}/status", ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $this->deleteJson("/api/v1/admin/users/{$id}")->assertNoContent();
    $this->assertDatabaseMissing('users', ['id' => $id]);
});

it('protects administrators from self-deactivation and self-deletion', function () {
    $admin = makeAccount('admin');
    $this->actingAs($admin, 'sanctum');

    $this->patchJson("/api/v1/admin/users/{$admin->id}/status", ['is_active' => false])->assertUnprocessable();
    $this->deleteJson("/api/v1/admin/users/{$admin->id}")->assertUnprocessable();
});

it('allows administrators to maintain loan catalogs', function () {
    $this->actingAs(makeAccount('admin'), 'sanctum');

    $category = $this->postJson('/api/v1/admin/catalog/loan-categories', [
        'name' => 'Emergency loan',
    ])->assertCreated()->assertJsonPath('data.name', 'Emergency loan');

    $this->getJson('/api/v1/admin/catalog/loan-categories?search=emergency')
        ->assertOk()->assertJsonPath('data.0.id', $category->json('data.id'));

    $this->putJson('/api/v1/admin/catalog/loan-categories/'.$category->json('data.id'), [
        'name' => 'Personal loan',
    ])->assertOk()->assertJsonPath('data.name', 'Personal loan');

    $this->postJson('/api/v1/admin/catalog/down-payment-rates', [
        'name' => 'Ten percent', 'rate' => 0.1,
    ])->assertCreated()->assertJsonPath('data.rate', 0.1);

    $this->postJson('/api/v1/admin/catalog/loan-interest-rates', [
        'name' => 'Twelve percent', 'rate' => 0.12,
    ])->assertCreated();

    $penalty = $this->postJson('/api/v1/admin/catalog/penalties', [
        'name' => 'Monthly late fee',
        'rate' => 0.01,
        'penalty_option' => 'PER MONTH',
        'description' => 'Applied each month while an installment remains unpaid.',
    ])->assertCreated()->assertJsonPath('data.penalty_option', 'PER MONTH');

    $this->deleteJson('/api/v1/admin/catalog/penalties/'.$penalty->json('data.id'))->assertNoContent();
});

it('calculates loans and creates one ledger installment per month', function () {
    $this->actingAs(makeAccount('admin'), 'sanctum');
    $user = makeAccount('user');
    $category = $this->postJson('/api/v1/admin/catalog/loan-categories', ['name' => 'Personal loan'])->json('data.id');
    $downpaymentRate = $this->postJson('/api/v1/admin/catalog/down-payment-rates', [
        'name' => 'Ten percent', 'rate' => 0.1,
    ])->json('data.id');
    $interestRate = $this->postJson('/api/v1/admin/catalog/loan-interest-rates', [
        'name' => 'Twelve percent', 'rate' => 0.12,
    ])->json('data.id');
    $penalty = $this->postJson('/api/v1/admin/catalog/penalties', [
        'name' => 'Daily late fee', 'rate' => 0.001, 'penalty_option' => 'PER DAY', 'description' => 'Daily penalty.',
    ])->json('data.id');

    $payload = [
        'loan_name' => 'Test loan',
        'loan_amount' => '1200.0000',
        'months_to_pay' => 12,
        'loan_date' => '2026-10-15',
        'start_date' => '2026-10-15',
        'user_id' => $user->id,
        'downpayment_rate_id' => $downpaymentRate,
        'loan_category_id' => $category,
        'loan_interest_rate_id' => $interestRate,
        'penalty_id' => $penalty,
    ];

    $created = $this->postJson('/api/v1/admin/loans', $payload)
        ->assertCreated()
        ->assertJsonPath('data.monthly_amortization', '244.0000')
        ->assertJsonPath('data.down_payment', '120.0000')
        ->assertJsonPath('data.user_name', $user->name);

    $loanId = $created->json('data.id');
    $this->deleteJson('/api/v1/admin/catalog/penalties/'.$penalty)->assertStatus(409);
    $ledgers = $created->json('data.ledgers');
    expect($ledgers)->toHaveCount(12)
        ->and($ledgers[0]['due_date'])->toBe('2026-10-15')
        ->and($ledgers[1]['due_date'])->toBe('2026-11-15')
        ->and($ledgers[11]['due_date'])->toBe('2027-09-15')
        ->and($ledgers[0]['payment_date'])->toBeNull()
        ->and($ledgers[0]['payment_reference'])->toBeNull();

    $payload['loan_amount'] = '2400.0000';
    $payload['months_to_pay'] = 6;
    $updated = $this->putJson('/api/v1/admin/loans/'.$loanId, $payload)
        ->assertOk()
        ->assertJsonPath('data.monthly_amortization', '688.0000')
        ->assertJsonCount(6, 'data.ledgers');

    Storage::fake('local');
    $ledgerId = $updated->json('data.ledgers.0.id');
    $this->post('/api/v1/admin/loan-ledgers/'.$ledgerId.'/payment', [
        'payment_date' => '2026-10-20',
        'payment_reference' => UploadedFile::fake()->image('receipt.png'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.payment_date', '2026-10-20');
    $this->getJson('/api/v1/admin/loan-ledgers/'.$ledgerId.'/payment-reference')->assertOk();

    $this->deleteJson('/api/v1/admin/loans/'.$loanId)->assertNoContent();
    $this->assertDatabaseMissing('loans', ['id' => $loanId]);
    $this->assertDatabaseMissing('loan_ledgers', ['loan_id' => $loanId]);
});

it('exposes the framework health endpoint', function () {
    $this->get('/up')->assertOk();
});
