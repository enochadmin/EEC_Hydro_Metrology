<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores supporting files on the private `local` disk (storage/app/private)
 * and keeps the polymorphic document row in sync.
 */
class DocumentService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function store(Model $documentable, UploadedFile $file, ?string $title, User $actor): Document
    {
        return DB::transaction(function () use ($documentable, $file, $title, $actor): Document {
            $directory = 'documents/'.Str::slug(class_basename($documentable)).'/'.$documentable->getKey();
            $extension = Str::lower($file->getClientOriginalExtension() ?: 'bin');
            $filename = (string) Str::uuid().'.'.$extension;

            $path = $file->storeAs($directory, $filename, 'local');

            /** @var Document $document */
            $document = $documentable->documents()->create([
                'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'uploaded_by' => $actor->getKey(),
            ]);

            $this->logger->log(
                'document.uploaded',
                $documentable,
                'Document "'.$document->title.'" attached.',
                ['document_id' => $document->id, 'original_name' => $document->original_name, 'size_bytes' => $document->size_bytes],
                $actor,
            );

            return $document;
        });
    }

    public function delete(Document $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $subject = $document->documentable;

            if ($document->file_path !== '' && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            $document->delete();

            if ($subject !== null) {
                $this->logger->log(
                    'document.deleted',
                    $subject,
                    'Document "'.$document->title.'" removed.',
                    ['document_id' => $document->id, 'original_name' => $document->original_name],
                    $actor,
                );
            }
        });
    }
}
