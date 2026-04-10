<?php

use Illuminate\Support\Facades\Process;

it('creates a new branch off dev on the happy path', function () {
    Process::fake([
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git fetch origin dev' => Process::result(output: '', exitCode: 0),
        'git show-ref --verify --quiet refs/heads/dev' => Process::result(output: '', exitCode: 0),
        'git checkout dev' => Process::result(output: 'switched', exitCode: 0),
        'git pull --ff-only origin dev' => Process::result(output: 'up to date', exitCode: 0),
        'git checkout -b feature/account-settings' => Process::result(output: 'switched', exitCode: 0),
    ]);

    $this->artisan('git:new-branch')
        ->expectsQuestion('Branch prefix', 'feature')
        ->expectsQuestion('Branch slug (kebab-case, no prefix)', 'account-settings')
        ->assertExitCode(0);

    Process::assertRan('git checkout -b feature/account-settings');
    Process::assertNotRan('git branch dev origin/dev');
    Process::assertNotRan('git push -u origin feature/account-settings');
});

it('aborts when working tree is dirty', function () {
    Process::fake([
        'git status --porcelain' => Process::result(output: " M app/Foo.php\n", exitCode: 0),
    ]);

    $this->artisan('git:new-branch')->assertExitCode(1);

    Process::assertNotRan('git fetch origin dev');
    Process::assertNotRan('git checkout dev');
});

it('fails when origin has no dev branch', function () {
    Process::fake([
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git fetch origin dev' => Process::result(output: "fatal: couldn't find remote ref dev", exitCode: 1),
    ]);

    $this->artisan('git:new-branch')->assertExitCode(1);

    Process::assertNotRan('git checkout dev');
});

it('creates local dev from origin/dev when missing', function () {
    Process::fake([
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git fetch origin dev' => Process::result(output: '', exitCode: 0),
        'git show-ref --verify --quiet refs/heads/dev' => Process::result(output: '', exitCode: 1),
        'git branch dev origin/dev' => Process::result(output: '', exitCode: 0),
        'git checkout dev' => Process::result(output: 'switched', exitCode: 0),
        'git pull --ff-only origin dev' => Process::result(output: 'up to date', exitCode: 0),
        'git checkout -b fix/login' => Process::result(output: 'switched', exitCode: 0),
    ]);

    $this->artisan('git:new-branch')
        ->expectsQuestion('Branch prefix', 'fix')
        ->expectsQuestion('Branch slug (kebab-case, no prefix)', 'login')
        ->assertExitCode(0);

    Process::assertRan('git branch dev origin/dev');
    Process::assertRan('git checkout -b fix/login');
});

it('aborts when local dev has diverged from origin/dev', function () {
    Process::fake([
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git fetch origin dev' => Process::result(output: '', exitCode: 0),
        'git show-ref --verify --quiet refs/heads/dev' => Process::result(output: '', exitCode: 0),
        'git checkout dev' => Process::result(output: 'switched', exitCode: 0),
        'git pull --ff-only origin dev' => Process::result(output: 'not possible to fast-forward', exitCode: 1),
    ]);

    $this->artisan('git:new-branch')->assertExitCode(1);

    Process::assertNotRan('git checkout -b feature/whatever');
});

it('fails when creating the new branch fails', function () {
    Process::fake([
        'git status --porcelain' => Process::result(output: '', exitCode: 0),
        'git fetch origin dev' => Process::result(output: '', exitCode: 0),
        'git show-ref --verify --quiet refs/heads/dev' => Process::result(output: '', exitCode: 0),
        'git checkout dev' => Process::result(output: 'switched', exitCode: 0),
        'git pull --ff-only origin dev' => Process::result(output: 'up to date', exitCode: 0),
        'git checkout -b feature/exists' => Process::result(output: 'already exists', exitCode: 128),
    ]);

    $this->artisan('git:new-branch')
        ->expectsQuestion('Branch prefix', 'feature')
        ->expectsQuestion('Branch slug (kebab-case, no prefix)', 'exists')
        ->assertExitCode(1);

    Process::assertRan('git checkout -b feature/exists');
});
