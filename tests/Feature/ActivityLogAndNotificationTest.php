<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_activity_logs(): void
    {
        $response = $this->get('/log-aktivitas');
        $response->assertRedirect('/login');
    }

    public function test_pjlp_is_forbidden_from_viewing_activity_logs(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();
        $response = $this->actingAs($pjlp)->get('/log-aktivitas');
        $response->assertStatus(403);
    }

    public function test_superadmin_and_kasi_can_view_activity_logs_with_filters(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        // Seed some test logs
        ActivityLog::record([
            'user_id' => $superadmin->id,
            'user_name' => $superadmin->nama_lengkap,
            'user_role' => $superadmin->role,
            'user_seksi' => $superadmin->seksi,
            'action' => 'LOGIN',
            'module' => 'Autentikasi',
            'description' => 'Test log login',
        ]);

        $response = $this->actingAs($superadmin)->get('/log-aktivitas?module=Autentikasi&action=LOGIN');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ActivityLog/Index')
            ->has('logs.data')
            ->has('stats')
            ->where('filters.module', 'Autentikasi')
            ->where('filters.action', 'LOGIN')
        );
    }

    public function test_user_creation_records_activity_log_and_notification(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->post('/users', [
            'nama_lengkap' => 'Audit Tester',
            'username' => 'audittester',
            'email' => 'audittester@example.com',
            'password' => 'password123',
            'role' => 'PJLP',
            'seksi' => 'Seksi ASTIK',
            'status_user' => 'Aktif',
            'jam_masuk' => '07:30',
            'jam_pulang' => '16:00',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'TAMBAH_USER',
            'module' => 'Manajemen User',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'title' => 'Akun Baru Didaftarkan',
            'role' => 'Superadmin',
        ]);
    }

    public function test_user_notifications_unread_polling_and_mark_as_read(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();

        // Create direct notification for PJLP
        $notif = AppNotification::send([
            'title' => 'Pengujian Notifikasi Realtime',
            'message' => 'Halo PJLP, ini notifikasi tes.',
            'type' => 'info',
            'module' => 'Aktivitas',
            'target_user_id' => $pjlp->id,
            'link' => '/aktivitas',
        ]);

        // Poll unread count endpoint
        $pollResponse = $this->actingAs($pjlp)->getJson('/notifications/unread');
        $pollResponse->assertStatus(200);
        $pollResponse->assertJson([
            'unread_count' => 1,
        ]);
        $this->assertCount(1, $pollResponse->json('latest'));

        // Mark single notification as read
        $readResponse = $this->actingAs($pjlp)->postJson("/notifications/{$notif->id}/read");
        $readResponse->assertStatus(200);
        $readResponse->assertJson(['success' => true, 'unread_count' => 0]);

        $this->assertDatabaseHas('app_notifications', [
            'id' => $notif->id,
            'is_read' => true,
        ]);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();

        AppNotification::send([
            'title' => 'Notif 1',
            'message' => 'Pesan 1',
            'type' => 'info',
            'target_user_id' => $pjlp->id,
        ]);

        AppNotification::send([
            'title' => 'Notif 2',
            'message' => 'Pesan 2',
            'type' => 'success',
            'target_user_id' => $pjlp->id,
        ]);

        $response = $this->actingAs($pjlp)->postJson('/notifications/mark-all-read');
        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'unread_count' => 0]);

        $this->assertEquals(0, AppNotification::forUser($pjlp)->unread()->count());
    }

    public function test_user_cannot_read_another_users_private_notification(): void
    {
        $pjlp1 = User::where('username', 'ikhwan')->first();
        $pjlp2 = User::where('role', 'PJLP')->where('id', '!=', $pjlp1->id)->first();
        if (!$pjlp2) {
            $pjlp2 = User::create([
                'name' => 'PJLP Dua',
                'nama_lengkap' => 'PJLP Dua',
                'username' => 'pjlpdua',
                'email' => 'pjlpdua@epjlp.com',
                'password' => 'secret123',
                'role' => 'PJLP',
                'seksi' => 'Seksi ASTIK',
                'status_user' => 'Aktif',
            ]);
        }

        $notif = AppNotification::send([
            'title' => 'Rahasia PJLP 1',
            'message' => 'Pesan privat untuk pjlp1',
            'type' => 'info',
            'target_user_id' => $pjlp1->id,
        ]);

        $response = $this->actingAs($pjlp2)->postJson("/notifications/{$notif->id}/read");
        $response->assertStatus(403);
    }
}
