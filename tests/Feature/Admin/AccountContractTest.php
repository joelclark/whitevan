<?php

use App\Enums\ActivityEvent;
use App\Enums\SecurityGroup;
use App\Models\Account;
use App\Models\AccountContractOverride;
use App\Models\ActivityLog;
use App\Models\ContractTemplate;
use App\Models\SecurityGroupUser;
use App\Models\User;

beforeEach(function () {
    ContractTemplate::factory()->create([
        'kind' => ContractTemplate::DEFAULT_KIND,
        'body' => '# Default system contract body',
    ]);

    $this->account = Account::factory()->create();
    $this->admin = $this->account->owner;
    SecurityGroupUser::create([
        'account_id' => $this->account->id,
        'user_id' => $this->admin->id,
        'security_group' => SecurityGroup::Admin,
    ]);
});

test('admins can view the contract page with default body preview', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.contract.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/contract/edit')
            ->where('override', null)
            ->has('default.body')
            ->has('default.preview_html')
        );
});

test('non-admins cannot view the contract page', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('admin.contract.edit'))
        ->assertForbidden();
});

test('admins can save a custom override', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.contract.update'), [
            'body' => 'Custom override for this account and some extra text',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('account_contract_overrides', [
        'account_id' => $this->account->id,
        'body' => 'Custom override for this account and some extra text',
    ]);

    expect(ActivityLog::where('event', ActivityEvent::AccountContractUpdated)->count())->toBe(1);
});

test('saving again updates the existing override row', function () {
    AccountContractOverride::factory()->create([
        'account_id' => $this->account->id,
        'body' => 'first version',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.contract.update'), [
            'body' => 'second version with more content here',
        ])
        ->assertRedirect();

    expect(AccountContractOverride::where('account_id', $this->account->id)->count())->toBe(1);
    expect(AccountContractOverride::where('account_id', $this->account->id)->value('body'))
        ->toBe('second version with more content here');
});

test('admins can revert to the default by deleting the override', function () {
    AccountContractOverride::factory()->create([
        'account_id' => $this->account->id,
        'body' => 'existing custom body',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.contract.destroy'))
        ->assertRedirect();

    $this->assertDatabaseMissing('account_contract_overrides', [
        'account_id' => $this->account->id,
    ]);

    expect(ActivityLog::where('event', ActivityEvent::AccountContractReverted)->count())->toBe(1);
});
