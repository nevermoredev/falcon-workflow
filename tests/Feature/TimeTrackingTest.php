<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_start_a_second_timer(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $employee = User::factory()->create(['role' => 'employee']);
        $first = Task::create(['title' => 'Первая', 'priority' => 'medium', 'assigned_by' => $manager->id]);
        $second = Task::create(['title' => 'Вторая', 'priority' => 'medium', 'assigned_by' => $manager->id]);
        $a1 = $first->assignments()->create(['user_id' => $employee->id, 'scope' => 'x']);
        $a2 = $second->assignments()->create(['user_id' => $employee->id, 'scope' => 'y']);

        $this->actingAs($employee)->postJson(route('timer.start', [$first, $a1]))->assertOk()->assertJson(['running' => true]);
        $this->actingAs($employee)->postJson(route('timer.start', [$first, $a1]))->assertOk();
        $this->actingAs($employee)->postJson(route('timer.start', [$second, $a2]))->assertStatus(409);
        $this->assertSame(1, TimeEntry::where('user_id', $employee->id)->count());

        $this->actingAs($employee)->postJson(route('timer.stop'))->assertOk()->assertJson(['running' => false]);
        $this->assertSame(0, TimeEntry::whereNull('stopped_at')->count());
    }

    public function test_manager_sees_empty_report_for_period_without_entries(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->get(route('reports.index', ['tab' => 'time', 'from' => '2020-01-01', 'to' => '2020-01-02']))->assertOk();
    }
}
