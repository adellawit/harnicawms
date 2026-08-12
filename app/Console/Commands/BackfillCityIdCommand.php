<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Partner\Agent;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class BackfillCityIdCommand extends Command
{
    protected $signature = 'ongkir:backfill-city';

    protected $description = 'Match city string to public.cities.id for agents and warehouses missing city_id';

    public function handle(): int
    {
        $matched = 0;
        $failed = [];

        foreach ([Agent::class => 'Agent', Warehouse::class => 'Warehouse'] as $modelClass => $label) {
            $modelClass::query()
                ->whereNull('city_id')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use (&$matched, &$failed, $label) {
                    foreach ($rows as $row) {
                        $cityName = mb_strtolower(trim((string) $row->city));
                        $match = City::query()
                            ->whereRaw('LOWER(name) = ?', [$cityName])
                            ->first();

                        if ($match) {
                            $row->update(['city_id' => $match->id]);
                            $matched++;
                        } else {
                            $failed[] = "{$label} {$row->id}: {$row->city}";
                        }
                    }
                });
        }

        $this->info("Matched: {$matched}");

        if ($failed !== []) {
            $this->warn('Failed ('.count($failed).'):');
            foreach ($failed as $line) {
                $this->line('  - '.$line);
            }
        }

        return self::SUCCESS;
    }
}
