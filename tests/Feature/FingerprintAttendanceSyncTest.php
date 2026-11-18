<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Fingerprint\FingerprintSoapService;
use Database\Seeders\DatabaseSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FingerprintAttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fingerprint_configuration_is_loaded_correctly(): void
    {
        $config = config('services.fingerprint');

        $this->assertIsArray($config);
        $this->assertEquals('10.130.56.7', $config['ip']);
        $this->assertEquals(80, $config['port']);
        $this->assertEquals(101, $config['key']);
    }

    public function test_fingerprint_soap_service_initialization(): void
    {
        $service = app(FingerprintSoapService::class);

        $this->assertEquals('10.130.56.7', $service->getIp());
        $this->assertEquals(80, $service->getPort());
        $this->assertEquals(101, $service->getKey());
    }

    public function test_test_connection_rejects_forbidden_ip(): void
    {
        $service = app(FingerprintSoapService::class);
        $result = $service->testConnection('169.254.169.254');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak valid atau dilarang', $result['message']);
    }

    public function test_attendance_service_sync_handles_offline_machine_gracefully(): void
    {
        $mockSoap = Mockery::mock(FingerprintSoapService::class);
        $mockSoap->shouldReceive('fetchAttendanceLogs')
            ->once()
            ->andThrow(new Exception('Connection timed out'));

        $this->app->instance(FingerprintSoapService::class, $mockSoap);

        $service = app(AttendanceService::class);
        $result = $service->syncFromMachine('10.130.56.7');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Gagal terhubung ke mesin fingerprint', $result['message']);
    }

    public function test_artisan_command_attendance_sync_machine_can_be_executed(): void
    {
        $mockSoap = Mockery::mock(FingerprintSoapService::class);
        $mockSoap->shouldReceive('getIp')->andReturn('10.130.56.7');
        $mockSoap->shouldReceive('getPort')->andReturn(80);
        $mockSoap->shouldReceive('getKey')->andReturn(101);
        $mockSoap->shouldReceive('fetchAttendanceLogs')
            ->once()
            ->andReturn([]);

        $this->app->instance(FingerprintSoapService::class, $mockSoap);

        $this->artisan('attendance:sync-machine')
            ->assertExitCode(0);
    }

    public function test_export_daily_attendance_excel_returns_valid_download(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $response = $this->actingAs($superadmin)->get('/absensi/export/excel?tanggal=2026-11-02');

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    public function test_export_daily_attendance_pdf_returns_valid_download(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $response = $this->actingAs($superadmin)->get('/absensi/export/pdf?tanggal=2026-11-02');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_export_monthly_recap_excel_returns_valid_download(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $response = $this->actingAs($superadmin)->get('/absensi/rekap/export/excel?periode=2026-11');

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    public function test_export_monthly_recap_pdf_returns_valid_download(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $response = $this->actingAs($superadmin)->get('/absensi/rekap/export/pdf?periode=2026-11');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_pjlp_users_seeded_and_machine_sync_processes_all_registered_pins(): void
    {
        // Pastikan seluruh 17 PJLP dari seeder terdaftar
        $this->assertGreaterThanOrEqual(17, User::pjlp()->count());

        $mockSoap = Mockery::mock(FingerprintSoapService::class);
        $mockSoap->shouldReceive('fetchAttendanceLogs')
            ->once()
            ->andReturn([
                ['pin' => '80790520', 'datetime' => '2026-10-20 07:25:00', 'status' => '0'],
                ['pin' => '80790520', 'datetime' => '2026-10-20 16:30:00', 'status' => '1'],
                ['pin' => '80003177', 'datetime' => '2026-10-20 07:15:00', 'status' => '0'],
                ['pin' => '80003177', 'datetime' => '2026-10-20 16:45:00', 'status' => '1'],
                ['pin' => '99999999', 'datetime' => '2026-10-20 08:00:00', 'status' => '0'], // Unregistered PIN
            ]);

        $this->app->instance(FingerprintSoapService::class, $mockSoap);

        $service = app(AttendanceService::class);
        $result = $service->syncFromMachine('10.130.56.7');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['count']); // Only registered PINs (80790520 & 80003177)
    }

    public function test_machine_sync_with_month_and_year_filters_correctly(): void
    {
        $mockSoap = Mockery::mock(FingerprintSoapService::class);
        $mockSoap->shouldReceive('fetchAttendanceLogs')
            ->once()
            ->andReturn([
                ['pin' => '80790520', 'datetime' => '2026-10-15 07:25:00', 'status' => '0'],
                ['pin' => '80790520', 'datetime' => '2026-10-15 16:30:00', 'status' => '1'],
                ['pin' => '80790520', 'datetime' => '2026-09-15 07:25:00', 'status' => '0'], // Bulan September (should be excluded)
                ['pin' => '80790520', 'datetime' => '2025-10-15 07:25:00', 'status' => '0'], // Tahun 2025 (should be excluded)
            ]);

        $this->app->instance(FingerprintSoapService::class, $mockSoap);

        $service = app(AttendanceService::class);
        $result = $service->syncFromMachine('10.130.56.7', '10', '2026');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('Oktober 2026', $result['message']);
    }

    public function test_controller_sync_endpoint_accepts_month_and_year(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();

        $mockSoap = Mockery::mock(FingerprintSoapService::class);
        $mockSoap->shouldReceive('fetchAttendanceLogs')
            ->once()
            ->andReturn([]);

        $this->app->instance(FingerprintSoapService::class, $mockSoap);

        $response = $this->actingAs($superadmin)->post(route('attendance.sync'), [
            'ip' => '10.130.56.7',
            'bulan' => '10',
            'tahun' => '2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
