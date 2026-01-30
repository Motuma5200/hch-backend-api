<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HealthMetric;
use Carbon\Carbon;

class SyncLocalHealthMetrics extends Command
{
    protected $signature = 'health:sync-local {--file=storage/app/private/health_metrics.json}';

    protected $description = 'Sync local health metrics fallback file into the database when available.';

    public function handle()
    {
        $file = base_path($this->option('file'));

        if (!file_exists($file)) {
            $this->info('No local fallback file found.');
            return 0;
        }

        $json = file_get_contents($file);
        $items = json_decode($json, true) ?: [];

        if (empty($items)) {
            $this->info('No records to sync.');
            return 0;
        }

        $inserted = 0;
        foreach ($items as $item) {
            try {
                HealthMetric::create([
                    'user_id' => $item['user_id'] ?? 0,
                    'metric_type' => $item['metric_type'] ?? null,
                    'value' => isset($item['value']) ? $item['value'] : null,
                    'unit' => $item['unit'] ?? null,
                    'additional_data' => $item['additional_data'] ?? null,
                    'recorded_at' => isset($item['recorded_at']) ? Carbon::parse($item['recorded_at']) : Carbon::now()
                ]);

                $inserted++;
            } catch (\Exception $e) {
                $this->error('Failed inserting record: ' . $e->getMessage());
            }
        }

        if ($inserted > 0) {
            // clear file
            file_put_contents($file, json_encode([]));
            $this->info("Inserted {$inserted} records and cleared local fallback file.");
        } else {
            $this->info('No records were inserted.');
        }

        return 0;
    }
}
