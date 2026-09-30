<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Imagick;
use ImagickException;
use RuntimeException;
use SplFileInfo;

#[Signature('icons:build-webp {--check : Validate the generated WebP catalog without rewriting files}')]
#[Description('Build transparent, lossless 128x128 WebP product icons from the SVG sources')]
final class BuildWebpIcons extends Command
{
    private const int ICON_SIZE = 128;

    public function handle(Filesystem $files): int
    {
        $sourceDirectory = resource_path('icons/svg');
        $outputDirectory = public_path('icons/webp');

        if (! $files->isDirectory($sourceDirectory)) {
            $this->components->error("Icon source directory does not exist: {$sourceDirectory}");

            return self::FAILURE;
        }

        $sourceFiles = collect($files->files($sourceDirectory))
            ->filter(fn (SplFileInfo $file): bool => strtolower($file->getExtension()) === 'svg')
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
            ->values();

        if ($sourceFiles->isEmpty()) {
            $this->components->error('No SVG icon sources were found.');

            return self::FAILURE;
        }

        if (! $this->option('check')) {
            $files->ensureDirectoryExists($outputDirectory);
            $expectedFilenames = $sourceFiles
                ->map(fn (SplFileInfo $file): string => $file->getBasename('.svg').'.webp')
                ->all();

            foreach ($files->files($outputDirectory) as $outputFile) {
                if (strtolower($outputFile->getExtension()) === 'webp'
                    && ! in_array($outputFile->getFilename(), $expectedFilenames, true)) {
                    $files->delete($outputFile->getPathname());
                }
            }

            foreach ($sourceFiles as $sourceFile) {
                try {
                    $this->convert($sourceFile, $outputDirectory, $files);
                } catch (ImagickException|RuntimeException $exception) {
                    $this->components->error("Could not convert {$sourceFile->getFilename()}: {$exception->getMessage()}");

                    return self::FAILURE;
                }
            }
        }

        try {
            $this->validateCatalog($sourceFiles->all(), $outputDirectory, $files);
        } catch (ImagickException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $action = $this->option('check') ? 'Validated' : 'Built and validated';
        $this->components->info("{$action} {$sourceFiles->count()} transparent lossless WebP icons at 128x128.");

        return self::SUCCESS;
    }

    private function convert(SplFileInfo $sourceFile, string $outputDirectory, Filesystem $files): void
    {
        $source = new Imagick;
        $source->setBackgroundColor('transparent');
        $source->readImage($sourceFile->getPathname());
        $source->setIteratorIndex(0);
        $source->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        $source->thumbnailImage(self::ICON_SIZE, self::ICON_SIZE, true);
        $source->setImagePage(0, 0, 0, 0);

        $canvas = new Imagick;
        $canvas->newImage(self::ICON_SIZE, self::ICON_SIZE, 'transparent');
        $canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        $canvas->compositeImage(
            $source,
            Imagick::COMPOSITE_OVER,
            (self::ICON_SIZE - $source->getImageWidth()) / 2,
            (self::ICON_SIZE - $source->getImageHeight()) / 2,
        );
        $canvas->setImageFormat('webp');
        $canvas->setOption('webp:lossless', 'true');
        $canvas->setImageCompressionQuality(100);
        $canvas->stripImage();

        $filename = $sourceFile->getBasename('.svg').'.webp';
        $target = $outputDirectory.DIRECTORY_SEPARATOR.$filename;
        $temporary = $target.'.tmp';

        if (! $canvas->writeImage($temporary)) {
            throw new RuntimeException("Could not write {$filename}.");
        }

        if (! $files->move($temporary, $target)) {
            throw new RuntimeException("Could not move {$filename} into the generated catalog.");
        }
        $source->clear();
        $canvas->clear();
    }

    /**
     * @param  list<SplFileInfo>  $sourceFiles
     */
    private function validateCatalog(array $sourceFiles, string $outputDirectory, Filesystem $files): void
    {
        if (! $files->isDirectory($outputDirectory)) {
            throw new RuntimeException("Generated icon directory does not exist: {$outputDirectory}");
        }

        $outputFiles = collect($files->files($outputDirectory))
            ->filter(fn (SplFileInfo $file): bool => strtolower($file->getExtension()) === 'webp')
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename())
            ->values();

        if ($sourceFiles !== [] && count($sourceFiles) !== $outputFiles->count()) {
            throw new RuntimeException(sprintf(
                'Expected %d generated icons, found %d.',
                count($sourceFiles),
                $outputFiles->count(),
            ));
        }

        foreach ($sourceFiles as $sourceFile) {
            $filename = $sourceFile->getBasename('.svg').'.webp';
            $target = $outputDirectory.DIRECTORY_SEPARATOR.$filename;

            if (! $files->exists($target)) {
                throw new RuntimeException("Missing generated icon: {$filename}");
            }

            $imageSize = getimagesize($target);

            if ($imageSize === false || $imageSize[0] !== self::ICON_SIZE || $imageSize[1] !== self::ICON_SIZE) {
                throw new RuntimeException("Icon {$filename} must be exactly 128x128.");
            }

            if (($imageSize['mime'] ?? null) !== 'image/webp') {
                throw new RuntimeException("Icon {$filename} is not a WebP image.");
            }

            $contents = $files->get($target);

            if (substr($contents, 12, 4) !== 'VP8L') {
                throw new RuntimeException("Icon {$filename} is not encoded as lossless WebP.");
            }

            $image = new Imagick($target);
            $alpha = $image->exportImagePixels(
                0,
                0,
                $image->getImageWidth(),
                $image->getImageHeight(),
                'A',
                Imagick::PIXEL_DOUBLE,
            );
            $image->clear();

            if ($alpha === [] || min($alpha) >= 1.0) {
                throw new RuntimeException("Icon {$filename} does not contain transparent pixels.");
            }
        }
    }
}
