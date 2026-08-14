<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingRole = DB::table('Role')
            ->where('id', 'system-super-admin')
            ->orWhere('key', 'super-admin')
            ->orWhere('name', '超级管理员')
            ->first();
        $roleId = (string) ($existingRole?->id ?? 'system-super-admin');

        DB::table('Role')->updateOrInsert(
            ['id' => $roleId],
            [
                'key' => 'super-admin',
                'name' => '超级管理员',
                'description' => '自动拥有全部店铺、页面和系统管理权限',
                'isSystem' => true,
                'createdAt' => $existingRole?->createdAt ?? now(),
                'updatedAt' => now(),
            ],
        );

        $employeeId = DB::table('Employee')->where('username', 'admin')->value('id');

        if ($employeeId) {
            DB::table('Employee')->where('id', $employeeId)->update([
                'status' => 'ACTIVE',
                'isDefaultAdmin' => true,
                'updatedAt' => now(),
            ]);
            DB::table('EmployeeRole')->updateOrInsert(
                ['employeeId' => $employeeId, 'roleId' => $roleId],
                ['assignedAt' => now()],
            );
        }

        foreach (DB::table('Permission')->pluck('id') as $permissionId) {
            DB::table('RolePermission')->updateOrInsert(
                ['roleId' => $roleId, 'permissionId' => (string) $permissionId],
                ['assignedAt' => now()],
            );
        }
    }

    public function down(): void
    {
        // Full access is a security invariant; rolling back must not silently revoke it.
    }
};
