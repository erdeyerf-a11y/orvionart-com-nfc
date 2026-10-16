<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InspectAuditLogHygiene extends Command
{
    protected $signature = 'orvionart:inspect-audit-logs {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Denetim kayıtlarında zaman, aktör ve hassas metadata anahtarlarını salt okunur denetler';

    public function handle(): int
    {
        if (!Schema::hasTable('audit_logs')) {
            $this->error('audit_logs tablosu bulunamadı; migration durumu incelenmelidir.');
            return self::FAILURE;
        }

        $checks = [
            'future_created_at' => $this->queryCheck(
                DB::table('audit_logs')->where('created_at', '>', now()->addMinutes(5)),
                'Denetim kaydının oluşturulma zamanı gelecekte görünüyor.'
            ),
            'empty_action' => $this->queryCheck(
                DB::table('audit_logs')->where(function ($query): void {
                    $query->whereNull('action')->orWhereRaw("TRIM(action) = ''");
                }),
                'Denetim kaydında action alanı boş.'
            ),
        ];

        $invalidIpIds = [];
        $invalidIpCount = 0;
        DB::table('audit_logs')->select(['id', 'ip_address'])->whereNotNull('ip_address')->orderBy('id')->chunkById(500, function ($rows) use (&$invalidIpIds, &$invalidIpCount): void {
            foreach ($rows as $row) {
                if (filter_var((string) $row->ip_address, FILTER_VALIDATE_IP) === false) {
                    $invalidIpCount++;
                    if (count($invalidIpIds) < 20) {
                        $invalidIpIds[] = (int) $row->id;
                    }
                }
            }
        });
        $checks['invalid_ip_address'] = [
            'count' => $invalidIpCount,
            'sample_ids' => $invalidIpIds,
            'message' => 'Denetim kaydında geçerli IPv4/IPv6 biçiminde olmayan IP adresi var.',
        ];

        $sensitiveMetadataIds = [];
        $sensitiveMetadataCount = 0;
        DB::table('audit_logs')->select(['id', 'metadata'])->orderBy('id')->chunkById(500, function ($rows) use (&$sensitiveMetadataIds, &$sensitiveMetadataCount): void {
            foreach ($rows as $row) {
                $metadata = $row->metadata;
                if (is_string($metadata)) {
                    $decoded = json_decode($metadata, true);
                    $metadata = is_array($decoded) ? $decoded : [];
                }
                if ($this->containsSensitiveKey($metadata)) {
                    $sensitiveMetadataCount++;
                    if (count($sensitiveMetadataIds) < 20) {
                        $sensitiveMetadataIds[] = (int) $row->id;
                    }
                }
            }
        });
        $checks['sensitive_metadata_key'] = [
            'count' => $sensitiveMetadataCount,
            'sample_ids' => $sensitiveMetadataIds,
            'message' => 'Metadata içinde parola, token, secret, API anahtarı, cookie veya kimlik bilgisi çağrışımlı bir anahtar var.',
        ];

        if (Schema::hasTable('users')) {
            $orphanActors = DB::table('audit_logs')
                ->whereNotNull('actor_id')
                ->whereNotIn('actor_id', DB::table('users')->select('id'))
                ->select('id');
            $checks['orphan_actor'] = $this->queryCheck($orphanActors, 'Denetim kaydı aktör ID değeri artık users tablosunda bulunmuyor.');
        } else {
            $checks['orphan_actor'] = [
                'count' => 0,
                'sample_ids' => [],
                'message' => 'users tablosu bulunamadığı için aktör referans kontrolü yapılamadı.',
                'status' => 'manual',
            ];
        }

        $warnings = [];
        foreach ($checks as $code => $check) {
            if (($check['count'] ?? 0) > 0 || ($check['status'] ?? null) === 'manual') {
                $warnings[] = [
                    'code' => $code,
                    'message' => $check['message'],
                    'count' => $check['count'] ?? 0,
                    'sample_ids' => $check['sample_ids'] ?? [],
                ];
            }
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'checks' => $checks,
            'warnings' => $warnings,
            'limitations' => [
                'Bu komut audit_logs veya users kayıtlarını değiştirmez ve silmez.',
                'Metadata değerleri rapora yazılmaz; yalnızca şüpheli anahtar bulunan kayıt ID değerleri örneklenir.',
                'Anahtar adı eşleşmesi kesin kanıt değildir; yetkili inceleme gerekir.',
                'Bu denetim kriptografik değişmezlik veya eksiksiz denetim kaydı garantisi sağlamaz.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART denetim kaydı hijyeni (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            foreach ($checks as $code => $check) {
                $count = (int) ($check['count'] ?? 0);
                $this->line($code.': '.$count);
                if ($count > 0 || ($check['status'] ?? null) === 'manual') {
                    $this->warn($check['message'].' Örnek kayıt ID: '.implode(', ', $check['sample_ids'] ?? []));
                }
            }
            $this->line('Salt okunur: hiçbir kayıt değiştirilmedi.');
            $this->line('Bu rapor kriptografik audit log bütünlüğü veya son kabul testi değildir.');
        }

        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }

    private function queryCheck($query, string $message): array
    {
        $count = (clone $query)->count();
        $sampleIds = (clone $query)->orderBy('id')->limit(20)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return ['count' => $count, 'sample_ids' => $sampleIds, 'message' => $message];
    }

    private function containsSensitiveKey(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $child) {
            if (is_string($key) && preg_match('/password|passwd|secret|token|authorization|api[_-]?key|cookie|credential/i', $key) === 1) {
                return true;
            }
            if (is_array($child) && $this->containsSensitiveKey($child)) {
                return true;
            }
        }

        return false;
    }
}
