<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@decomkt.local');
        $password = env('ADMIN_PASSWORD', 'admin123456');
        $name = env('ADMIN_NAME', '系统管理员');

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'email_verified_at' => now()],
        );

        DB::table('Employee')->updateOrInsert(
            ['username' => 'admin'],
            [
                'passwordHash' => Hash::make($password),
                'name' => $name,
                'email' => $email,
                'emailVerified' => true,
                'status' => 'ACTIVE',
                'isDefaultAdmin' => true,
                'createdAt' => now(),
                'updatedAt' => now(),
            ],
        );
        $employeeId = DB::table('Employee')->where('username', 'admin')->value('id');

        $storeId = 'default-store';
        DB::table('Store')->updateOrInsert(
            ['id' => $storeId],
            [
                'slug' => 'default',
                'name' => '默认店铺',
                'timezone' => 'America/Los_Angeles',
                'status' => 'ACTIVE',
                'createdAt' => now(),
                'updatedAt' => now(),
            ],
        );
        DB::table('StoreMember')->updateOrInsert(
            ['storeId' => $storeId, 'employeeId' => $employeeId],
            ['role' => 'OWNER', 'assignedAt' => now()],
        );

        DB::table('StudentDiscountCampaign')->updateOrInsert(
            ['storeId' => $storeId],
            [
                'id' => 'default-student-discount', 'enabled' => false, 'schoolEmailEnabled' => true,
                'universityEnabled' => true, 'studentIdEnabled' => true, 'educationDomains' => '.edu,.ac.uk,.edu.au',
                'title' => 'Student discount', 'description' => 'Verify your student status and receive a discount code.',
                'schoolEmailLabel' => 'School email', 'schoolNameLabel' => 'School name',
                'consentLabel' => 'I confirm that the information provided is accurate.', 'buttonLabel' => 'Get my discount',
                'successTitle' => 'Your student discount is ready', 'successMessage' => 'Your discount code has been sent.',
                'terms' => 'One code per verified student.', 'discountType' => 'PERCENTAGE', 'discountValue' => 10,
                'currencyCode' => 'USD', 'codePrefix' => 'STUDENT', 'validityDays' => 30,
                'customerTag' => 'student-verified', 'accentColor' => '#111111', 'backgroundColor' => '#ffffff',
                'allowedOrigins' => '*', 'combinesWithProduct' => false, 'combinesWithOrder' => false,
                'combinesWithShipping' => false, 'createdAt' => now(), 'updatedAt' => now(),
            ],
        );

        $roleId = 'system-super-admin';
        DB::table('Role')->updateOrInsert(
            ['id' => $roleId],
            [
                'key' => 'super-admin',
                'name' => '超级管理员',
                'description' => '拥有全部页面和系统管理权限',
                'isSystem' => true,
                'createdAt' => now(),
                'updatedAt' => now(),
            ],
        );
        DB::table('EmployeeRole')->updateOrInsert(
            ['employeeId' => $employeeId, 'roleId' => $roleId],
            ['assignedAt' => now()],
        );

        $pages = collect(config('decomkt.pages'))
            ->map(fn (array $page) => [$page['group'], $page['title'], $page['path']])
            ->prepend(['工作台', '总览仪表盘', '/'])
            ->push(['人员管理', '页面访问权限', '/permissions'])
            ->all();

        foreach ($pages as [$module, $title, $route]) {
            $key = 'page.'.trim(str_replace('/', '.', $route), '.');
            $key = $key === 'page.' ? 'page.dashboard' : $key;
            $permissionId = (string) Str::uuid();
            $existingId = DB::table('Permission')->where('key', $key)->value('id');
            $permissionId = $existingId ?: $permissionId;
            DB::table('Permission')->updateOrInsert(
                ['key' => $key],
                [
                    'id' => $permissionId,
                    'name' => $title,
                    'module' => $module,
                    'description' => $route,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
            );
            DB::table('RolePermission')->updateOrInsert(
                ['roleId' => $roleId, 'permissionId' => $permissionId],
                ['assignedAt' => now()],
            );
        }
    }
}
