<?php

namespace App\Concerns;

use App\Models\Estimate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait MaterializesEstimatePdf
{
    /**
     * Resolve an absolute local filesystem path to the stored PDF. Shell
     * commands (pdftoppm/pdfinfo) and AI SDK attachments require a real
     * on-disk file — when the configured disk is remote (S3/MinIO), we
     * stream the object to a temp file rather than buffering it into a
     * PHP string so 25MB uploads don't OOM the queue worker.
     *
     * @return array{0: string, 1: \Closure}
     */
    private function materializePdf(Estimate $estimate): array
    {
        $diskName = config('estimates.disk');
        $disk = Storage::disk($diskName);
        $driver = config("filesystems.disks.{$diskName}.driver");

        Log::info('estimate.pdf.materialize', ['disk' => $diskName, 'driver' => $driver, 'path' => $estimate->pdf_path]);

        if ($driver === 'local') {
            return [$disk->path($estimate->pdf_path), fn () => null];
        }

        $source = $disk->readStream($estimate->pdf_path);
        if (! is_resource($source)) {
            throw new RuntimeException("Unable to open read stream for {$estimate->pdf_path}");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'estimate-pdf-');
        $dest = @fopen($tempPath, 'wb');
        if ($dest === false) {
            fclose($source);
            @unlink($tempPath);
            throw new RuntimeException("Unable to open temp file for write: {$tempPath}");
        }

        try {
            stream_copy_to_stream($source, $dest);
        } finally {
            fclose($source);
            fclose($dest);
        }

        return [$tempPath, fn () => @unlink($tempPath)];
    }
}
