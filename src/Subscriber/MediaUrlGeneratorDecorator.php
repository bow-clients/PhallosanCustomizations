<?php declare(strict_types=1);

namespace PhallosanCustomizations\Subscriber;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;

class MediaUrlGeneratorDecorator extends AbstractMediaUrlGenerator
{
    public function __construct(
        private readonly FilesystemOperator $filesystem
    ) {
    }

    public function generate(array $paths): array
    {
        $urls = [];
        foreach ($paths as $key => $value) {
            if (str_starts_with($value->path, 'http')) {
                $url = $value->path;
            } else {
                $url = $this->filesystem->publicUrl($value->path);
            }

            $urls[$key] = $url;
        }

        return $urls;
    }
}
