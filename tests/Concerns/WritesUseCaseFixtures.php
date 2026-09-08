<?php

declare(strict_types=1);

namespace Ylsalame\LaravelUseCases\Tests\Concerns;

/**
 * Writes fixture classes to disk under the testbench skeleton.
 *
 * BaseController resolves the Request/UseCase/Resource trio for an endpoint by building
 * a file path from the controller's name and checking file_exists(), so fixtures that are
 * merely declared inside a test file are invisible to it — the dispatch fails with
 * "Request file does not exist" before any assertion runs. They have to be real files
 * under base_path().
 */
trait WritesUseCaseFixtures
{
    /** @var list<string> */
    private array $fixturePaths = [];

    protected function writeFixture(string $relativePath, string $code): void
    {
        $path = base_path($relativePath);

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $code."\n");
        $this->fixturePaths[] = $path;

        require_once $path;
    }

    protected function removeWrittenFixtures(): void
    {
        foreach ($this->fixturePaths as $path) {
            @unlink($path);
        }

        $this->fixturePaths = [];
    }
}
