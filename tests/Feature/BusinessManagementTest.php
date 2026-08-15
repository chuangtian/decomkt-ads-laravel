<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        DB::table('Employee')->insert([
            'username' => 'admin', 'passwordHash' => bcrypt('password'), 'name' => '系统管理员',
            'email' => $user->email, 'emailVerified' => true, 'status' => 'ACTIVE', 'isDefaultAdmin' => true,
            'createdAt' => now(), 'updatedAt' => now(),
        ]);
        DB::table('Store')->insert(['id' => 'default-store', 'slug' => 'default', 'name' => '默认店铺', 'timezone' => 'America/Los_Angeles', 'status' => 'ACTIVE', 'createdAt' => now(), 'updatedAt' => now()]);

        return $user;
    }

    public function test_admin_can_create_employee_role_store_and_task(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/roles', ['name' => '营销', 'key' => 'marketing', 'description' => '营销团队', 'permissionIds' => []])->assertSessionHasNoErrors();
        $roleId = DB::table('Role')->where('key', 'marketing')->value('id');
        $this->actingAs($admin)->post('/employees', ['name' => '测试员工', 'email' => 'staff@example.com', 'password' => 'password123', 'jobTitle' => '投手', 'roleId' => $roleId])->assertSessionHasNoErrors();
        $employeeId = DB::table('Employee')->where('email', 'staff@example.com')->value('id');
        $this->assertDatabaseHas('users', ['email' => 'staff@example.com']);
        $this->assertDatabaseHas('EmployeeRole', ['employeeId' => $employeeId, 'roleId' => $roleId]);

        $this->actingAs($admin)->post('/stores', ['name' => '美国二店', 'slug' => 'us-store-2', 'timezone' => 'America/Los_Angeles'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('Store', ['slug' => 'us-store-2', 'status' => 'ACTIVE']);

        $this->actingAs($admin)->post('/tasks', ['title' => '检查广告账户', 'description' => '核对昨日消耗', 'priority' => 'HIGH', 'assigneeIds' => [$employeeId]])->assertSessionHasNoErrors();
        $taskId = DB::table('Task')->where('title', '检查广告账户')->value('id');
        $this->assertDatabaseHas('TaskAssignee', ['taskId' => $taskId, 'employeeId' => $employeeId]);
        $this->assertDatabaseHas('Notification', ['taskId' => $taskId, 'recipientId' => $employeeId, 'type' => 'TASK_ASSIGNED']);

        $storeId = DB::table('Store')->where('slug', 'us-store-2')->value('id');
        $this->actingAs($admin)->put("/stores/{$storeId}/members", ['employeeIds' => [$employeeId]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('StoreMember', ['storeId' => $storeId, 'employeeId' => $employeeId, 'role' => 'MEMBER']);

        $this->actingAs($admin)->put("/employees/{$employeeId}", [
            'name' => '测试员工（已更新）', 'status' => 'ACTIVE', 'roleIds' => [$roleId], 'storeIds' => [$storeId],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('Employee', ['id' => $employeeId, 'name' => '测试员工（已更新）']);
        $this->assertDatabaseHas('StoreMember', ['storeId' => $storeId, 'employeeId' => $employeeId]);
    }

    public function test_configuration_risk_analysis_and_weekly_report_are_persisted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/configuration/system', ['key' => 'OPENAI_API_KEY', 'value' => 'secret-test-key'])->assertSessionHasNoErrors();
        $encrypted = DB::table('SystemConfig')->where('key', 'OPENAI_API_KEY')->value('value');
        $this->assertNotSame('secret-test-key', $encrypted);
        $this->assertStringNotContainsString('secret-test-key', $encrypted);

        $this->actingAs($admin)->post('/reputation/items', [
            'entity' => 'review', 'platform' => 'TRUSTPILOT', 'content' => '交付严重延误', 'star' => 1, 'sentiment' => 'NEGATIVE',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/reputation/analyze')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ReputationRisk', ['platform' => 'TRUSTPILOT', 'source' => 'auto', 'status' => 'OPEN']);

        $this->actingAs($admin)->post('/reputation/weekly-report', [
            'reporter' => '运营负责人', 'reportTo' => '管理层', 'content' => '本周舆情风险已完成复盘。',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ReputationWeeklyReport', [
            'storeId' => 'default-store', 'reporter' => '运营负责人', 'reportTo' => '管理层',
        ]);
    }

    public function test_default_admin_is_presented_with_all_permissions_and_all_active_stores(): void
    {
        $admin = $this->admin();
        DB::table('Store')->insert([
            'id' => 'second-store',
            'slug' => 'second-store',
            'name' => '第二店铺',
            'timezone' => 'America/Los_Angeles',
            'status' => 'ACTIVE',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/employees')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees')
                ->where('employees.0.isDefaultAdmin', true)
                ->where('employees.0.hasGlobalAccess', true)
                ->where('employees.0.roles.0', '超级管理员（全部权限）')
                ->where('employees.0.storeIds', fn ($storeIds) => collect($storeIds)->sort()->values()->all() === ['default-store', 'second-store']),
            );
    }

    public function test_default_admin_cannot_be_downgraded_through_employee_editor(): void
    {
        $admin = $this->admin();
        $employeeId = DB::table('Employee')->where('username', 'admin')->value('id');
        DB::table('Store')->insert([
            'id' => 'second-store',
            'slug' => 'second-store',
            'name' => '第二店铺',
            'timezone' => 'America/Los_Angeles',
            'status' => 'ACTIVE',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
        DB::table('EmployeeRole')->insert([
            'employeeId' => $employeeId,
            'roleId' => 'system-super-admin',
            'assignedAt' => now(),
        ]);
        DB::table('StoreMember')->insert([
            'storeId' => 'second-store',
            'employeeId' => $employeeId,
            'role' => 'OWNER',
            'assignedAt' => now(),
        ]);

        $this->actingAs($admin)->put("/employees/{$employeeId}", [
            'name' => '系统管理员',
            'status' => 'ACTIVE',
            'roleIds' => [],
            'storeIds' => ['default-store'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('EmployeeRole', [
            'employeeId' => $employeeId,
            'roleId' => 'system-super-admin',
        ]);
        $this->assertDatabaseHas('StoreMember', [
            'storeId' => 'second-store',
            'employeeId' => $employeeId,
        ]);
    }

    public function test_student_discount_page_displays_environment_mail_defaults(): void
    {
        config()->set('mail.mailers.smtp.host', 'smtp.env.example');
        config()->set('mail.mailers.smtp.port', 465);
        config()->set('mail.mailers.smtp.scheme', 'smtps');
        config()->set('mail.mailers.smtp.username', 'mailer@example.com');
        config()->set('mail.mailers.smtp.password', 'environment-password');
        config()->set('mail.from.address', 'mailer@example.com');
        config()->set('mail.from.name', 'Macfox');

        $this->actingAs($this->admin())
            ->get('/default/plugins/macfox-student-discount')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Plugins/StudentDiscount')
                ->where('smtp.source', 'system')
                ->where('smtp.configured', true)
                ->where('smtp.host', 'smtp.env.example')
                ->where('smtp.password', 'environment-password')
                ->where('smtp.fromAddress', 'mailer@example.com'),
            );
    }

    public function test_admin_can_save_and_remove_store_specific_email_branding(): void
    {
        config()->set('filesystems.disks.public.url', 'https://admin.example/storage');
        Storage::fake('public');
        $logo = UploadedFile::fake()->createWithContent(
            'logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );

        $this->actingAs($this->admin())
            ->post('/student-discounts/email-branding', [
                'brandName' => 'Macfox',
                'logo' => $logo,
                'shopUrl' => 'https://macfox.example/',
                'supportUrl' => 'mailto:support@macfox.example',
                'instagramUrl' => 'https://instagram.com/macfox',
                'facebookUrl' => 'https://facebook.com/macfox',
                'tiktokUrl' => 'https://tiktok.com/@macfox',
                'youtubeUrl' => 'https://youtube.com/@macfox',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '当前店铺的邮件品牌与链接已保存');

        $logoPath = Crypt::decryptString(DB::table('StoreConfig')->where([
            'storeId' => 'default-store',
            'key' => 'STUDENT_DISCOUNT_LOGO_PATH',
        ])->value('value'));
        $logoUrl = Crypt::decryptString(DB::table('StoreConfig')->where([
            'storeId' => 'default-store',
            'key' => 'STUDENT_DISCOUNT_LOGO_URL',
        ])->value('value'));

        Storage::disk('public')->assertExists($logoPath);
        $this->assertContains($logoUrl, [
            "/storage/{$logoPath}",
            "https://admin.example/storage/{$logoPath}",
        ]);
        $this->assertLessThanOrEqual(1, substr_count($logoUrl, 'https://'));
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())
            ->get('/default/plugins/macfox-student-discount')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.brandName', 'Macfox')
                ->where('branding.logoUrl', $logoUrl)
                ->where('branding.shopUrl', 'https://macfox.example/')
                ->where('branding.supportUrl', 'mailto:support@macfox.example'),
            );

        $this->delete('/student-discounts/email-branding/logo')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '邮件 Logo 已删除，可以重新上传');
        Storage::disk('public')->assertMissing($logoPath);
        $this->assertDatabaseMissing('StoreConfig', [
            'storeId' => 'default-store',
            'key' => 'STUDENT_DISCOUNT_LOGO_URL',
        ]);
    }

    public function test_non_employee_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertForbidden();
    }
}
