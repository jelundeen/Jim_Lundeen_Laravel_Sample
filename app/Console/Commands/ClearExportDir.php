<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearExportDir extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:export-dir';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Empty the temp directory';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->cleanDirectory('export');
        return 0;
    }
    private function cleanDirectory($path, $recursive = true)
    {
	$EXPORT_FILES_TTL = env('EXPORT_FILES_TTL',600) / 60;
        collect(Storage::disk('public')->listContents($path, $recursive))
            ->each(function($file) use ($EXPORT_FILES_TTL) {
                if ( $file['timestamp'] < now()->subMinutes($EXPORT_FILES_TTL)->getTimestamp()) {
                    Storage::disk('public')->delete($file['path']);
                    Storage::disk('public')->deleteDirectory($file['path']);
                }
            });
    }
}
