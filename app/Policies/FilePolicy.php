<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FilePolicy
{
    use HandlesAuthorization;

    /**
     * File create rule
     *
     * @param  User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        $userUploads = $user->files()->byStatus(File::STATUS_UPLOADED)
            ->whereNotNull('local_path')
            ->createdBetween(now()->subDay(), now())
            ->count();

        return $userUploads < config('filesystems.user_upload_limit_24h');
    }
}
