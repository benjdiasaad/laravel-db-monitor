<?php

use BenjdiaSaad\DbMonitor\DbMonitor;
use BenjdiaSaad\DbMonitor\Events\FindingRecorded;
use BenjdiaSaad\DbMonitor\Models\DbFinding;
use BenjdiaSaad\DbMonitor\Models\QueryLog;
use BenjdiaSaad\DbMonitor\Notifications\DbIssueDetected;
use BenjdiaSaad\DbMonitor\Testing\InteractsWithDbMonitor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(InteractsWithDbMonitor::class);

beforeEach(function () {
    Schema::create('widgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    DB::table('widgets')->insert(collect(range(1, 10))->map(fn ($i) => ['name' => "w{$i}"])->all());

    Route::middleware('db.monitor')->get('/widgets', function () {
        foreach (range(1, 6) as $id) {
            DB::table('widgets')->where('id', $id)->first();
        }

        return 'ok';
    });
});

it('records queries and findings for a monitored request', function () {
    $this->get('/widgets')->assertOk();

    expect(QueryLog::count())->toBe(6)
        ->and(QueryLog::first()->request_path)->toBe('widgets')
        ->and(QueryLog::first()->location)->toContain('MonitoringTest.php')
        ->and(DbFinding::where('type', 'n_plus_one')->count())->toBe(1);
});

it('does not record queries outside a monitored scope', function () {
    DB::table('widgets')->get();

    expect(app(DbMonitor::class)->getRecorder()->getQueries())->toBeEmpty();
});

it('respects the sample rate', function () {
    config(['db-monitor.sample_rate' => 0]);

    $this->get('/widgets')->assertOk();

    expect(QueryLog::count())->toBe(0);
});

it('ignores queries matching ignore_queries', function () {
    config(['db-monitor.ignore_queries' => ['/from "widgets"/']]);

    $this->get('/widgets')->assertOk();

    expect(QueryLog::count())->toBe(0);
});

it('dispatches an event for every stored finding', function () {
    Event::fake([FindingRecorded::class]);

    $this->get('/widgets')->assertOk();

    Event::assertDispatched(FindingRecorded::class, fn ($e) => $e->finding->type === 'n_plus_one');
});

it('throttles repeated notifications for the same issue', function () {
    Notification::fake();
    config(['db-monitor.notify' => 'ops@example.com', 'db-monitor.notify_min_severity' => 'warning']);

    $this->get('/widgets');
    $this->get('/widgets');

    Notification::assertSentOnDemandTimes(DbIssueDetected::class, 1);
});

it('profiles a callback without storing anything', function () {
    config(['db-monitor.enabled' => false]);

    $profile = app(DbMonitor::class)->profile(function () {
        foreach (range(1, 6) as $id) {
            DB::table('widgets')->where('id', $id)->first();
        }
    });

    expect($profile->queryCount())->toBe(6)
        ->and($profile->hasIssues('n_plus_one'))->toBeTrue()
        ->and(QueryLog::count())->toBe(0);
});

it('provides query assertions for tests', function () {
    $this->assertMaxQueries(1, fn () => DB::table('widgets')->get());
    $this->assertNoNPlusOneQueries(fn () => DB::table('widgets')->whereIn('id', [1, 2, 3])->get());
    $this->assertNoDuplicateQueries(fn () => DB::table('widgets')->get());

    expect(fn () => $this->assertMaxQueries(2, fn () => $this->get('/widgets')))
        ->toThrow(PHPUnit\Framework\ExpectationFailedException::class);
});

it('fails db:report in CI mode when issues exist', function () {
    $this->get('/widgets');

    $this->artisan('db:report --fail-on=warning')->assertExitCode(1);
    $this->artisan('db:report --fail-on=critical')->assertExitCode(0);
    $this->artisan('db:report --json')->assertExitCode(0);
});

it('can preview index migrations with db:fix --dry-run', function () {
    $this->artisan('db:fix --table=widgets --column=name --dry-run')
        ->expectsOutputToContain('add_index_name_to_widgets_table')
        ->assertExitCode(0);

    $this->artisan('db:fix --table=widgets --column=nope --dry-run')->assertExitCode(1);
});
