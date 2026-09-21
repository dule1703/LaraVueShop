<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Windows je case-insensitive, Ubuntu (CI/server) nije: `@/components/X` radi lokalno
 * a puca na runner-u. file_exists() na Windows-u to ne vidi, pa se putanje proveravaju
 * poređenjem sa stvarnim imenima iz scandir().
 */
class FrontendImportCaseTest extends TestCase
{
    private const EXTENSIONS = ['', '.js', '.vue', '.mjs', '.json', '.css'];

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function test_svi_alias_importi_se_poklapaju_sa_stvarnim_imenima_fajlova(): void
    {
        $errors = [];

        foreach ($this->frontendFiles() as $file) {
            preg_match_all('/(?:from|import)\s*\(?\s*[\'"]@\/([^\'"]+)[\'"]/', file_get_contents($file), $matches);

            foreach ($matches[1] as $path) {
                $problem = $this->checkPath($this->root.'/resources/js', $path, mustExist: true);
                if ($problem !== null) {
                    $errors[] = str_replace($this->root.'/', '', $file)." -> @/{$path}: {$problem}";
                }
            }
        }

        $this->assertSame([], $errors, implode(PHP_EOL, $errors));
    }

    public function test_shadcn_aliasi_u_components_json_se_poklapaju_sa_stvarnim_folderima(): void
    {
        $config = json_decode(file_get_contents($this->root.'/components.json'), true, flags: JSON_THROW_ON_ERROR);
        $errors = [];

        foreach ($config['aliases'] as $name => $alias) {
            $this->assertStringStartsWith('@/', $alias, "components.json alias '{$name}'");

            // Folder ne mora da postoji (npr. ui/ još nije generisan), ali ako roditelj
            // postoji, mora da ima isto pisanje velikih/malih slova.
            $problem = $this->checkPath($this->root.'/resources/js', substr($alias, 2), mustExist: false);
            if ($problem !== null) {
                $errors[] = "components.json aliases.{$name} = {$alias}: {$problem}";
            }
        }

        $this->assertSame([], $errors, implode(PHP_EOL, $errors));
    }

    /** @return list<string> */
    private function frontendFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root.'/resources/js', RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), ['vue', 'js'], true)) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        return $files;
    }

    private function checkPath(string $base, string $path, bool $mustExist): ?string
    {
        $segments = explode('/', $path);
        $dir = $base;

        foreach ($segments as $i => $segment) {
            $entries = scandir($dir);
            $isLast = $i === count($segments) - 1;
            $candidates = $isLast ? array_map(fn ($ext) => $segment.$ext, self::EXTENSIONS) : [$segment];

            if (array_intersect($candidates, $entries) !== []) {
                $dir .= '/'.($isLast ? array_values(array_intersect($candidates, $entries))[0] : $segment);

                continue;
            }

            foreach ($entries as $entry) {
                foreach ($candidates as $candidate) {
                    if (strcasecmp($entry, $candidate) === 0) {
                        return "'{$candidate}' postoji samo kao '{$entry}' (razlika u velikim/malim slovima)";
                    }
                }
            }

            return $mustExist ? "'{$segment}' ne postoji" : null;
        }

        return null;
    }
}
