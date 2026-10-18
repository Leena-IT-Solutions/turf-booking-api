<?php

namespace App\Console\Commands;

use App\Models\Turf;
use App\Services\GstStateResolver;
use Illuminate\Console\Command;

class ListTurfsMissingGstState extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gst:turfs-missing-state';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List every turf where the GST state is missing or unknown.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $turfs = Turf::with(['location.user', 'turfSetting'])->get();

        $missing = [];

        foreach ($turfs as $turf) {
            if (GstStateResolver::turfStateCode($turf) === null) {
                $owner = $turf->location?->user;
                $ownerInfo = $owner
                    ? trim("{$owner->name} ({$owner->email})")
                    : 'N/A';

                $missing[] = [
                    'id' => $turf->id,
                    'name' => $turf->name,
                    'owner' => $ownerInfo,
                    'status' => $turf->status ?: 'Draft',
                ];
            }
        }

        if (empty($missing)) {
            $this->info('All turfs have a valid GST state set.');
            return self::SUCCESS;
        }

        $this->table(['Turf ID', 'Turf Name', 'Owner (Name / Email)', 'Status'], $missing);
        $this->warn(sprintf('Found %d turf(s) missing GST state.', count($missing)));

        return self::SUCCESS;
    }
}
