<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\GridFS\Bucket;
use MongoDB\GridFS\Exception\FileNotFoundException;
use RuntimeException;

class BillingInvoiceStorage
{
    private const GRIDFS_PREFIX = 'gridfs://';

    private ?Bucket $bucket = null;

    public function storeUploadedFile(
        UploadedFile $uploadedFile,
        string $storedName,
        array $metadata = [],
    ): string {
        $path = $uploadedFile->getRealPath();

        if ($path === false) {
            throw new RuntimeException('Unable to read uploaded invoice.');
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open uploaded invoice stream.');
        }

        try {
            $fileId = $this->bucket()->uploadFromStream($storedName, $stream, [
                'metadata' => $metadata,
            ]);
        } finally {
            fclose($stream);
        }

        return $this->toGridFsPath((string) $fileId);
    }

    public function isGridFsPath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::GRIDFS_PREFIX);
    }

    public function openDownloadStream(string $path)
    {
        return $this->bucket()->openDownloadStream(
            new ObjectId($this->fileIdFromPath($path)),
        );
    }

    public function delete(string $path): void
    {
        try {
            $this->bucket()->delete(new ObjectId($this->fileIdFromPath($path)));
        } catch (FileNotFoundException) {
            // Keep archive cleanup idempotent when the GridFS file is already gone.
        }
    }

    private function fileIdFromPath(string $path): string
    {
        if (! $this->isGridFsPath($path)) {
            throw new RuntimeException('The provided invoice path is not a GridFS path.');
        }

        $fileId = substr($path, strlen(self::GRIDFS_PREFIX));

        try {
            new ObjectId($fileId);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('The provided invoice GridFS id is invalid.', 0, $exception);
        }

        return $fileId;
    }

    private function toGridFsPath(string $fileId): string
    {
        return self::GRIDFS_PREFIX.$fileId;
    }

    private function bucket(): Bucket
    {
        if ($this->bucket instanceof Bucket) {
            return $this->bucket;
        }

        $dsn = (string) config('database.connections.mongodb.dsn', '');
        $database = (string) config('database.connections.mongodb.database', '');
        $bucketName = (string) config('esp32.mongodb.billing_invoices_bucket', 'billing_invoices');

        if ($dsn === '' || $database === '') {
            throw new RuntimeException('MongoDB is not configured for invoice storage.');
        }

        $this->bucket = (new Client($dsn))
            ->selectDatabase($database)
            ->selectGridFSBucket([
                'bucketName' => $bucketName,
            ]);

        return $this->bucket;
    }
}
