<?php

namespace App\Http\Controllers;

use App\Services\Stores\StoreContext;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __invoke(StoreContext $storeContext): Response
    {
        $storeId = $storeContext->id();
        $members = DB::table('Employee')->join('StoreMember', 'StoreMember.employeeId', '=', 'Employee.id')->where('StoreMember.storeId', $storeId)->where('Employee.status', 'ACTIVE')->orderBy('Employee.name')->get(['Employee.*', 'StoreMember.role as storeRole'])->map(function ($member) use ($storeId) {
            $member->totalTasks = DB::table('TaskAssignee')->join('Task', 'Task.id', '=', 'TaskAssignee.taskId')->where('Task.storeId', $storeId)->where('employeeId', $member->id)->count();
            $member->completedTasks = DB::table('TaskAssignee')->join('Task', 'Task.id', '=', 'TaskAssignee.taskId')->where('Task.storeId', $storeId)->where('employeeId', $member->id)->where('Task.status', 'COMPLETED')->count();
            $member->overdueTasks = DB::table('TaskAssignee')->join('Task', 'Task.id', '=', 'TaskAssignee.taskId')->where('Task.storeId', $storeId)->where('employeeId', $member->id)->where('Task.status', '!=', 'COMPLETED')->where('Task.dueDate', '<', now())->count();

            return $member;
        });

        return Inertia::render('Team',['members' => $members]);
    }
}
