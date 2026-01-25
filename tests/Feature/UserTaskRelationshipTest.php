<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTaskRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_tasks(): void
    {
        $user = User::factory()->create();
        
        $task = $user->tasks()->create([
            'title' => 'Test Task',
            'description' => 'Description',
        ]);

        $this->assertCount(1, $user->tasks);
        $this->assertEquals('Test Task', $user->tasks->first()->title);
        $this->assertEquals($user->id, $task->user_id);
    }

    public function test_task_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $task->user);
        $this->assertEquals($user->id, $task->user->id);
    }
}
