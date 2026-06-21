<?php

namespace App\LiveComponents;

use SPPMod\SPPView\LiveComponent;
use SPP\Attributes\Validate;
use SPP\Attributes\Session;
use SPPMod\SPPView\Attributes\Title;
use SPP\Attributes\On;

#[Title('Tasks Board - Live Showcase')]
class TasksComponent extends LiveComponent
{
    #[Validate(['required', 'min:3'])]
    public string $newTask = '';

    #[Session]
    public string $draftNote = '';

    public array $tasks = [
        ['id' => 1, 'title' => 'Build the ultimate framework', 'done' => true],
        ['id' => 2, 'title' => 'Implement SPPLive Showcase', 'done' => false],
    ];

    public function addTask()
    {
        $this->tasks[] = [
            'id' => time(),
            'title' => $this->newTask,
            'done' => false
        ];
        $this->newTask = '';
        $this->emit('task-created');
    }

    public function toggleTask($id)
    {
        foreach ($this->tasks as &$t) {
            if ($t['id'] == $id) {
                $t['done'] = !$t['done'];
                break;
            }
        }
    }

    public function deleteTask($id)
    {
        $this->tasks = array_values(array_filter($this->tasks, fn($t) => $t['id'] != $id));
    }

    #[On('task-created')]
    public function onTaskCreated()
    {
        // Re-render when another user creates a task (Simulated here)
    }

    public function render(): string
    {
        $html = <<<HTML
        <div class="p-6 bg-white rounded shadow">
            <h2 class="text-xl font-bold mb-4">Task Board</h2>
            
            <form wire:submit.prevent="addTask" class="mb-6">
                <input type="text" wire:model="newTask" placeholder="Add a new task..." class="border p-2 rounded w-full mb-2">
                <!-- Errors automatically handled by #[Validate] -->
                
                <textarea wire:model="draftNote" placeholder="Session persisted draft notes..." class="border p-2 rounded w-full mb-2"></textarea>
                
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Add Task</button>
            </form>

            <ul>
HTML;
        foreach ($this->tasks as $t) {
            $checked = $t['done'] ? 'checked' : '';
            $cross = $t['done'] ? 'line-through text-gray-400' : '';
            
            $html .= <<<HTML
                <li class="flex items-center justify-between mb-2 p-2 border-b">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:click.optimistic="toggleTask({$t['id']})" {$checked}>
                        <span class="{$cross}">{$t['title']}</span>
                    </div>
                    <button wire:click="deleteTask({$t['id']})" wire:confirm="Are you sure you want to delete this task?" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                </li>
HTML;
        }

        $html .= <<<HTML
            </ul>
        </div>
        HTML;

        return $html;
    }
}
