<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as RoutingController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends RoutingController
{
    public function index(Request $request)
    {
        $query = Task::with(['assignedUser', 'creator', 'dependencies']);

        if ($request->user()->isUser()) {
            $query->where('assigned_to', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('due_date_from')) {
            $query->where('due_date', '>=', $request->due_date_from);
        }

        if ($request->has('due_date_to')) {
            $query->where('due_date', '<=', $request->due_date_to);
        }

        if ($request->has('assigned_to') && $request->user()->isManager()) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($tasks, 200);
    }

    public function store(Request $request)
    {
        if (!$request->user()->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. Only managers can create tasks.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date|after_or_equal:today',
            'assigned_to' => 'nullable|exists:users,id',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'exists:tasks,id',
        ]);

        DB::beginTransaction();
        try {
            $task = Task::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'pending',
            ]);

            if (isset($validated['dependencies'])) {
                foreach ($validated['dependencies'] as $depId) {
                    if ($this->wouldCreateCircularDependency($task->id, $depId)) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Circular dependency detected.',
                        ], 422);
                    }
                }
                $task->dependencies()->attach($validated['dependencies']);
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
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        if ($request->user()->isUser()) {
            if ($task->assigned_to !== $request->user()->id) {
                return response()->json([
                    'message' => 'Unauthorized. You can only update tasks assigned to you.',
                ], 403);
            }

            $validated = $request->validate([
                'status' => ['required', Rule::in(['pending', 'completed', 'canceled'])],
            ]);

            if ($validated['status'] === 'completed' && !$task->canBeCompleted()) {
                return response()->json([
                    'message' => 'Cannot complete task. There are pending dependencies.',
                ], 422);
            }

            $task->update(['status' => $validated['status']]);

            $task->load(['assignedUser', 'creator', 'dependencies']);

            return response()->json([
                'message' => 'Task status updated successfully',
                'task' => $task,
            ], 200);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['sometimes', Rule::in(['pending', 'completed', 'canceled'])],
            'due_date' => 'nullable|date|after_or_equal:today',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed' && !$task->canBeCompleted()) {
            return response()->json([
                'message' => 'Cannot complete task. There are pending dependencies.',
            ], 422);
        }

        $task->update($validated);

        $task->load(['assignedUser', 'creator', 'dependencies']);

        return response()->json([
            'message' => 'Task updated successfully',
            'task' => $task,
        ], 200);
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

    public function addDependencies(Request $request, $id)
    {
        if (!$request->user()->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. Only managers can add dependencies.',
            ], 403);
        }

        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        $validated = $request->validate([
            'dependencies' => 'required|array',
            'dependencies.*' => 'exists:tasks,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['dependencies'] as $depId) {
                if ($task->dependencies()->where('depends_on_task_id', $depId)->exists()) {
                    continue;
                }

                if ($this->wouldCreateCircularDependency($task->id, $depId)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Circular dependency detected.',
                    ], 422);
                }

                $task->dependencies()->attach($depId);
            }

            DB::commit();

            $task->load(['dependencies']);

            return response()->json([
                'message' => 'Dependencies added successfully',
                'task' => $task,
            ], 200);
        } catch (\Exception $e) {
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