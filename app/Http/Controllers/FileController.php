<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class FileController extends Controller
{
    /**
     * Returns user file list
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        File::retrieved(function (File $file) {
            $file->append('url');
            $file->makeHidden('local_path');
        });
        return response()->json(
            auth()->user()
                ->files()
                ->uploaded()
                ->select([
                    'file_name',
                    'status',
                    'local_path'
                ])
                ->get()
        );
    }

    /**
     * Upload file.
     *
     * @param Request $request
     * @param FileService $fileService
     * @return JsonResponse
     */
    public function upload(Request $request, FileService $fileService): JsonResponse
    {
        $this->authorize('create', File::class);

        $this->validate($request, [
            'dzchunkindex' => 'required|integer|min:0',
            'dzuuid'       => 'required|string|alpha_dash|size:36',
        ]);

        if ($request->get('dzchunkindex') == 0) {
            $this->validate($request, [
                'dzuuid'          => 'required|unique:files,uuid',
                'dztotalfilesize' => 'required|integer',
            ]);
            $this->validate($request, [
                'dztotalchunkcount' => 'required|integer|min:1|max:' . $fileService->chunkCount($request->get('dztotalfilesize')),
            ]);
            $file = $fileService->storeFile($request);
        } else {
            $file = File::byUuid($request->get('dzuuid'))->firstOrFail();
            $this->validate($request, [
                'dztotalfilesize'   => 'required|integer|in:' . $file->file_size,
                'dztotalchunkcount' => 'required|integer|in:' . $file->chunks_total,
                'dzchunkindex'      => 'required|integer|in:' . $file->chunks_received,
            ]);
        }

        if ($file->status != File::STATUS_UPLOADING) {
            throw new UnprocessableEntityHttpException('File upload no longer accepted.');
        }

        if ($file->chunks_received >= $file->chunks_total) {
            throw new UnprocessableEntityHttpException('Upload failed.');
        }
        $fileService->uploadFile($request, $file);

        return response()->json([
            'id'              => $file->id,
            'chunks_total'    => $file->chunks_total,
            'chunks_received' => $file->chunks_received,
            'chunks_left'     => $file->chunks_total - $file->chunks_received,
            'progress'        => $file->chunks_received / $file->chunks_total,
        ]);
    }
}
