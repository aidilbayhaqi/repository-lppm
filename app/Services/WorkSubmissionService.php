<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class WorkSubmissionService
{
    public function __construct(private WorkService $works, private DocumentService $documents) {}

    public function save(string $type, array $data, User $actor, ?int $id = null): Model
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($type, $data, $actor, $id, &$stored) {
                $work = $this->works->save($type, $data, $actor, $id)->refresh();
                foreach ($data['attachments'] ?? [] as $i => $attachment) {
                    if (empty($attachment['file'])) {
                        continue;
                    }
                    try {
                        $document = $this->documents->store($work, $actor, $attachment['file'], [
                            'jenis_dokumen' => $attachment['jenis_dokumen'], 'publik' => $attachment['publik'] ?? false,
                            'lock_version' => $work->lock_version,
                        ]);
                    } catch (ValidationException $error) {
                        throw ValidationException::withMessages(["attachments.$i.file" => $error->validator->errors()->all()]);
                    }
                    $stored[] = [$document->storage_disk, $document->path_file];
                    $work->refresh();
                }

                return $work;
            });
        } catch (\Throwable $error) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $error;
        }
    }
}
