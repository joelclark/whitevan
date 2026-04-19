<?php

use App\Enums\ActivityEvent;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Asserts that a ProjectEvent row was written for the given ActivityEvent,
 * scoped to the subject (Project or Estimate). When the subject is an
 * Estimate, both project_id and estimate_id are checked.
 */
expect()->extend('toHaveRecordedProjectEvent', function (ActivityEvent $event) {
    /** @var Project|Estimate $subject */
    $subject = $this->value;

    $query = ProjectEvent::withoutGlobalScopes()
        ->where('event', $event->value);

    if ($subject instanceof Estimate) {
        $query->where('project_id', $subject->project_id)
            ->where('estimate_id', $subject->id);
    } else {
        $query->where('project_id', $subject->id);
    }

    Assert::assertTrue(
        $query->exists(),
        sprintf(
            'Expected a project_events row for event "%s" scoped to %s#%d, found none.',
            $event->value,
            class_basename($subject),
            $subject->id,
        ),
    );

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
