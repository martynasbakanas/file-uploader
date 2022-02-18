<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class File extends Model
{
    use HasFactory;

    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_UPLOAD_FAILED = 'upload-failed';
    public const STATUS_JOIN_PENDING = 'join-pending';
    public const STATUS_JOIN_FAILED = 'join-failed';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_DELETED = 'deleted';
    public const STATUS_AZURE_UPLOAD_PENDING = 'azure-upload-pending';

    /**
     * Get file url
     *
     * @return Attribute
     */
    protected function url(): Attribute
    {
        return new Attribute(
            get: fn () => $this->azure_url ?
                '' :
                Storage::disk('public')->url($this->local_path),
        );
    }

    /**
     * Result limit by status
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', '=', $status);
    }

    /**
     * Result limit to uploaded only
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUploaded(Builder $query): Builder
    {
        return $query->where('status', '=', self::STATUS_UPLOADED);
    }

    /**
     * Time interval limit for files
     * @param Builder $query
     * @param Carbon $from
     * @param Carbon $to
     * @return Builder
     */
    public function scopeCreatedBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Limits results by uuid
     *
     * @param Builder $query
     * @param string $uuid
     * @return Builder
     */
    public function scopeByUuid(Builder $query, $uuid): Builder
    {
        return $query->where('uuid', '=', $uuid);
    }

    /**
     * Changes file status to pending join
     *
     * @return void
     */
    public function joinPending(): void
    {
        $this->status = self::STATUS_JOIN_PENDING;
        $this->save();
    }

    /**
     * File upload failed
     *
     * @return void
     */
    public function uploadFailed(): void
    {
        $this->status = self::STATUS_JOIN_FAILED;
        $this->save();
        $this->cleanChunkStorage();
    }

    /**
     * Deletes all left over chunks for file
     * @return bool
     */
    public function cleanChunkStorage(): bool
    {
        if (is_null($this->chunk_path)) {
            return true;
        }
        try {
            Storage::disk('local')->deleteDirectory($this->chunk_path);
            $this->chunk_path = null;
            $this->save();
            return true;
        } catch (\Exception $e) {
            Log::error('Could not delete chunks directory', [
                'file' => $this->id,
                'path' => $this->chunk_path,
                'exception' => $e->getMessage(),
            ]);
        }
        return false;
    }
}
