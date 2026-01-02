<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AddDependenciesRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as RoutingController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends RoutingController
{
 public function index(Request $request)
{
    $user = $request->user();

    $tasks = Task::with(['assignedUser', 'creator', 'dependencies'])
        ->when($user->isUser(), function ($q) use ($user) {
            $q->where('assigned_to', $user->id);
        })
        ->when($request->status, function ($q, $status) {
            $q->where('status', $status);
        })
        ->when($request->due_date_from, function ($q, $from) {
            $q->where('due_date', '>=', $from);
        })
        ->when($request->due_date_to, function ($q, $to) {
            $q->where('due_date', '<=', $to);
        })
        ->when($request->assigned_to && $user->isManager(), function ($q) use ($request) {
            $q->where('assigned_to', $request->assigned_to);
        })
        ->orderByDesc('created_at')
        ->paginate(15);

    return response()->json($tasks);
}


    public function store(StoreTaskRequest $request)
    {
 

        DB::beginTransaction();
        try {
            $task = Task::create([
                'title' => $request['title'],
                'description' => $request['description'] ?? null,
                'due_date' => $request['due_date'] ?? null,
                'assigned_to' => $request['assigned_to'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'pending',
            ]);

            if (isset($request['dependencies'])) {
                foreach ($request['dependencies'] as $depId) {
                    if ($this->wouldCreateCircularDependency($task->id, $depId)) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Circular dependency detected.',
                        ], 422);
                    }
                }
                $task->dependencies()->attach($request['dependencies']);
            }
            DB::commit();
            $task->load(['assignedUser', 'creator', 'dependencies']);
            return response()->json([
                'message' => 'Task created successfully',
                'task' => $task,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create task',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $task = Task::with(['assignedUser', 'creator', 'dependencies', 'dependents'])->find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        if ($request->user()->isUser() && $task->assigned_to !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only view tasks assigned to you.',
            ], 403);
        }

        return response()->json($task, 200);
    }

public function update(Request $request, $id)
{
    $task = Task::findOrFail($id);

    if ($request->user()->isUser()) {

        $request = app(UpdateTaskStatusRequest::class);

        if ($request->status === 'completed' && !$task->canBeCompleted()) {
            return response()->json([
                'message' => 'Cannot complete task. There are pending dependencies.',
            ], 422);
        }

        $task->update(['status' => $request->status]);

    } else {

        $request = app(UpdateTaskRequest::class);

        if ($request->status === 'completed' && !$task->canBeCompleted()) {
            return response()->json([
                'message' => 'Cannot complete task. There are pending dependencies.',
            ], 422);
        }

        $task->update($request->validated());
    }

    return response()->json([
        'message' => 'Task updated successfully',
        'task' => $task->load(['assignedUser', 'creator', 'dependencies']),
    ]);
}


    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. Only managers can delete tasks.',
            ], 403);
        }

        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully',
        ], 200);
    }

public function addDependencies(AddDependenciesRequest $request, Task $task)
{
    DB::beginTransaction();

    try {
        foreach ($request->dependencies as $depId) {

            if ($task->dependencies()->where('depends_on_task_id', $depId)->exists()) {
                continue;
            }

            if ($this->wouldCreateCircularDependency($task->id, $depId)) {
                throw ValidationException::withMessages([
                    'dependencies' => 'Circular dependency detected',
                ]);
            }
            $task->dependencies()->attach($depId);
        }
        DB::commit();
        return response()->json([
            'message' => 'Dependencies added successfully',
            'task' => $task->load('dependencies'),
        ], 200);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to add dependencies',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    private function wouldCreateCircularDependency($taskId, $dependencyId)
    {
        if ($taskId === $dependencyId) {
            return true;
        }

        return $this->hasTransitiveDependency($dependencyId, $taskId);
    }

    private function hasTransitiveDependency($taskId, $targetId, $visited = [])
    {
        if (in_array($taskId, $visited)) {
            return false;
        }

        $visited[] = $taskId;

        $dependencies = DB::table('task_dependencies')
            ->where('task_id', $taskId)
            ->pluck('depends_on_task_id');

        foreach ($dependencies as $depId) {
            if ($depId == $targetId) {
                return true;
            }

            if ($this->hasTransitiveDependency($depId, $targetId, $visited)) {
                return true;
            }
        }

        return false;
    }
}