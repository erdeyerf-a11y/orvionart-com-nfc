<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogHygieneInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_audit_log_is_reported_without_mutating_data(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'actor_id' => null,
            'action' => 'experience.updated',
            'subject_type' => 'experience',
            'subject_id' => '42',
            'ip_address' => '192.0.2.10',
            'user_agent' => 'ORVIONART test',
            'metadata' => json_encode(['changed_fields' => ['title']], JSON_THROW_ON_ERROR),
            'created_at' => now()->subMinute(),
        ]);

        $this->assertSame(0, Artisan::call('orvionart:inspect-audit-logs', ['--json' => true]));
        $this->assertStringContainsString('"read_only": true', Artisan::output());
        $this->assertDatabaseHas('audit_logs', ['id' => $id, 'action' => 'experience.updated']);
    }

    public function test_sensitive_metadata_key_is_reported_without_exposing_its_value(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'actor_id' => null,
            'action' => 'settings.updated',
            'subject_type' => 'setting',
            'subject_id' => '1',
            'ip_address' => '192.0.2.11',
            'user_agent' => 'ORVIONART test',
            'metadata' => json_encode(['integration' => ['api_key' => 'NEVER_PRINT_THIS_SECRET']], JSON_THROW_ON_ERROR),
            'created_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, Artisan::call('orvionart:inspect-audit-logs', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('sensitive_metadata_key', $output);
        $this->assertStringContainsString((string) $id, $output);
        $this->assertStringNotContainsString('NEVER_PRINT_THIS_SECRET', $output);
        $this->assertDatabaseHas('audit_logs', ['id' => $id]);
    }

    public function test_future_timestamp_and_invalid_ip_are_reported_without_mutation(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'actor_id' => null,
            'action' => 'test.event',
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => 'not-an-ip',
            'user_agent' => 'ORVIONART test',
            'metadata' => null,
            'created_at' => now()->addHour(),
        ]);

        $this->assertSame(1, Artisan::call('orvionart:inspect-audit-logs', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('future_created_at', $output);
        $this->assertStringContainsString('invalid_ip_address', $output);
        $this->assertDatabaseHas('audit_logs', ['id' => $id, 'ip_address' => 'not-an-ip']);
    }
}
