<?php

use App\Enums\ActivityEvent;
use App\Enums\EstimateStatus;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateRoom;

function interviewEstimate(?Account $account = null, int $roomCount = 2): Estimate
{
    $account ??= Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create([
        'status' => EstimateStatus::Ready,
    ]);

    for ($i = 1; $i <= $roomCount; $i++) {
        EstimateRoom::factory()->create([
            'estimate_id' => $estimate->id,
            'position' => $i,
            'name' => "Room {$i}",
        ]);
    }

    return $estimate->refresh();
}

function answer(Estimate $estimate, string $key, mixed $value, ?int $roomId = null): array
{
    return [
        'question_key' => $key,
        'room_id' => $roomId,
        'value' => $value,
    ];
}

test('a member can answer a room-phase question and next question is returned', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account, 2);
    $room = $estimate->rooms->first();

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'material', 'lvp', $room->id))
        ->assertRedirect();

    $estimate->refresh();
    $rooms = $estimate->interview_answers['rooms'];
    expect((array) $rooms)->toHaveKey((string) $room->id);
    expect(((array) $rooms[(string) $room->id])['material'])->toBe('lvp');
});

test('invalid option returns a validation error', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account);
    $room = $estimate->rooms->first();

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'material', 'granite', $room->id))
        ->assertSessionHasErrors('value');
});

test('unknown question_key returns a validation error', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account);
    $room = $estimate->rooms->first();

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'bogus', 'x', $room->id))
        ->assertSessionHasErrors('question_key');
});

test('answering when the estimate is still processing returns 409', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account);
    $estimate->forceFill(['status' => EstimateStatus::Processing])->save();
    $room = $estimate->rooms->first();

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'material', 'lvp', $room->id))
        ->assertStatus(409);
});

test('answering a failed estimate returns 409', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account);
    $estimate->forceFill(['status' => EstimateStatus::Failed])->save();
    $room = $estimate->rooms->first();

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'material', 'lvp', $room->id))
        ->assertStatus(409);
});

test('a room-phase question without room_id fails validation', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account);

    $this->actingAs($account->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'material', 'lvp'))
        ->assertSessionHasErrors('room_id');
});

test('another account cannot answer an estimate', function () {
    $otherAccount = Account::factory()->create();
    $estimate = interviewEstimate($otherAccount);
    $room = $estimate->rooms->first();

    $intruder = Account::factory()->create();

    $this->actingAs($intruder->owner)
        ->post(route('estimates.interview.answer', $estimate), answer($estimate, 'material', 'lvp', $room->id))
        ->assertNotFound();
});

test('orphan clearing: switching furniture away from heavy drops heavy_count', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account, 1);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    $post = fn (array $body) => $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), $body);

    $post(answer($estimate, 'material', 'lvp', $room->id))->assertRedirect();
    $post(answer($estimate, 'existing', 'bare', $room->id))->assertRedirect();
    $post(answer($estimate, 'subfloor', 'none', $room->id))->assertRedirect();
    $post(answer($estimate, 'furniture', 'heavy', $room->id))->assertRedirect();
    $post(answer($estimate, 'heavy_count', 3, $room->id))->assertRedirect();

    $estimate->refresh();
    $roomKey = (string) $room->id;
    expect(((array) $estimate->interview_answers['rooms'])[$roomKey])->toHaveKey('heavy_count');

    $post(answer($estimate, 'furniture', 'empty', $room->id))->assertRedirect();

    $estimate->refresh();
    $roomAnswers = (array) ((array) $estimate->interview_answers['rooms'])[$roomKey];
    expect($roomAnswers)->not->toHaveKey('heavy_count');
    expect($roomAnswers['furniture'])->toBe('empty');
});

test('completing the interview fires EstimateInterviewCompleted exactly once', function () {
    $account = Account::factory()->create();
    $estimate = interviewEstimate($account, 1);
    $room = $estimate->rooms->first();
    $user = $account->owner;

    $post = fn (array $body) => $this->actingAs($user)
        ->post(route('estimates.interview.answer', $estimate), $body)
        ->assertRedirect();

    // Room phase.
    $post(answer($estimate, 'material', 'lvp', $room->id));
    $post(answer($estimate, 'existing', 'bare', $room->id));
    $post(answer($estimate, 'subfloor', 'none', $room->id));
    $post(answer($estimate, 'furniture', 'empty', $room->id));

    // Long tail.
    $post(answer($estimate, 'customer_type', 'person'));
    $post(answer($estimate, 'demo_haul_away', 'van'));
    $post(answer($estimate, 'baseboards', 'leave'));
    $post(answer($estimate, 'quarter_round', 'new'));
    $post(answer($estimate, 'transitions', 2));
    $post(answer($estimate, 'door_undercuts', 1));

    expect(ActivityLog::where('event', ActivityEvent::EstimateInterviewCompleted)->count())->toBe(0);

    // Final answer — triggers completion.
    $post(answer($estimate, 'toilet_pulls', 0));

    expect(ActivityLog::where('event', ActivityEvent::EstimateInterviewCompleted)->count())->toBe(1);

    // Re-posting an already-answered question does not re-fire the event.
    $post(answer($estimate, 'toilet_pulls', 1));

    expect(ActivityLog::where('event', ActivityEvent::EstimateInterviewCompleted)->count())->toBe(1);
    expect($estimate)->toHaveRecordedProjectEvent(ActivityEvent::EstimateInterviewCompleted);
});
