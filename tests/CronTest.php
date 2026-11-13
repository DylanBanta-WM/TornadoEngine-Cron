<?php

namespace TornadoEngine\Cron\Tests;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use TornadoEngine\Cron\Cron;
use TornadoEngine\Cron\Explain;
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

    public function test_each_job_takes_a_cron_expression_as_text(): void
    {
        $this->actingAs($this->admin())->get('/administration/cron')
            ->assertSee('name="schedule"', false)
            ->assertDontSee('<select', false)
            ->assertSee('At 02:00.');

        $this->actingAs($this->admin())->post('/administration/cron/test.refresh', ['job' => 'test.refresh', 'schedule' => ' 30 6 * * 1-5 ', 'enabled' => '1']);
        $this->assertSame('30 6 * * 1-5', CronJob::find('test.refresh')->schedule);
        $this->actingAs($this->admin())->get('/administration/cron')->assertSee('At 06:30 on Monday through Friday. (Default: 0 2 * * *)');
    }

    public function test_the_calculator_explains_expressions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/administration/cron')->assertSee('Cron calculator');

        $this->actingAs($admin)->getJson('/administration/cron/explain?expression='.urlencode('*/15 9-17 * * mon-fri'))
            ->assertOk()
            ->assertJson(['valid' => true, 'description' => 'At every 15th minute past hour 9 through 17 on Monday through Friday.'])
            ->assertJsonCount(5, 'next');
        $this->actingAs($admin)->getJson('/administration/cron/explain?expression=61+*+*+*+*')->assertJson(['valid' => false, 'next' => []]);
        $this->actingAs(User::factory()->create())->getJson('/administration/cron/explain?expression=*+*+*+*+*')->assertForbidden();
    }

    public function test_descriptions(): void
    {
        foreach ([
            '0 * * * *' => 'At minute 0.',
            '5,10,15 8 * * *' => 'At minute 5, 10 and 15 past hour 8.',
            '0 0 1,15 * sun' => 'At 00:00 on day-of-month 1 and 15 and on Sunday.',
            '30 4 * jan-mar 6' => 'At 04:30 on Saturday in January through March.',
            '0 */3 * * *' => 'At minute 0 past every 3rd hour.',
            '@weekly' => 'At 00:00 on Sunday.',
        ] as $expression => $text) {
            $this->assertSame($text, Explain::describe($expression), $expression);
        }
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
