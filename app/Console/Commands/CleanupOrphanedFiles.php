<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ItemAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class CleanupOrphanedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:cleanup-orphaned {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned item attachment files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting orphaned files cleanup...');
        
        // Get all attachment records
        $attachments = ItemAttachment::all();
        $attachmentPaths = $attachments->pluck('file_path')->toArray();
        
        // Get all files in the item-attachments directory
        $storagePath = storage_path('app/public/item-attachments');
        $allFiles = [];
        
        if (File::exists($storagePath)) {
            $allFiles = File::allFiles($storagePath);
        }
        
        $orphanedFiles = [];
        $orphanedCount = 0;
        $totalSize = 0;
        
        foreach ($allFiles as $file) {
            $relativePath = 'item-attachments/' . $file->getFilename();
            
            if (!in_array($relativePath, $attachmentPaths)) {
                $orphanedFiles[] = $file;
                $orphanedCount++;
                $totalSize += $file->getSize();
            }
        }
        
        if ($orphanedCount === 0) {
            $this->info('No orphaned files found.');
            return;
        }
        
        $this->warn("Found {$orphanedCount} orphaned files (Total size: " . $this->formatBytes($totalSize) . ")");
        
        if ($dryRun) {
            $this->info('DRY RUN - Files that would be deleted:');
            foreach ($orphanedFiles as $file) {
                $this->line("  - {$file->getPathname()} (" . $this->formatBytes($file->getSize()) . ")");
            }
            return;
        }
        
        if ($this->confirm('Do you want to delete these orphaned files?')) {
            $deletedCount = 0;
            $deletedSize = 0;
            
            foreach ($orphanedFiles as $file) {
                try {
                    File::delete($file->getPathname());
                    $deletedCount++;
                    $deletedSize += $file->getSize();
                    $this->line("Deleted: {$file->getPathname()}");
                } catch (\Exception $e) {
                    $this->error("Failed to delete {$file->getPathname()}: {$e->getMessage()}");
                }
            }
            
            $this->info("Cleanup completed!");
            $this->info("Deleted {$deletedCount} files (Total size: " . $this->formatBytes($deletedSize) . ")");
        }
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
