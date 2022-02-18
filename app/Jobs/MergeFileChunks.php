<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\File as FileFacade;

class MergeFileChunks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var File */
    private $file;

    /**
     * Create a new job instance.
     *
     * @param File $file
     */
    public function __construct(File $file)
    {
        $file->joinPending();
        $this->file = $file;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Check if file upload is fully completed
        if ($this->file->chunks_received != $this->file->chunks_total) {
            $this->file->uploadFailed();
            return;
        }

        $chunks = collect([]);

        for ($chunk = 0; $chunk < $this->file->chunks_total; $chunk++) {
            $chunkPath = Storage::disk('local')->path($this->file->chunk_path . $chunk);
            $chunks->push([
                'index' => $chunk,
                'path' => $chunkPath,
                'exists' => $exists = file_exists($chunkPath),
                'fileSize' => filesize($chunkPath),
            ]);

            // If atleast one chunk is missing, fail upload
            if (!$exists) {
                $this->file->uploadFailed();
                return;
            }
        }

        if ($chunks->sum('fileSize') !== $this->file->file_size) {
            $this->file->uploadFailed();
            return;
        }

        $this->file->local_path = $this->file->file_name;
        $this->file->save();

        $destinationPath = Storage::disk('public')->path($this->file->local_path);

        $fh = fopen($destinationPath, 'a');

        // Join the chunks to a single file
        $chunks->each(function ($chunk) use (&$fh) {
            $fhChunk = fopen($chunk['path'], 'r');
            stream_copy_to_stream($fhChunk, $fh);
            fclose($fhChunk);
        });
        fclose($fh);
        $this->file->cleanChunkStorage();

        if (FileFacade::isFile($destinationPath)) {
            $this->file->file_size = FileFacade::size($destinationPath);
            $this->file->mime = FileFacade::mimeType($destinationPath);
            $this->file->save();
        }

        $this->file->status = File::STATUS_UPLOADED;
        $this->file->save();
    }
}
