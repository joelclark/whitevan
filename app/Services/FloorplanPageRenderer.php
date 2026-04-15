<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\Exceptions\ProcessStartFailedException;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Thin wrapper around poppler-utils (`pdftoppm`, `pdfinfo`).
 *
 * Lives behind an interface-like class so the extraction job can swap a fake
 * implementation in tests via $this->instance(FloorplanPageRenderer::class, …).
 * No state — safe to share across requests.
 */
class FloorplanPageRenderer
{
    /**
     * Total page count of the PDF, parsed from `pdfinfo`.
     */
    public function pageCount(string $pdfAbsolutePath): int
    {
        try {
            $result = Process::run(['pdfinfo', $pdfAbsolutePath]);
        } catch (ProcessStartFailedException $e) {
            throw new RuntimeException(
                'poppler-utils (pdfinfo) is not installed or not on PATH.',
                previous: $e,
            );
        }

        if ($result->failed()) {
            throw new RuntimeException(
                'pdfinfo failed: '.trim($result->errorOutput() ?: $result->output()),
            );
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $result->output(), $matches) !== 1) {
            throw new RuntimeException('pdfinfo output did not contain a Pages line.');
        }

        return (int) $matches[1];
    }

    /**
     * Render a single page to PNG. Returns the absolute path of the written file.
     *
     * `pdftoppm -singlefile` appends `.png` to the supplied output prefix; we
     * pass the prefix without the extension and return the final path.
     */
    public function render(string $pdfAbsolutePath, int $page, string $outputAbsolutePathNoExt): string
    {
        try {
            $result = Process::run([
                'pdftoppm',
                '-png',
                '-r', '150',
                '-f', (string) $page,
                '-l', (string) $page,
                '-singlefile',
                $pdfAbsolutePath,
                $outputAbsolutePathNoExt,
            ]);
        } catch (ProcessStartFailedException $e) {
            throw new RuntimeException(
                'poppler-utils (pdftoppm) is not installed or not on PATH.',
                previous: $e,
            );
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'pdftoppm failed: '.trim($e->getMessage()),
                previous: $e,
            );
        }

        if ($result->failed()) {
            throw new RuntimeException(
                'pdftoppm failed: '.trim($result->errorOutput() ?: $result->output()),
            );
        }

        $finalPath = $outputAbsolutePathNoExt.'.png';

        if (! is_file($finalPath)) {
            throw new RuntimeException("pdftoppm reported success but {$finalPath} is missing.");
        }

        return $finalPath;
    }
}
