<?php

namespace App\Http\Controllers;

use App\Services\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(StoreContext $storeContext): Response
    {
        $tasks = DB::table('Task')->where('storeId', $storeContext->id())->orderBy('dueDate')->get()->map(function ($task) {
            $task->assignees = DB::table('TaskAssignee')->join('Employee', 'Employee.id', '=', 'TaskAssignee.employeeId')->where('taskId', $task->id)->get(['Employee.id', 'Employee.name']);

            return $task;
        });

        $employees = DB::table('Employee')->join('StoreMember', 'StoreMember.employeeId', '=', 'Employee.id')->where('StoreMember.storeId', $storeContext->id())->where('Employee.status', 'ACTIVE')->orderBy('Employee.name')->get(['Employee.id', 'Employee.name']);

        return Inertia::render('Kanban', ['tasks' => $tasks, 'employees' => $employees]);
    }

    public function store(Request $request, StoreContext $storeContext): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string'], 'priority' => ['required', 'in:LOW,MEDIUM,HIGH,URGENT'], 'dueDate' => ['nullable', 'date'], 'assigneeIds' => ['array'], 'assigneeIds.*' => ['integer', 'exists:Employee,id']]);
        $creatorId = DB::table('Employee')->where('email', $request->user()->email)->value('id');
        $id = (string) Str::uuid();
        $storeId = $storeContext->id();
        DB::transaction(function () use ($data, $creatorId, $id, $storeId) {
            DB::table('Task')->insert(['id' => $id, 'storeId' => $storeId, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'status' => 'TODO', 'priority' => $data['priority'], 'dueDate' => $data['dueDate'] ?? null, 'creatorId' => $creatorId, 'createdAt' => now(), 'updatedAt' => now()]);
            foreach (array_unique($data['assigneeIds'] ?? []) as $eid) {
                DB::table('TaskAssignee')->insert(['taskId' => $id, 'employeeId' => $eid, 'assignedAt' => now()]);
                DB::table('Notification')->insert([
                    'id' => (string) Str::uuid(), 'type' => 'TASK_ASSIGNED', 'title' => '新任务已分配',
                    'content' => '任务：'.$data['title'], 'isRead' => false, 'recipientId' => $eid,
                    'taskId' => $id, 'createdAt' => now(),
                ]);
            }
        });

        return back()->with('success', '任务已创建');
    }

    public function update(Request $request, string $task, StoreContext $storeContext): RedirectResponse
    {
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:190'], 'description' => ['nullable', 'string'], 'status' => ['sometimes', 'in:TODO,IN_PROGRESS,COMPLETED'], 'priority' => ['sometimes', 'in:LOW,MEDIUM,HIGH,URGENT'], 'dueDate' => ['nullable', 'date']]);
        DB::table('Task')->where('storeId', $storeContext->id())->where('id', $task)->update([...$data, 'updatedAt' => now()]);

        return back()->with('success', '任务已更新');
    }

    public function destroy(string $task, StoreContext $storeContext): RedirectResponse
    {
        abort_unless(DB::table('Task')->where('storeId', $storeContext->id())->where('id', $task)->exists(), 404);
        DB::transaction(function () use ($task) {
            DB::table('TaskAssignee')->where('taskId', $task)->delete();
            DB::table('Notification')->where('taskId', $task)->delete();
            DB::table('Task')->where('id', $task)->delete();
        });

        return back()->with('success', '任务已删除');
    }
}
