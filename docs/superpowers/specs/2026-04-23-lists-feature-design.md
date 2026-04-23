# Lists Feature — Design Spec

## Overview

Add persistent lists to TasksSphere. Two types:

- **Task Lists** — group existing tasks under a named list
- **Checklists** — simple items with text + optional note + completed state (no due dates)

Lists can be owned by a user (private) or a team (shared).

## Data Model

### `task_lists` table

| Column       | Type                    | Notes                              |
|-------------|-------------------------|-------------------------------------|
| id          | bigint PK               |                                     |
| user_id     | FK users (nullable)     | Private list owner                  |
| team_id     | FK teams (nullable)     | Team list owner                     |
| title       | string(255)             | Required                            |
| description | text (nullable)         |                                     |
| type        | enum: tasks, checklist  | Determines child model              |
| icon        | string(32) (nullable)   | Emoji string                        |
| color       | string(7) (nullable)    | Hex color, e.g. #ef4444             |
| position    | integer default 0       | Sort order                          |
| created_at  | timestamp               |                                     |
| updated_at  | timestamp               |                                     |
| deleted_at  | timestamp (nullable)    | Soft delete                         |

**Constraint:** At least one of `user_id` or `team_id` must be set. Enforced in model validation, not DB constraint.

**Indexes:**
- `(user_id, type, position)` — user's lists sorted
- `(team_id, type, position)` — team's lists sorted

### `list_items` table (for type=checklist only)

| Column        | Type                     | Notes                    |
|--------------|--------------------------|--------------------------|
| id           | bigint PK                |                          |
| task_list_id | FK task_lists (cascade)  |                          |
| title        | string(255)              | Required                 |
| note         | text (nullable)          | Optional note            |
| is_completed | boolean default false    |                          |
| position     | integer default 0        | Sort order               |
| created_at   | timestamp                |                          |
| updated_at   | timestamp                |                          |

**Index:** `(task_list_id, position)`

### `tasks` table modification

Add nullable FK column:

| Column       | Type                           | Notes                    |
|-------------|--------------------------------|--------------------------|
| task_list_id | FK task_lists (set null)       | Optional list assignment |

## Models

### TaskList

- `belongsTo(User)` — nullable
- `belongsTo(Team)` — nullable
- `hasMany(ListItem)` — only meaningful when type=checklist
- `hasMany(Task)` — only meaningful when type=tasks
- Scopes: `forUser($userId)`, `forTeam($teamId)`, `ofType($type)`
- Casts: `type` as enum, `deleted_at` as datetime

### ListItem

- `belongsTo(TaskList)`
- Fillable: title, note, is_completed, position
- Casts: `is_completed` as boolean

### Task (modified)

- Add `belongsTo(TaskList)` — nullable
- Add `task_list_id` to fillable

## Ownership Logic

- `user_id` set, `team_id` null → private list, only that user sees it
- `team_id` set, `user_id` null → team list, visible to all team members
- Both null → invalid, rejected by validation
- Both set → treat as team list (team_id takes precedence)

## Authorization

### TaskListPolicy

- `viewAny` — authenticated user (filters applied in queries)
- `view` — owner (user_id match) OR member of team (team_id match)
- `create` — authenticated user
- `update` — same as view
- `delete` — owner OR team admin

### ListItemPolicy

- Delegates to parent TaskList policy

## Livewire Components

### ListManager

**Purpose:** Overview of all lists (private + team), create/edit/delete lists.

**Properties:**
- `lists` — collection of user's private + current team's lists
- `title`, `description`, `type`, `icon`, `color` — form fields
- `isEditing`, `editingList` — edit state

**Features:**
- Two sections: "My Lists" and "Team Lists"
- Each list shows: icon, title, item/task count, color indicator
- Click list → navigates to detail view
- Create button opens inline form
- Type selector: Tasks / Checklist
- Icon picker (emoji grid) + color swatches (same as TimeSphere projects)
- Drag handle for reorder (position field)

### TaskListDetail

**Purpose:** Shows tasks assigned to a specific task-list.

**Properties:**
- `taskList` — the list model
- `availableTasks` — unassigned tasks for adding

**Features:**
- Header with list icon, title, description, edit/delete buttons
- List of assigned tasks (reuse existing task display from TaskManager)
- "Add existing task" dropdown — shows unassigned tasks
- "Create new task" button — opens task form with list pre-selected
- Remove task from list (sets task_list_id to null, does NOT delete task)

### ChecklistDetail

**Purpose:** Shows checklist items with inline add/edit/complete.

**Properties:**
- `taskList` — the list model
- `items` — ordered list items
- `newItemTitle` — inline add field

**Features:**
- Header with list icon, title, description
- Items with checkbox, title, optional note (expandable)
- Inline add at bottom (type + enter)
- Inline edit (click title to edit)
- Swipe or button to delete item
- Completed items section (collapsible, at bottom)
- Drag to reorder

## Routes (Web)

```
GET    /lists                    → ListManager (overview)
GET    /lists/{taskList}         → TaskListDetail or ChecklistDetail (based on type)
```

Both within existing authenticated middleware group.

## API Routes

```
GET    /api/task-lists                        → index (user + team lists)
POST   /api/task-lists                        → store
GET    /api/task-lists/{taskList}              → show (with items/tasks)
PUT    /api/task-lists/{taskList}              → update
DELETE /api/task-lists/{taskList}              → destroy

GET    /api/task-lists/{taskList}/items        → index items (checklist only)
POST   /api/task-lists/{taskList}/items        → store item
PUT    /api/task-lists/{taskList}/items/{item} → update item
DELETE /api/task-lists/{taskList}/items/{item} → destroy item

PUT    /api/tasks/{task}                      → existing, add task_list_id support
```

## UI Design

### List Overview (ListManager)

- Card grid or list view
- Each card: colored left border, emoji icon, title, count badge, type indicator
- Separate sections for private and team lists
- FAB or button for "New List"

### Checklist Detail

- Clean, minimal design matching existing TaskManager style
- Input at bottom for quick add
- Checkbox + title inline, note expandable below
- Completed items grayed out, at bottom, collapsible
- Empty state with icon and "Add your first item" prompt

### Task List Detail

- Shows assigned tasks in same format as main TaskManager
- Dropdown/search to assign existing tasks
- Button to create task directly in list

## Validation Rules

### TaskList

```php
'title'       => 'required|string|max:255',
'description' => 'nullable|string',
'type'        => 'required|in:tasks,checklist',
'icon'        => 'nullable|string|max:32',
'color'       => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
'user_id'     => 'nullable|exists:users,id',
'team_id'     => 'nullable|exists:teams,id',
```

### ListItem

```php
'title'        => 'required|string|max:255',
'note'         => 'nullable|string',
'is_completed' => 'boolean',
'position'     => 'integer|min:0',
```

## Migration Order

1. Create `task_lists` table
2. Create `list_items` table
3. Add `task_list_id` to `tasks` table

## Out of Scope

- Real-time collaboration (WebSocket sync between team members)
- List sharing via link
- List templates/duplication
- Sub-lists / nesting
- File attachments on list items
- Due dates on checklist items (that makes them tasks)
