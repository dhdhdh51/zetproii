<?php
/**
 * Secure file upload handling (spec #35/#36):
 *  - validates extension against an allow-list (never a deny-list-only check)
 *  - validates real MIME type via finfo (not just the client-supplied one)
 *  - enforces max upload size
 *  - stores with a randomized filename outside the guessable path
 *  - stores business documents/knowledge uploads OUTSIDE /public (in
 *    /storage), served only via a controlled, ownership-checked endpoint
 */
final class FileUploadService
{
    /**
     * @param array $file a single entry from $_FILES
     * @param string[] $allowedExtensions
     * @return array{stored_path:string, extension:string, size:int, original_name:string}
     */
    public static function handleUpload(array $file, array $allowedExtensions, int $maxSizeMb, string $destinationSubdir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('File upload failed. Please try again.');
        }

        $originalName = (string) ($file['name'] ?? 'upload');
        $tmpPath = (string) $file['tmp_name'];
        $sizeBytes = (int) ($file['size'] ?? 0);

        if ($sizeBytes <= 0 || $sizeBytes > $maxSizeMb * 1024 * 1024) {
            throw new InvalidArgumentException("File exceeds the maximum allowed size of {$maxSizeMb}MB.");
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (Security::isExtensionBlocked($originalName)) {
            throw new InvalidArgumentException('This file type is not allowed for security reasons.');
        }
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('Unsupported file type. Allowed: ' . implode(', ', $allowedExtensions));
        }

        // Verify the actual file content, not just the extension/client MIME.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($tmpPath) ?: 'application/octet-stream';
        $allowedMimeMap = [
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
            'gif' => ['image/gif'], 'webp' => ['image/webp'], 'svg' => ['image/svg+xml', 'text/plain'],
        ];
        if (isset($allowedMimeMap[$extension]) && !in_array($realMime, $allowedMimeMap[$extension], true)) {
            throw new InvalidArgumentException('The file content does not match its extension.');
        }

        $storageRoot = dirname(__DIR__, 2) . '/storage/uploads/' . trim($destinationSubdir, '/');
        if (!is_dir($storageRoot)) {
            @mkdir($storageRoot, 0755, true);
        }

        $safeName = Security::safeFilename($originalName);
        $destPath = $storageRoot . '/' . $safeName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        @chmod($destPath, 0644);

        return [
            'stored_path' => $destPath,
            'extension' => $extension,
            'size' => $sizeBytes,
            'original_name' => Security::cleanString($originalName),
        ];
    }
}
