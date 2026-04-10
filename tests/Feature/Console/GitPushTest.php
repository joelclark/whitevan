<?php

use Illuminate\Support\Facades\Process;

it('runs ci then pushes when on a feature branch and user confirms', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "feature/foo\n"),
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'composer run ci:check' => Process::result(output: 'ok', exitCode: 0),
        'git push -u origin feature/foo' => Process::result(output: 'pushed', exitCode: 0),
    ]);

    $this->artisan('git:push')
        ->expectsConfirmation("Push 'feature/foo' to origin?", 'yes')
        ->assertExitCode(0);

    Process::assertRan('git push -u origin feature/foo');
});

it('fails and does not push when ci:check fails', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "feature/foo\n"),
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'composer run ci:check' => Process::result(output: 'boom', exitCode: 1),
    ]);

    $this->artisan('git:push')->assertExitCode(1);

    Process::assertNotRan('git push -u origin feature/foo');
});

it('skips push when user declines', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "feature/foo\n"),
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'composer run ci:check' => Process::result(output: 'ok', exitCode: 0),
    ]);

    $this->artisan('git:push')
        ->expectsConfirmation("Push 'feature/foo' to origin?", 'no')
        ->assertExitCode(0);

    Process::assertNotRan('git push -u origin feature/foo');
});

it('aborts when working tree is dirty', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "feature/foo\n"),
        'git status --porcelain' => Process::result(output: " M app/Foo.php\n", exitCode: 0),
    ]);

    $this->artisan('git:push')->assertExitCode(1);

    Process::assertNotRan('composer run ci:check');
    Process::assertNotRan('git push -u origin feature/foo');
});

it('prompts for new branch when on a protected branch and creates it before running ci', function (string $protected) {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "{$protected}\n"),
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git checkout -b feature/new' => Process::result(output: 'switched', exitCode: 0),
        'composer run ci:check' => Process::result(output: 'ok', exitCode: 0),
        'git push -u origin feature/new' => Process::result(output: 'pushed', exitCode: 0),
    ]);

    $this->artisan('git:push')
        ->expectsQuestion('New branch name', 'feature/new')
        ->expectsConfirmation("Push 'feature/new' to origin?", 'yes')
        ->assertExitCode(0);

    Process::assertRan('git checkout -b feature/new');
    Process::assertRan('git push -u origin feature/new');
})->with(['master', 'dev', 'main']);

it('fails when current branch cannot be determined', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: '', exitCode: 1),
    ]);

    $this->artisan('git:push')->assertExitCode(1);

    Process::assertNotRan('git status --porcelain');
    Process::assertNotRan('composer run ci:check');
});

it('fails when creating a new branch from master fails', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "master\n"),
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git checkout -b feature/new' => Process::result(output: 'boom', exitCode: 1),
    ]);

    $this->artisan('git:push')
        ->expectsQuestion('New branch name', 'feature/new')
        ->assertExitCode(1);

    Process::assertNotRan('composer run ci:check');
    Process::assertNotRan('git push -u origin feature/new');
});

it('fails when git push itself fails', function () {
    Process::fake([
        'git rev-parse --abbrev-ref HEAD' => Process::result(output: "feature/foo\n"),
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'composer run ci:check' => Process::result(output: 'ok', exitCode: 0),
        'git push -u origin feature/foo' => Process::result(output: 'rejected', exitCode: 1),
    ]);

    $this->artisan('git:push')
        ->expectsConfirmation("Push 'feature/foo' to origin?", 'yes')
        ->assertExitCode(1);

    Process::assertRan('git push -u origin feature/foo');
});
