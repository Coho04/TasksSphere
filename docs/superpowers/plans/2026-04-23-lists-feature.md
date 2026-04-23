# Lists Feature Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add persistent task lists and checklists to TasksSphere, supporting both user-owned (private) and team-owned (shared) lists.

**Architecture:** Two list types share a `task_lists` table differentiated by `type` enum. Task lists group existing Task models via a nullable FK on tasks. Checklists use a dedicated `list_items` table for simple text+checkbox items. Three Livewire components handle the UI: ListManager (overview), TaskListDetail, and ChecklistDetail.

**Tech Stack:** Laravel 12, Livewire 4, Tailwind CSS v4, PHPUnit

---

### Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_04_23_000001_create_task_lists_table.php`
- Create: `database/migrations/2026_04_23_000002_create_list_items_table.php`
- Create: `database/migrations/2026_04_23_000003_add_task_list_id_to_tasks_table.php`

- [ ] **Step 1: Create task_lists migration**

```bash
cd /Users/colilg/PhpstormProjects/TasksSphere
php artisan make:migration create_task_lists_table
```

Replace the generated file contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('checklist'); // 'tasks' or 'checklist'
            $table->string('icon', 32)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'position']);
            $table->index(['team_id', 'type', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};
```

- [ ] **Step 2: Create list_items migration**

```bash
php artisan make:migration create_list_items_table
```

Replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->string('title');
            $table->text('note')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['task_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
```

- [ ] **Step 3: Add task_list_id to tasks migration**

```bash
php artisan make:migration add_task_list_id_to_tasks_table
```

Replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_list_id')->nullable()->after('user_id')
                ->constrained('task_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_list_id');
        });
    }
};
```

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

Expected: Three migrations run successfully, no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat: add task_lists, list_items tables and task_list_id FK on tasks"
```

---

### Task 2: Models (TaskList, ListItem) and Task Model Update

**Files:**
- Create: `app/Models/TaskList.php`
- Create: `app/Models/ListItem.php`
- Modify: `app/Models/Task.php:15-26` (add task_list_id to fillable, add relationship)

- [ ] **Step 1: Create TaskList model**

Create `app/Models/TaskList.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'team_id',
        'title',
        'description',
        'type',
        'icon',
        'color',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->whereNull('team_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isChecklist(): bool
    {
        return $this->type === 'checklist';
    }

    public function isTaskList(): bool
    {
        return $this->type === 'tasks';
    }

    public function itemCount(): int
    {
        if ($this->isChecklist()) {
            return $this->items()->count();
        }

        return $this->tasks()->count();
    }

    public function completedCount(): int
    {
        if ($this->isChecklist()) {
            return $this->items()->where('is_completed', true)->count();
        }

        return $this->tasks()->whereNotNull('completed_at')->count();
    }
}
```

- [ ] **Step 2: Create ListItem model**

Create `app/Models/ListItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItem extends Model
{
    protected $fillable = [
        'task_list_id',
        'title',
        'note',
        'is_completed',
        'position',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'position' => 'integer',
    ];

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }
}
```

- [ ] **Step 3: Update Task model**

In `app/Models/Task.php`, add `task_list_id` to the `$fillable` array (line 15-26):

```php
protected $fillable = [
    'user_id',
    'task_list_id',
    'title',
    'description',
    'due_at',
    'completed_at',
    'is_active',
    'is_archived',
    'recurrence_rule',
    'recurrence_timezone',
    'last_notified_at',
];
```

Add relationship method after the `completions()` method (after line 45):

```php
public function taskList()
{
    return $this->belongsTo(TaskList::class);
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/TaskList.php app/Models/ListItem.php app/Models/Task.php
git commit -m "feat: add TaskList and ListItem models, update Task with list relationship"
```

---

### Task 3: TaskListPolicy

**Files:**
- Create: `app/Policies/TaskListPolicy.php`

- [ ] **Step 1: Create TaskListPolicy**

Create `app/Policies/TaskListPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\TaskList;
use App\Models\User;

class TaskListPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaskList $taskList): bool
    {
        if ($taskList->user_id === $user->id) {
            return true;
        }

        if ($taskList->team_id && $user->belongsToTeam(\App\Models\Team::find($taskList->team_id))) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TaskList $taskList): bool
    {
        return $this->view($user, $taskList);
    }

    public function delete(User $user, TaskList $taskList): bool
    {
        if ($taskList->user_id === $user->id) {
            return true;
        }

        if ($taskList->team_id) {
            $team = \App\Models\Team::find($taskList->team_id);
            return $team && $user->ownsTeam($team);
        }

        return false;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Policies/TaskListPolicy.php
git commit -m "feat: add TaskListPolicy with user/team authorization"
```

