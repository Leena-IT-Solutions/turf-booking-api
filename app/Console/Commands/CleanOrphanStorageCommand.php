<?php

namespace App\Console\Commands;

use App\Models\SaasSetting;
use App\Models\SliderImage;
use App\Models\TurfPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanOrphanStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:clean-orphans {--dry-run : Only inspect and list unreferenced files without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan public storage disk and safely delete all orphaned files not referenced in the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = (bool) $this->option('dry-run');
        $publicDisk = Storage::disk('public');

        $this->info($isDryRun 
            ? '🔍 Inspecting storage for orphaned files (--dry-run enabled)...' 
            : '🧹 Scanning storage and cleaning orphaned files...');

        // 1. Gather all referenced files from DB tables
        $activeReferences = collect();

        // Turf Photos
        if (Schema::hasTable('turf_photos')) {
            $turfPhotos = TurfPhoto::query()->pluck('photo')->filter()->map(fn ($p) => $this->normalizePath($p));
            $activeReferences = $activeReferences->merge($turfPhotos);
        }

        // Slider Images
        if (Schema::hasTable('slider_images')) {
            $sliderImages = SliderImage::query()->pluck('image_path')->filter()->map(fn ($p) => $this->normalizePath($p));
            $activeReferences = $activeReferences->merge($sliderImages);
        }

        // SaaS Settings Logos
        if (Schema::hasTable('saas_settings')) {
            $logos = SaasSetting::query()->pluck('logo_path')->filter()->map(fn ($p) => $this->normalizePath($p));
            $activeReferences = $activeReferences->merge($logos);
        }

        $activeReferences = $activeReferences->unique()->values();
        $this->line("Found <fg=cyan>{$activeReferences->count()}</> active file references in the database.");

        // 2. Scan public storage disk
        $allDiskFiles = collect($publicDisk->allFiles());
        
        // Exclude system/hidden files like .gitignore or .DS_Store
        $scannableFiles = $allDiskFiles->reject(function ($file) {
            $basename = basename($file);
            return str_starts_with($basename, '.') || $basename === '.gitignore';
        })->values();

        $this->line("Found <fg=cyan>{$scannableFiles->count()}</> total files on the public storage disk.");

        // 3. Identify orphans
        $orphanedFiles = $scannableFiles->filter(function ($diskFile) use ($activeReferences) {
            $normalizedDiskFile = $this->normalizePath($diskFile);
            return !$activeReferences->contains($normalizedDiskFile);
        })->values();

        if ($orphanedFiles->isEmpty()) {
            $this->info('✅ No orphaned files found! Storage is completely in sync with the database.');
            return 0;
        }

        $totalBytes = 0;
        $orphanTableRows = [];

        foreach ($orphanedFiles as $orphan) {
            $size = $publicDisk->exists($orphan) ? $publicDisk->size($orphan) : 0;
            $totalBytes += $size;
            $orphanTableRows[] = [
                $orphan,
                $this->formatBytes($size),
                $isDryRun ? '<fg=yellow>Would Delete</>' : '<fg=red>Deleting</>',
            ];
        }

        $this->table(['Orphaned File Path', 'Size', 'Action'], $orphanTableRows);

        $formattedTotalSize = $this->formatBytes($totalBytes);

        if ($isDryRun) {
            $this->warn("⚠️ Dry run complete: {$orphanedFiles->count()} orphaned files found ({$formattedTotalSize}). No files were removed.");
            $this->line('Run without <fg=yellow>--dry-run</> to delete these files.');
            return 0;
        }

        // Delete orphaned files
        $deletedCount = 0;
        foreach ($orphanedFiles as $orphan) {
            if ($publicDisk->delete($orphan)) {
                $deletedCount++;
            }
        }

        // Clean empty directories (optional pruning)
        $allDirs = collect($publicDisk->allDirectories())->reverse();
        foreach ($allDirs as $dir) {
            if (empty($publicDisk->allFiles($dir))) {
                $publicDisk->deleteDirectory($dir);
            }
        }

        $this->info("🎉 Successfully deleted {$deletedCount} orphaned files. Freed {$formattedTotalSize} of disk space.");
        return 0;
    }

    /**
     * Normalize path to relative format without leading slashes or storage prefixes.
     */
    protected function normalizePath(string $path): string
    {
        $clean = ltrim($path, '/\\');
        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, 8);
        }
        return ltrim($clean, '/\\');
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), $precision) . ' ' . ($units[$power] ?? 'B');
    }
}
