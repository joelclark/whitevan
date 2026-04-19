<?php

use App\Enums\ActivityEvent;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\ContractTemplate;
use App\Models\User;

beforeEach(function () {
    $this->sysop = User::factory()->create();
    $this->sysop->forceFill(['is_sysop' => true])->save();

    $this->template = ContractTemplate::factory()->create([
        'kind' => ContractTemplate::DEFAULT_KIND,
        'body' => 'Original body here with more than ten chars.',
    ]);
});

test('non-sysops cannot view the contract templates index', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->get(route('sysops.contract-templates.index'))
        ->assertForbidden();
});

test('sysops can view the contract templates index', function () {
    $this->actingAs($this->sysop)
        ->get(route('sysops.contract-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/contract-templates/index')
            ->has('templates', 1)
        );
});

test('sysops can view the edit page with a rendered preview', function () {
    $this->actingAs($this->sysop)
        ->get(route('sysops.contract-templates.edit', $this->template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/contract-templates/edit')
            ->has('template.body')
            ->has('template.preview_html')
        );
});

test('sysops can update the body and the change is logged', function () {
    $this->actingAs($this->sysop)
        ->put(route('sysops.contract-templates.update', $this->template), [
            'body' => str_repeat('Updated contract body. ', 10),
        ])
        ->assertRedirect();

    expect($this->template->refresh()->body)->toContain('Updated contract body.');

    expect(ActivityLog::where('event', ActivityEvent::ContractTemplateUpdated)->count())->toBe(1);
});

test('non-sysops cannot update a contract template', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->put(route('sysops.contract-templates.update', $this->template), [
            'body' => 'malicious override here and then some more text',
        ])
        ->assertForbidden();

    expect($this->template->refresh()->body)->not->toContain('malicious override');
});
