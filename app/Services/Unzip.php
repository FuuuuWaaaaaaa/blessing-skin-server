<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Filesystem\Filesystem;
use ZipArchive;

class Unzip
{
    protected Filesystem $filesystem;

    protected ZipArchive $zipper;

    public function __construct(Filesystem $filesystem, ZipArchive $zipper)
    {
        $this->filesystem = $filesystem;
        $this->zipper = $zipper;
    }

    public function extract(string $file, string $destination): void
    {
        $zip = $this->zipper;
        $resource = $zip->open($file);

        if ($resource !== true) {
            throw new Exception(trans('admin.download.errors.unzip'));
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $parts = is_string($name) ? preg_split('/[\\\\\/]+/', $name) : false;
                if (
                    !is_string($name)
                    || $name === ''
                    || !is_array($parts)
                    || $parts[0] === ''
                    || in_array('..', $parts, true)
                ) {
                    throw new Exception(trans('admin.download.errors.unzip'));
                }

                $system = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $system, $attributes)) {
                    $fileType = ($attributes >> 16) & 0xF000;
                    if ($fileType === 0xA000) {
                        throw new Exception(trans('admin.download.errors.unzip'));
                    }
                }
            }

            if (!$zip->extractTo($destination)) {
                throw new Exception(trans('admin.download.errors.unzip'));
            }
        } finally {
            $zip->close();
        }

        $this->filesystem->delete($file);
    }
}
