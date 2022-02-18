<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;
use App\Jobs\MergeFileChunks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Class FileService
 * @package App\Services\FileService
 */
class FileService
{
    /**
     * Returns chunks count
     *
     * @param integer $fileSize
     * @return float
     */
    public function chunkCount(int $fileSize): float
    {
        return ceil(bcdiv($fileSize, config('filesystems.chunk_size_kb'), 2));
    }

    /**
     * Initial file creation
     *
     * @param Request $request
     * @return File
     */
    public function storeFile(Request $request): File
    {
        $fileFromRequest = $request->file('file');
        $file = new File();
        $file->uuid = $request->get('dzuuid');
        $file->file_name = $fileFromRequest->getClientOriginalName();
        $file->file_size = $request->get('dztotalfilesize');
        $file->extension = $fileFromRequest->getClientOriginalExtension();
        $file->mime = $fileFromRequest->getMimeType();
        $file->chunk_path = uniqid('chunked-uploads-', true) . '/';
        $file->chunks_received = 0;
        $file->chunks_total = $request->get('dztotalchunkcount');
        $file->last_chunk_uploaded_at = now();
        $file->status = File::STATUS_UPLOADING;
        auth()->user()->files()->save($file);
        return $file;
    }

    /**
     * File upload to storage
     *
     * @param Request $request
     * @param File $file
     * @return void
     */
    public function uploadFile(Request $request, File $file): void
    {
        $request->file('file')->storeAs($file->chunk_path, $request->get('dzchunkindex'), 'local');
        chmod(Storage::disk('local')->path($file->chunk_path.'/'.$request->get('dzchunkindex')), 0777);
        chmod(Storage::disk('local')->path($file->chunk_path), 0777);

        $file->increment('chunks_received');
        $file->last_chunk_uploaded_at = now();
        $file->save();
        if ($file->chunks_received == $file->chunks_total) {
            MergeFileChunks::dispatch($file)->onQueue('file-upload');
            $file->refresh();
        }
    }
}