---

### Task 4: Livewire ListManager Component

**Files:**
- Create: `app/Livewire/ListManager.php`
- Create: `resources/views/livewire/list-manager.blade.php`
- Modify: `routes/web.php` (add /lists route)

- [ ] **Step 1: Create ListManager Livewire component**

Create `app/Livewire/ListManager.php`:

```php
<?php

namespace App\Livewire;

use App\Models\TaskList;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListManager extends Component
{
    public $title = '';
    public $description = '';
    public $type = 'checklist';
    public $icon = '';
    public $color = '';
    public $showForm = false;
    public $isEditing = false;
    public $editingListId = null;

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'type' => 'required|in:tasks,checklist',
        'icon' => 'nullable|string|max:32',
        'color' => 'nullable|string|max:7',
    ];

    public function render()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam?->id;

        $myLists = TaskList::forUser($user->id)
            ->orderBy('position')
            ->get();

        $teamLists = $teamId
            ? TaskList::forTeam($teamId)->orderBy('position')->get()
            : collect();

        return view('livewire.list-manager', [
            'myLists' => $myLists,
            'teamLists' => $teamLists,
        ])->layout('layouts.app');
    }

    public function showCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function createList(): void
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'icon' => $this->icon ?: null,
            'color' => $this->color ?: null,
            'user_id' => Auth::id(),
        ];

        TaskList::create($data);
        $this->resetForm();
    }

    public function createTeamList(): void
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'icon' => $this->icon ?: null,
            'color' => $this->color ?: null,
            'team_id' => Auth::user()->currentTeam?->id,
        ];

        TaskList::create($data);
        $this->resetForm();
    }

    public function editList(int $id): void
    {
        $list = TaskList::findOrFail($id);
        $this->authorize('update', $list);

        $this->editingListId = $id;
        $this->title = $list->title;
        $this->description = $list->description;
        $this->type = $list->type;
        $this->icon = $list->icon ?? '';
        $this->color = $list->color ?? '';
        $this->isEditing = true;
        $this->showForm = true;
    }

    public function updateList(): void
    {
        $this->validate();

        $list = TaskList::findOrFail($this->editingListId);
        $this->authorize('update', $list);

        $list->update([
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon ?: null,
            'color' => $this->color ?: null,
        ]);

        $this->resetForm();
    }

    public function deleteList(int $id): void
    {
        $list = TaskList::findOrFail($id);
        $this->authorize('delete', $list);
        $list->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['title', 'description', 'type', 'icon', 'color', 'showForm', 'isEditing', 'editingListId']);
    }
}
```

- [ ] **Step 2: Create list-manager view**

Create `resources/views/livewire/list-manager.blade.php`:

