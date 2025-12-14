<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */


declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MissingUseStatementFixer;
use PHPUnit\Framework\TestCase;

final class MissingUseStatementFixerTest extends TestCase
{
    private MissingUseStatementFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MissingUseStatementFixer(
            new PhpFileAnalyzer()
        );
    }

    public function testCanFixClassNotFound(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Class Foo not found');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testAddsUseForFullyQualifiedClass(): void
    {
        $tempFile = sys_get_temp_dir() . '/missing-use-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

namespace App;

class Demo {
    public function run(): void {
        new Bar();
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                6,
                'Class \\Vendor\\Package\\Bar not found'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('use Vendor\\Package\\Bar;', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testGetName(): void
    {
        $this->assertSame('MissingUseStatementFixer', $this->fixer->getName());
    }

    public function testDiscoversFqnFromVendorDirectory(): void
    {
        $root = sys_get_temp_dir() . '/phpstan-fixer-' . uniqid();
        $srcDir = $root . '/src/App';
        $vendorDir = $root . '/vendor/Acme/Util';

        mkdir($srcDir, 0777, true);
        mkdir($vendorDir, 0777, true);

        file_put_contents($root . '/composer.json', '{}');

        $vendorClass = <<<'PHP'
<?php

namespace Acme\Util;

final class Helper {}
PHP;
        file_put_contents($vendorDir . '/Helper.php', $vendorClass);

        $fileContent = <<<'PHP'
<?php

namespace App;

final class Main {
    public function run(): void {
        new Helper();
    }
}
PHP;
        $filePath = $srcDir . '/Main.php';
        file_put_contents($filePath, $fileContent);

        try {
            $issue = new Issue($filePath, 6, 'Class Helper not found');

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('use Acme\\Util\\Helper;', $result->getFixedContent());
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}

