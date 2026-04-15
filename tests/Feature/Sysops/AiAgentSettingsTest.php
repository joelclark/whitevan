<?php

use App\Enums\ActivityEvent;
use App\Enums\AiAgentKind;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\AiAgentSetting;
use App\Models\User;

beforeEach(function () {
    $this->sysop = User::factory()->create();
    $this->sysop->forceFill(['is_sysop' => true])->save();

    $this->setting = AiAgentSetting::factory()->create([
        'kind' => AiAgentKind::FloorPlanExtraction->value,
    ]);
});

test('non-sysops cannot view the AI agents index', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->get(route('sysops.ai-agents.index'))
        ->assertForbidden();
});

test('sysops can view the AI agents index', function () {
    $this->actingAs($this->sysop)
        ->get(route('sysops.ai-agents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sysops/ai-agents/index')
            ->has('agents', 1)
        );
});

test('sysops can edit a system prompt and the change is logged', function () {
    $this->actingAs($this->sysop)
        ->put(route('sysops.ai-agents.update', $this->setting), [
            'system_prompt' => str_repeat('You are an agent. ', 10),
            'description' => 'Reads floor plans.',
        ])
        ->assertRedirect();

    $this->setting->refresh();
    expect($this->setting->description)->toBe('Reads floor plans.');
    expect($this->setting->system_prompt)->toContain('You are an agent.');

    expect(ActivityLog::where('event', ActivityEvent::AiAgentSettingUpdated)->count())->toBe(1);
});

test('non-sysops cannot update an AI agent setting', function () {
    $account = Account::factory()->create();

    $this->actingAs($account->owner)
        ->put(route('sysops.ai-agents.update', $this->setting), [
            'system_prompt' => 'malicious override here',
        ])
        ->assertForbidden();

    expect($this->setting->refresh()->system_prompt)->not->toBe('malicious override here');
});