```blade
<div class="p-4 sm:p-6 lg:p-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">{{ __('Listen') }}</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Organisiere deine Aufgaben und Einkaufslisten.') }}
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex gap-2">
                <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-full text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all">
                    {{ __('Aufgaben') }}
                </a>
                <button type="button" wire:click="showCreateForm" class="inline-flex items-center px-4 py-2 border border-transparent rounded-full shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Neue Liste') }}
                </button>
            </div>
        </div>

        <!-- Create/Edit Form -->
        @if($showForm)
        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden mb-10 border border-gray-100 dark:border-gray-700">
            <div class="p-6 sm:p-8">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                    {{ $isEditing ? __('Liste bearbeiten') : __('Neue Liste erstellen') }}
                </h2>
                <form wire:submit.prevent="{{ $isEditing ? 'updateList' : 'createList' }}" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Name') }}</label>
                        <input type="text" wire:model="title" placeholder="{{ __('z.B. Einkaufsliste') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm p-3">
                        @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Beschreibung (optional)') }}</label>
                        <textarea wire:model="description" rows="2" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm p-3"></textarea>
                    </div>

                    @unless($isEditing)
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Typ') }}</label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" wire:model="type" value="checklist" class="peer sr-only">
                                <div class="p-4 rounded-xl border-2 text-center transition-all peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/30 border-gray-200 dark:border-gray-600 hover:border-gray-300">
                                    <span class="text-2xl block mb-1">📋</span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Checkliste') }}</span>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" wire:model="type" value="tasks" class="peer sr-only">
                                <div class="p-4 rounded-xl border-2 text-center transition-all peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/30 border-gray-200 dark:border-gray-600 hover:border-gray-300">
                                    <span class="text-2xl block mb-1">✅</span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Aufgaben-Liste') }}</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    @endunless

                    <!-- Icon -->
                    <div x-data="{ emojis: ['📋','📝','🛒','🏠','💼','📚','🎯','❤️','⭐','🔥','🎉','🎵','✈️','🍔','💪','🧹','📦','🎁','🔧','💡'], showPicker: false }">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Icon') }}</label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showPicker = !showPicker"
                                    class="w-12 h-12 rounded-xl border-2 border-gray-200 dark:border-gray-600 flex items-center justify-center text-2xl hover:border-gray-300 transition-all">
                                <span x-text="$wire.icon || '➕'"></span>
                            </button>
                            <span class="text-xs text-gray-400" x-show="!$wire.icon">{{ __('Klicken zum Auswählen') }}</span>
                        </div>
                        <div x-show="showPicker" x-cloak x-transition class="mt-2 flex flex-wrap gap-2">
                            <template x-for="e in emojis" :key="e">
                                <button type="button" @click="$wire.icon = e; showPicker = false"
                                        :class="$wire.icon === e ? 'ring-2 ring-blue-500 scale-110' : ''"
                                        class="w-10 h-10 rounded-lg flex items-center justify-center text-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-all cursor-pointer"
                                        x-text="e"></button>
                            </template>
                        </div>
                    </div>

                    <!-- Color -->
                    <div x-data="{ colors: ['#ef4444','#f97316','#f59e0b','#22c55e','#10b981','#06b6d4','#3b82f6','#6366f1','#8b5cf6','#ec4899','#64748b','#1e293b'] }">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Farbe') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="c in colors" :key="c">
                                <button type="button" @click="$wire.color = c"
                                        :style="`background-color: ${c}`"
                                        :class="$wire.color === c ? 'ring-2 ring-offset-2 ring-offset-white dark:ring-offset-gray-800 ring-blue-500 scale-110' : 'hover:scale-110'"
                                        class="w-8 h-8 rounded-full cursor-pointer transition-all duration-150 shadow-sm">
                                    <span x-show="$wire.color === c" class="flex items-center justify-center h-full">
                                        <svg class="w-4 h-4 text-white drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="resetForm" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                            {{ __('Abbrechen') }}
                        </button>
                        @unless($isEditing)
                        @if(Auth::user()->currentTeam)
                        <button type="button" wire:click="createTeamList" class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 rounded-full hover:bg-blue-200 transition-all">
                            {{ __('Als Team-Liste') }}
                        </button>
                        @endif
                        @endunless
                        <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-full hover:bg-blue-700 shadow-sm transition-all">
                            {{ $isEditing ? __('Speichern') : __('Erstellen') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <!-- My Lists -->
        <div class="mb-10">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('Meine Listen') }}</h2>
            @if($myLists->isEmpty())
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <span class="text-4xl">📝</span>
                    <p class="mt-3 text-gray-500 dark:text-gray-400">{{ __('Noch keine Listen erstellt.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($myLists as $list)
                        <a href="{{ route('lists.show', $list) }}" wire:navigate
                           class="group bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 hover:shadow-lg transition-all"
                           style="border-left: 4px solid {{ $list->color ?? '#6366f1' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">{{ $list->icon ?? ($list->isChecklist() ? '📋' : '✅') }}</span>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 transition-colors">{{ $list->title }}</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $list->completedCount() }}/{{ $list->itemCount() }} {{ $list->isChecklist() ? __('Einträge') : __('Aufgaben') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click.prevent="editList({{ $list->id }})" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button wire:click.prevent="deleteList({{ $list->id }})" wire:confirm="{{ __('Liste wirklich löschen?') }}" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 text-gray-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Team Lists -->
        @if(Auth::user()->currentTeam)
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('Team-Listen') }}</h2>
            @if($teamLists->isEmpty())
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <span class="text-4xl">👥</span>
                    <p class="mt-3 text-gray-500 dark:text-gray-400">{{ __('Noch keine Team-Listen.') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($teamLists as $list)
                        <a href="{{ route('lists.show', $list) }}" wire:navigate
                           class="group bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 hover:shadow-lg transition-all"
                           style="border-left: 4px solid {{ $list->color ?? '#6366f1' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">{{ $list->icon ?? ($list->isChecklist() ? '📋' : '✅') }}</span>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 transition-colors">{{ $list->title }}</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $list->completedCount() }}/{{ $list->itemCount() }} {{ $list->isChecklist() ? __('Einträge') : __('Aufgaben') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click.prevent="editList({{ $list->id }})" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button wire:click.prevent="deleteList({{ $list->id }})" wire:confirm="{{ __('Liste wirklich löschen?') }}" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 text-gray-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        @endif
    </div>
</div>
```

