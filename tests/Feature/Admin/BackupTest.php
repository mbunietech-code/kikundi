<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Backup\BackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    private function admin(string $role = 'system_admin'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    public function test_backup_zip_holds_the_database_and_member_photos(): void
    {
        Storage::disk('local')->put('member-photos/amina.jpg', 'jpeg-bytes');

        $name = app(BackupService::class)->create();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($name)));
        $sql = $zip->getFromName('database.sql');
        $this->assertSame('jpeg-bytes', $zip->getFromName('member-photos/amina.jpg'));
        $this->assertNotFalse($zip->getFromName('README.txt'));
        $zip->close();

        $this->assertStringContainsString('CREATE TABLE "members"', $sql);
        $this->assertStringContainsString("'Amina Juma'", $sql);
        $this->assertStringContainsString('INSERT INTO "obligations"', $sql);
    }

    public function test_old_backups_are_pruned_but_the_newest_is_kept(): void
    {
        $service = app(BackupService::class);

        $this->travelTo(now()->subDays(30));
        $old = $service->create();
        touch(Storage::disk('local')->path($old), now()->timestamp);
        $this->travelBack();

        $this->assertSame(0, $service->prune(), 'The only backup is never removed.');

        $service->create();
        $this->assertSame(1, $service->prune());
        $this->assertCount(1, $service->list());
    }

    public function test_only_the_system_admin_can_list_create_and_download(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/backups')->assertRedirect();
        $file = app(BackupService::class)->list()->first()['name'];

        $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee($file);
        $this->actingAs($admin)->get("/admin/backups/{$file}")->assertOk()->assertDownload($file);

        $this->actingAs($admin)->get('/admin/backups/..%2F.env')->assertNotFound();
        $this->actingAs($this->admin('chair'))->get('/admin/backups')->assertForbidden();
        $this->actingAs($this->admin('treasurer'))->get("/admin/backups/{$file}")->assertForbidden();
    }
}
