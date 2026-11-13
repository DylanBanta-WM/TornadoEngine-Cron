<?php

namespace TornadoEngine\Cron\Tests;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use TornadoEngine\Cron\Cron;
use TornadoEngine\Cron\Models\CronJob;

class CronTest extends TestCase
{
    use RefreshDatabase;

    private int $runs = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(Cron::class)
            ->job('test.refresh', 'Refresh the test list', function () {
                $this->runs++;

                return 'Refreshed 3 items';
            }, schedule: '0 2 * * *', description: 'A job for tests')
            ->job('test.broken', 'A broken job', fn () => throw new RuntimeException('It broke'));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @return array<string, string> scheduled job name => cron expression */
    private function scheduled(): array
    {
        $events = [];
        foreach (app(Schedule::class)->events() as $event) {
            $events[$event->description] = $event->expression;
        }

        return $events;
    }

    public function test_jobs_are_scheduled_with_their_default_until_an_admin_changes_it(): void
    {
        $this->assertSame('0 2 * * *', $this->scheduled()['cron:test.refresh']);

        $this->actingAs($this->admin())->from('/administration')
            ->post('/administration/cron/test.refresh', ['schedule' => '*/15 * * * *', 'enabled' => '1'])
            ->assertRedirect('/administration');

        $this->assertSame('*/15 * * * *', CronJob::find('test.refresh')->schedule);
    }

    public function test_jobs_that_are_off_are_not_scheduled(): void
    {
        CronJob::create(['key' => 'test.refresh', 'enabled' => false]);

        $this->assertArrayNotHasKey('cron:test.refresh', $this->scheduled());
        $this->assertArrayHasKey('cron:heartbeat', $this->scheduled());
    }

    public function test_invalid_cron_expressions_are_refused(): void
    {
        $this->actingAs($this->admin())->from('/administration')
            ->post('/administration/cron/test.refresh', ['schedule' => 'every day', 'enabled' => '1'])
            ->assertSessionHasErrors('schedule');
    }

    public function test_run_now_records_the_result(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from('/administration')->post('/administration/cron/test.refresh/run')->assertRedirect('/administration');
        $this->assertSame(1, $this->runs);
        $this->assertSame('ok', CronJob::find('test.refresh')->last_status);

        $this->actingAs($admin)->post('/administration/cron/test.broken/run');
        $this->assertSame('failed', CronJob::find('test.broken')->last_status);
        $this->assertSame('It broke', CronJob::find('test.broken')->last_message);

        $this->actingAs($admin)->get('/administration/cron')
            ->assertSee('Refresh the test list')
            ->assertSee('Refreshed 3 items')
            ->assertSee('It broke');
    }

    public function test_the_card_warns_when_the_scheduler_isnt_running(): void
    {
        $this->actingAs($this->admin())->get('/administration/cron')->assertSee('The scheduler isn\'t running', false);
    }

    public function test_only_admins_can_change_jobs(): void
    {
        $this->actingAs(User::factory()->create())->post('/administration/cron/test.refresh/run')->assertForbidden();
        $this->assertSame(0, $this->runs);
    }
}
