<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __invoke(): Response
    {
        $members = DB::table('Employee')->where('status', 'ACTIVE')->orderBy('name')->get()->map(function ($member) {
            $member->totalTasks = DB::table('TaskAssignee')->where('employeeId', $member->id)->count();
            $member->completedTasks = DB::table('TaskAssignee')->join('Task', 'Task.id', '=', 'TaskAssignee.taskId')->where('employeeId', $member->id)->where('Task.status', 'COMPLETED')->count();
            $member->overdueTasks = DB::table('TaskAssignee')->join('Task', 'Task.id', '=', 'TaskAssignee.taskId')->where('employeeId', $member->id)->where('Task.status', '!=', 'COMPLETED')->where('Task.dueDate', '<', now())->count();

            return $member;
        });

        return Inertia::render('Team',['members' => $members]);
    }
}
