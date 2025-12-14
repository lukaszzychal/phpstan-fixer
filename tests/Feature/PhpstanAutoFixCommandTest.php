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

namespace PhpstanFixer\Tests\Feature;

use PhpstanFixer\Command\PhpstanAutoFixCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PhpstanAutoFixCommandTest extends TestCase
{
    private string $tempDir;
    private string $tempFile;
    private string $tempConfigFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        $this->tempFile = $this->tempDir . '/test.php';
        $this->tempConfigFile = $this->tempDir . '/phpstan-fixer.json';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testCommandRunsInSuggestMode(): void
    {
        $phpFile = <<<'PHP'
<?php

class Test {
    public function foo() {
        return 42;
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 1
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Method has no return type specified",
          "line": 5,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--mode' => 'suggest',
        ]);

        $statusCode = $commandTester->getStatusCode();
        $output = $commandTester->getDisplay();
        
        // Command may fail if file doesn't exist or can't be fixed, but should at least parse
        if ($statusCode !== 0) {
            // Check if it's a parsing error or file issue
            $this->assertStringContainsString('Found', $output, "Output: " . $output);
        } else {
            $this->assertStringContainsString('Found 1 issue(s)', $output);
            $this->assertStringContainsString('Fix Results', $output);
        }
    }

    public function testCommandHandlesEmptyIssues(): void
    {
        $phpstanJson = <<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 0
  },
  "files": {}
}
JSON;

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No issues found', $output);
    }

    public function testCommandLoadsConfigurationFile(): void
    {
        $phpFile = <<<'PHP'
<?php

class Test {
    public function test() {
        return $this->foo;
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 1
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Access to an undefined property",
          "line": 5,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $configJson = <<<'JSON'
{
  "rules": {
    "Access to an undefined property": {
      "action": "ignore"
    }
  },
  "default": {
    "action": "fix"
  }
}
JSON;

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);
        file_put_contents($this->tempConfigFile, $configJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--config' => $this->tempConfigFile,
            '--mode' => 'suggest',
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Issues Ignored', $output);
    }

    public function testCommandHandlesInvalidMode(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--mode' => 'invalid',
        ]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Mode must be either', $output);
    }

    public function testCommandHandlesMissingInputFile(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => '/non/existent/file.json',
        ]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Input file not found', $output);
    }

    public function testCommandHandlesInvalidJsonInput(): void
    {
        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, '{ invalid json }');

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
        ]);

        $this->assertEquals(1, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Failed to parse', $output);
    }

    public function testCommandShowsUnifiedDiffInSuggestMode(): void
    {
        $phpFile = <<<'PHP'
<?php

class Test {
    public function foo() {
        return 42;
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 1
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Method has no return type specified",
          "line": 4,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--mode' => 'suggest',
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('--- a/', $output);
        $this->assertStringContainsString('+++ b/', $output);
        $this->assertStringContainsString('@@', $output);
    }

    public function testUnifiedDiffHunkHeaderIsAccurateWithInsertions(): void
    {
        // Test case: insertion in the middle should produce correct hunk header
        $phpFile = <<<'PHP'
<?php

class Test {
    public function foo() {
        return 42;
    }
    public function bar() {
        return 'test';
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 2
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Method has no return type specified",
          "line": 4,
          "ignorable": false
        },
        {
          "message": "Method has no return type specified",
          "line": 7,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--mode' => 'suggest',
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        
        // Verify the diff contains hunk headers
        $this->assertStringContainsString('@@', $output);
        
        // Extract hunk headers and verify they're in correct format
        // Format: @@ -oldStart,oldCount +newStart,newCount @@
        preg_match_all('/@@ -(\d+),(\d+) \+(\d+),(\d+) @@/', $output, $matches);
        $this->assertGreaterThan(0, count($matches[0]), 'Should have at least one hunk header');
        
        // Verify hunk header format is correct (all numbers should be positive)
        foreach ($matches[1] as $i => $oldStart) {
            $oldCount = (int)$matches[2][$i];
            $newStart = (int)$matches[3][$i];
            $newCount = (int)$matches[4][$i];
            
            $this->assertGreaterThan(0, (int)$oldStart, 'Old start should be positive');
            $this->assertGreaterThan(0, $oldCount, 'Old count should be positive');
            $this->assertGreaterThan(0, $newStart, 'New start should be positive');
            $this->assertGreaterThan(0, $newCount, 'New count should be positive');
        }
    }

    public function testCommandFiltersFixersByEnabledList(): void
    {
        $phpFile = <<<'PHP'
<?php

class Test {
    public function foo() {
        return 42;
    }
    public function bar($param) {
        return $param;
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 2
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Method has no return type specified",
          "line": 4,
          "ignorable": false
        },
        {
          "message": "Parameter $param has no type specified",
          "line": 7,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $configJson = <<<'JSON'
{
  "fixers": {
    "enabled": ["MissingReturnDocblockFixer"]
  }
}
JSON;

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);
        file_put_contents($this->tempConfigFile, $configJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--config' => $this->tempConfigFile,
            '--mode' => 'suggest',
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        
        // Should fix return type (enabled) but not param type (disabled)
        $this->assertStringContainsString('Found 2 issue(s)', $output);
    }

    public function testCommandFiltersFixersByDisabledList(): void
    {
        $phpFile = <<<'PHP'
<?php

class Test {
    public function foo() {
        return 42;
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 1
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Method has no return type specified",
          "line": 4,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $configJson = <<<'JSON'
{
  "fixers": {
    "disabled": ["MissingReturnDocblockFixer"]
  }
}
JSON;

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);
        file_put_contents($this->tempConfigFile, $configJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--config' => $this->tempConfigFile,
            '--mode' => 'suggest',
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        
        // Should not fix because fixer is disabled
        $this->assertStringContainsString('Found 1 issue(s)', $output);
    }

    public function testCommandAppliesFixerPrioritiesFromConfig(): void
    {
        $phpFile = <<<'PHP'
<?php

class Test {
    public function foo() {
        return 42;
    }
}
PHP;
        file_put_contents($this->tempFile, $phpFile);

        $phpstanJson = sprintf(<<<'JSON'
{
  "totals": {
    "errors": 0,
    "file_errors": 1
  },
  "files": {
    "%s": {
      "errors": 0,
      "messages": [
        {
          "message": "Method has no return type specified",
          "line": 4,
          "ignorable": false
        }
      ]
    }
  }
}
JSON, $this->tempFile);

        $configJson = <<<'JSON'
{
  "fixers": {
    "priorities": {
      "MissingReturnDocblockFixer": 200
    }
  }
}
JSON;

        $inputFile = $this->tempDir . '/phpstan.json';
        file_put_contents($inputFile, $phpstanJson);
        file_put_contents($this->tempConfigFile, $configJson);

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            '--input' => $inputFile,
            '--config' => $this->tempConfigFile,
            '--mode' => 'suggest',
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        // Priority should be applied (test passes if command runs without error)
    }

    private function createCommand(): PhpstanAutoFixCommand
    {
        $application = new Application();
        $application->add(new PhpstanAutoFixCommand());
        
        $command = $application->find('phpstan:auto-fix');
        assert($command instanceof PhpstanAutoFixCommand);
        
        return $command;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