- [ ] **Step 3: Add routes**

In `routes/web.php`, inside the authenticated middleware group, add:

```php
Route::get('/lists', \App\Livewire\ListManager::class)->name('lists.index');
Route::get('/lists/{taskList}', \App\Livewire\ListDetail::class)->name('lists.show');
```

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/ListManager.php resources/views/livewire/list-manager.blade.php routes/web.php
git commit -m "feat: add ListManager Livewire component with overview UI"
```

---

### Task 5: Livewire ListDetail Component (handles both types)

**Files:**
- Create: `app/Livewire/ListDetail.php`
- Create: `resources/views/livewire/list-detail.blade.php`

- [ ] **Step 1: Create ListDetail Livewire component**

Create `app/Livewire/ListDetail.php`:

```php
<?php

namespace App\Livewire;

use App\Models\ListItem;
use App\Models\TaskList;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ListDetail extends Component
{
    public TaskList $taskList;

    // Checklist item form
    public $newItemTitle = '';
    public $editingItemId = null;
    public $editingItemTitle = '';
    public $editingItemNote = '';
    public $showCompletedItems = false;

    // Task assignment
    public $showTaskPicker = false;

    public function mount(TaskList $taskList): void
    {
        $this->authorize('view', $taskList);
        $this->taskList = $taskList;
    }

    public function render()
    {
        if ($this->taskList->isChecklist()) {
            $activeItems = $this->taskList->items()
                ->where('is_completed', false)
                ->orderBy('position')
                ->get();

            $completedItems = $this->taskList->items()
                ->where('is_completed', true)
                ->orderBy('updated_at', 'desc')
                ->get();

            return view('livewire.list-detail', [
                'activeItems' => $activeItems,
                'completedItems' => $completedItems,
            ])->layout('layouts.app');
        }

        // Task list
        $assignedTasks = $this->taskList->tasks()
            ->where('is_archived', false)
            ->orderBy('due_at')
            ->get();

        $availableTasks = Auth::user()->tasks()
            ->whereNull('task_list_id')
            ->where('is_archived', false)
            ->whereNull('completed_at')
            ->orderBy('title')
            ->get();

        return view('livewire.list-detail', [
            'assignedTasks' => $assignedTasks,
            'availableTasks' => $availableTasks,
        ])->layout('layouts.app');
    }

    // --- Checklist methods ---

    public function addItem(): void
    {
        $this->validate(['newItemTitle' => 'required|string|max:255']);
        $this->authorize('update', $this->taskList);

        $maxPosition = $this->taskList->items()->max('position') ?? -1;

        $this->taskList->items()->create([
            'title' => $this->newItemTitle,
            'position' => $maxPosition + 1,
        ]);

        $this->newItemTitle = '';
    }

    public function toggleItem(int $itemId): void
    {
        $item = ListItem::where('task_list_id', $this->taskList->id)->findOrFail($itemId);
        $this->authorize('update', $this->taskList);
        $item->update(['is_completed' => !$item->is_completed]);
    }

    public function startEditItem(int $itemId): void
    {
        $item = ListItem::where('task_list_id', $this->taskList->id)->findOrFail($itemId);
        $this->editingItemId = $itemId;
        $this->editingItemTitle = $item->title;
        $this->editingItemNote = $item->note ?? '';
    }

    public function saveEditItem(): void
    {
        $this->validate([
            'editingItemTitle' => 'required|string|max:255',
            'editingItemNote' => 'nullable|string',
        ]);

        $item = ListItem::where('task_list_id', $this->taskList->id)->findOrFail($this->editingItemId);
        $this->authorize('update', $this->taskList);

        $item->update([
            'title' => $this->editingItemTitle,
            'note' => $this->editingItemNote ?: null,
        ]);

        $this->cancelEditItem();
    }

    public function cancelEditItem(): void
    {
        $this->reset(['editingItemId', 'editingItemTitle', 'editingItemNote']);
    }

    public function deleteItem(int $itemId): void
    {
        $item = ListItem::where('task_list_id', $this->taskList->id)->findOrFail($itemId);
        $this->authorize('update', $this->taskList);
        $item->delete();
    }

    public function clearCompleted(): void
    {
        $this->authorize('update', $this->taskList);
        $this->taskList->items()->where('is_completed', true)->delete();
    }

    // --- Task list methods ---

    public function assignTask(int $taskId): void
    {
        $this->authorize('update', $this->taskList);
        $task = Auth::user()->tasks()->findOrFail($taskId);
        $task->update(['task_list_id' => $this->taskList->id]);
        $this->showTaskPicker = false;
    }

    public function removeTask(int $taskId): void
    {
        $this->authorize('update', $this->taskList);
        $task = Auth::user()->tasks()->findOrFail($taskId);
        $task->update(['task_list_id' => null]);
    }
}
```

- [ ] **Step 2: Create list-detail view**

Create `resources/views/livewire/list-detail.blade.php`:

```blade
<div class="p-4 sm:p-6 lg:p-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('lists.index') }}" wire:navigate class="text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 flex items-center gap-1 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Zurück zu Listen') }}
            </a>
            <div class="flex items-center gap-3">
                <span class="text-3xl">{{ $taskList->icon ?? ($taskList->isChecklist() ? '📋' : '✅') }}</span>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $taskList->title }}</h1>
                    @if($taskList->description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $taskList->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if($taskList->isChecklist())
            {{-- ========== CHECKLIST VIEW ========== --}}

            <!-- Active Items -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                @if($activeItems->isEmpty() && $completedItems->isEmpty())
                    <div class="text-center py-16">
                        <span class="text-5xl">📝</span>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">{{ __('Füge deinen ersten Eintrag hinzu.') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($activeItems as $item)
                            <li class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group"
                                wire:key="item-{{ $item->id }}">
                                @if($editingItemId === $item->id)
                                    <div class="flex-1 space-y-2">
                                        <input type="text" wire:model="editingItemTitle" wire:keydown.enter="saveEditItem"
                                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm p-2">
                                        <textarea wire:model="editingItemNote" rows="2" placeholder="{{ __('Notiz (optional)') }}"
                                                  class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm p-2"></textarea>
                                        <div class="flex gap-2">
                                            <button wire:click="saveEditItem" class="text-xs text-blue-600 hover:text-blue-800 font-medium">{{ __('Speichern') }}</button>
                                            <button wire:click="cancelEditItem" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Abbrechen') }}</button>
                                        </div>
                                    </div>
                                @else
                                    <button wire:click="toggleItem({{ $item->id }})" class="mt-1 w-5 h-5 rounded border-2 border-gray-300 dark:border-gray-500 flex-shrink-0 hover:border-blue-500 transition-colors cursor-pointer"></button>
                                    <div class="flex-1 min-w-0 cursor-pointer" wire:click="startEditItem({{ $item->id }})">
                                        <p class="text-sm text-gray-900 dark:text-white">{{ $item->title }}</p>
                                        @if($item->note)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $item->note }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="deleteItem({{ $item->id }})" class="p-1 rounded opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <!-- Inline Add -->
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700">
                    <form wire:submit.prevent="addItem" class="flex items-center gap-3">
                        <span class="text-gray-300 dark:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </span>
                        <input type="text" wire:model="newItemTitle" placeholder="{{ __('Eintrag hinzufügen...') }}"
                               wire:keydown.enter="addItem"
                               class="flex-1 border-0 bg-transparent text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-0 p-0">
                    </form>
                </div>
            </div>

            <!-- Completed Items -->
            @if($completedItems->isNotEmpty())
                <div class="mt-6">
                    <button wire:click="$toggle('showCompletedItems')" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-3">
                        <svg class="w-4 h-4 transition-transform {{ $showCompletedItems ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        {{ __('Erledigt') }} ({{ $completedItems->count() }})
                    </button>

                    @if($showCompletedItems)
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($completedItems as $item)
                                    <li class="flex items-center gap-3 px-5 py-3 group" wire:key="completed-{{ $item->id }}">
                                        <button wire:click="toggleItem({{ $item->id }})" class="w-5 h-5 rounded bg-blue-500 flex items-center justify-center flex-shrink-0 cursor-pointer hover:bg-blue-600 transition-colors">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                        <span class="text-sm text-gray-400 dark:text-gray-500 line-through flex-1">{{ $item->title }}</span>
                                        <button wire:click="deleteItem({{ $item->id }})" class="p-1 rounded opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700">
                                <button wire:click="clearCompleted" wire:confirm="{{ __('Alle erledigten Einträge löschen?') }}" class="text-xs text-red-500 hover:text-red-700">
                                    {{ __('Erledigte löschen') }}
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

        @else
            {{-- ========== TASK LIST VIEW ========== --}}

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <!-- Assigned Tasks -->
                @if(isset($assignedTasks) && $assignedTasks->isNotEmpty())
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($assignedTasks as $task)
                            <li class="flex items-center gap-3 px-5 py-3 group" wire:key="task-{{ $task->id }}">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $task->title }}</p>
                                    @if($task->due_at)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $task->due_at->format('d.m.Y H:i') }}
                                            @if($task->isRecurring())
                                                <span class="ml-1 text-blue-500">↻</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                <button wire:click="removeTask({{ $task->id }})" class="p-1 rounded opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-all" title="{{ __('Aus Liste entfernen') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-16">
                        <span class="text-5xl">✅</span>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">{{ __('Noch keine Aufgaben zugeordnet.') }}</p>
                    </div>
                @endif

                <!-- Add Task -->
                <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700">
                    <button wire:click="$toggle('showTaskPicker')" class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Aufgabe zuordnen') }}
                    </button>

                    @if($showTaskPicker && isset($availableTasks))
                        <div class="mt-3 max-h-60 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600">
                            @forelse($availableTasks as $task)
                                <button wire:click="assignTask({{ $task->id }})" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">
                                    {{ $task->title }}
                                    @if($task->due_at)
                                        <span class="text-xs text-gray-400 ml-2">{{ $task->due_at->format('d.m.') }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="px-4 py-3 text-sm text-gray-400">{{ __('Keine freien Aufgaben vorhanden.') }}</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
```

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/ListDetail.php resources/views/livewire/list-detail.blade.php
git commit -m "feat: add ListDetail component for checklist and task list views"
```

---

### Task 6: API Controller for Task Lists and List Items

**Files:**
- Create: `app/Http/Controllers/Api/TaskListApiController.php`
- Create: `app/Http/Controllers/Api/ListItemApiController.php`
- Modify: `routes/api.php` (add routes)
- Modify: `app/Http/Controllers/Api/TaskApiController.php:98-112` (add task_list_id to update validation)

- [ ] **Step 1: Create TaskListApiController**

Create `app/Http/Controllers/Api/TaskListApiController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskListApiController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam?->id;

        $lists = TaskList::where(function ($q) use ($user, $teamId) {
            $q->where('user_id', $user->id);
            if ($teamId) {
                $q->orWhere('team_id', $teamId);
            }
        })
        ->orderBy('position')
        ->get();

        return $lists;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:tasks,checklist',
            'icon' => 'nullable|string|max:32',
            'color' => 'nullable|string|max:7',
            'team_id' => 'nullable|integer',
        ]);

        if (isset($validated['team_id'])) {
            $team = \App\Models\Team::findOrFail($validated['team_id']);
            abort_unless(Auth::user()->belongsToTeam($team), 403);
            $validated['user_id'] = null;
        } else {
            $validated['user_id'] = Auth::id();
            $validated['team_id'] = null;
        }

        return TaskList::create($validated);
    }

    public function show(TaskList $taskList)
    {
        $this->authorize('view', $taskList);

        $taskList->load($taskList->isChecklist() ? 'items' : 'tasks');

        return $taskList;
    }

    public function update(Request $request, TaskList $taskList)
    {
        $this->authorize('update', $taskList);

        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:32',
            'color' => 'nullable|string|max:7',
            'position' => 'nullable|integer|min:0',
        ]);

        $taskList->update($validated);

        return $taskList;
    }

    public function destroy(TaskList $taskList)
    {
        $this->authorize('delete', $taskList);
        $taskList->delete();

        return response()->json(['message' => 'List deleted']);
    }
}
```

- [ ] **Step 2: Create ListItemApiController**

Create `app/Http/Controllers/Api/ListItemApiController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListItem;
use App\Models\TaskList;
use Illuminate\Http\Request;

class ListItemApiController extends Controller
{
    public function index(TaskList $taskList)
    {
        $this->authorize('view', $taskList);

        return $taskList->items()->orderBy('position')->get();
    }

    public function store(Request $request, TaskList $taskList)
    {
        $this->authorize('update', $taskList);
        abort_unless($taskList->isChecklist(), 422, 'Items can only be added to checklists.');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
        ]);

        $maxPosition = $taskList->items()->max('position') ?? -1;
        $validated['position'] = $maxPosition + 1;

        return $taskList->items()->create($validated);
    }

    public function update(Request $request, TaskList $taskList, ListItem $item)
    {
        $this->authorize('update', $taskList);
        abort_unless($item->task_list_id === $taskList->id, 404);

        $validated = $request->validate([
            'title' => 'string|max:255',
            'note' => 'nullable|string',
            'is_completed' => 'boolean',
            'position' => 'integer|min:0',
        ]);

        $item->update($validated);

        return $item;
    }

    public function destroy(TaskList $taskList, ListItem $item)
    {
        $this->authorize('update', $taskList);
        abort_unless($item->task_list_id === $taskList->id, 404);

        $item->delete();

        return response()->json(['message' => 'Item deleted']);
    }
}
```

- [ ] **Step 3: Add API routes**

In `routes/api.php`, inside the `auth:sanctum` middleware group, add:

```php
// Task Lists
Route::get('/task-lists', [\App\Http\Controllers\Api\TaskListApiController::class, 'index']);
Route::post('/task-lists', [\App\Http\Controllers\Api\TaskListApiController::class, 'store']);
Route::get('/task-lists/{taskList}', [\App\Http\Controllers\Api\TaskListApiController::class, 'show']);
Route::put('/task-lists/{taskList}', [\App\Http\Controllers\Api\TaskListApiController::class, 'update']);
Route::delete('/task-lists/{taskList}', [\App\Http\Controllers\Api\TaskListApiController::class, 'destroy']);

// List Items
Route::get('/task-lists/{taskList}/items', [\App\Http\Controllers\Api\ListItemApiController::class, 'index']);
Route::post('/task-lists/{taskList}/items', [\App\Http\Controllers\Api\ListItemApiController::class, 'store']);
Route::put('/task-lists/{taskList}/items/{item}', [\App\Http\Controllers\Api\ListItemApiController::class, 'update']);
Route::delete('/task-lists/{taskList}/items/{item}', [\App\Http\Controllers\Api\ListItemApiController::class, 'destroy']);
```

- [ ] **Step 4: Add task_list_id to TaskApiController update validation**

In `app/Http/Controllers/Api/TaskApiController.php`, in the `update` method's validate array (around line 98-112), add:

```php
'task_list_id' => 'nullable|integer|exists:task_lists,id',
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TaskListApiController.php app/Http/Controllers/Api/ListItemApiController.php routes/api.php app/Http/Controllers/Api/TaskApiController.php
git commit -m "feat: add API endpoints for task lists and list items"
```

---

### Task 7: Add "Lists" link to dashboard navigation

**Files:**
- Modify: `resources/views/livewire/task-manager.blade.php:4-16` (add link to lists)

- [ ] **Step 1: Add link in TaskManager header**

In `resources/views/livewire/task-manager.blade.php`, in the header section (around line 11-16), add a link to lists next to the "Neue Aufgabe" button:

```blade
<div class="mt-4 md:mt-0 flex gap-2">
    <a href="{{ route('lists.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-full text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all">
        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
        {{ __('Listen') }}
    </a>
    <button type="button" wire:click="showCreateForm" class="inline-flex items-center px-4 py-2 border border-transparent rounded-full shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('Neue Aufgabe') }}
    </button>
</div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/livewire/task-manager.blade.php
git commit -m "feat: add Lists navigation link to task manager header"
```

---

### Task 8: Run tests and verify

**Files:** No new files

- [ ] **Step 1: Run existing tests to check for regressions**

```bash
cd /Users/colilg/PhpstormProjects/TasksSphere
php artisan test
```

Expected: All existing tests pass. No regressions.

- [ ] **Step 2: Verify routes are registered**

```bash
php artisan route:list --path=lists
php artisan route:list --path=task-lists
```

Expected: Lists routes and API task-lists routes appear.

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

Expected: Build succeeds.

- [ ] **Step 4: Commit any fixes if needed**

```bash
git add -A
git commit -m "fix: resolve any test/build issues from lists feature"
```
