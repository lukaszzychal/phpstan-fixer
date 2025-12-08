<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Feature;

use PhpstanFixer\AutoFixService;
use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Configuration\Configuration;
use PhpstanFixer\Configuration\Rule;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MissingPropertyDocblockFixer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Configuration system.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ConfigurationIntegrationTest extends TestCase
{
    public function testAutoFixServiceIgnoresIssueWhenConfigured(): void
    {
        // Arrange
        $rules = [
            'Access to an undefined property' => new Rule(Rule::ACTION_IGNORE),
        ];
        $config = new Configuration($rules);
        
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();
        $strategy = new MissingPropertyDocblockFixer($analyzer, $docblockManipulator);
        
        $service = new AutoFixService([$strategy], $config);
        
        $issue = new Issue(
            'test.php',
            10,
            'Access to an undefined property'
        );
        
        $fileContent = <<<'PHP'
<?php

class Model
{
    public function test()
    {
        return $this->name;
    }
}
PHP;

        // Act
        $result = $service->fixIssue($issue, $fileContent);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->isIgnored(), 'Issue should be ignored per configuration');
        $this->assertFalse($result->isSuccessful());
    }

    public function testAutoFixServiceReportsIssueWhenConfigured(): void
    {
        // Arrange
        $rules = [
            'Access to an undefined property' => new Rule(Rule::ACTION_REPORT),
        ];
        $config = new Configuration($rules);
        
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();
        $strategy = new MissingPropertyDocblockFixer($analyzer, $docblockManipulator);
        
        $service = new AutoFixService([$strategy], $config);
        
        $issue = new Issue(
            'test.php',
            10,
            'Access to an undefined property'
        );
        
        $fileContent = <<<'PHP'
<?php

class Model
{
    public function test()
    {
        return $this->name;
    }
}
PHP;

        // Act
        $result = $service->fixIssue($issue, $fileContent);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->isReported(), 'Issue should be reported per configuration');
        $this->assertFalse($result->isSuccessful());
    }

    public function testAutoFixServiceFixesIssueWhenConfiguredToFix(): void
    {
        // Arrange
        $tempFile = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

namespace App;

class Model
{
    public function test()
    {
        return $this->name;
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $rules = [
                'Access to an undefined property*' => new Rule(Rule::ACTION_FIX),
            ];
            $config = new Configuration($rules);
            
            $analyzer = new PhpFileAnalyzer();
            $docblockManipulator = new DocblockManipulator();
            $strategy = new MissingPropertyDocblockFixer($analyzer, $docblockManipulator);
            
            $service = new AutoFixService([$strategy], $config);
            
            $issue = new Issue(
                $tempFile,
                10,
                'Access to an undefined property App\\Model::$name.'
            );

            // Act
            $result = $service->fixIssue($issue, $fileContent);

            // Assert
            $this->assertNotNull($result);
            $this->assertTrue($result->isSuccessful(), 'Issue should be fixed per configuration');
            $this->assertFalse($result->isIgnored());
            $this->assertFalse($result->isReported());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testAutoFixServiceUsesDefaultRuleWhenNoMatch(): void
    {
        // Arrange
        $default = new Rule(Rule::ACTION_IGNORE);
        $config = new Configuration([], $default);
        
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();
        $strategy = new MissingPropertyDocblockFixer($analyzer, $docblockManipulator);
        
        $service = new AutoFixService([$strategy], $config);
        
        $issue = new Issue(
            'test.php',
            10,
            'Some other error message'
        );
        
        $fileContent = <<<'PHP'
<?php

class Model
{
    public function test()
    {
        return $this->name;
    }
}
PHP;

        // Act
        $result = $service->fixIssue($issue, $fileContent);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->isIgnored());
    }

    public function testAutoFixServiceWorksWithoutConfiguration(): void
    {
        // Arrange
        $tempFile = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

namespace App;

class Model
{
    public function test()
    {
        return $this->name;
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            // No configuration
            $analyzer = new PhpFileAnalyzer();
            $docblockManipulator = new DocblockManipulator();
            $strategy = new MissingPropertyDocblockFixer($analyzer, $docblockManipulator);
            
            $service = new AutoFixService([$strategy], null);
            
            $issue = new Issue(
                $tempFile,
                10,
                'Access to an undefined property App\\Model::$name.'
            );

            // Act
            $result = $service->fixIssue($issue, $fileContent);

            // Assert - should work normally (fix by default)
            $this->assertNotNull($result);
            $this->assertTrue($result->isSuccessful(), 'Issue should be fixed when no configuration is provided');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testAutoFixServiceWithWildcardPattern(): void
    {
        // Arrange
        $rules = [
            'Access to an undefined *' => new Rule(Rule::ACTION_IGNORE),
        ];
        $config = new Configuration($rules);
        
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();
        $strategy = new MissingPropertyDocblockFixer($analyzer, $docblockManipulator);
        
        $service = new AutoFixService([$strategy], $config);
        
        $issue = new Issue(
            'test.php',
            10,
            'Access to an undefined property'
        );
        
        $fileContent = <<<'PHP'
<?php

class Model
{
    public function test()
    {
        return $this->name;
    }
}
PHP;

        // Act
        $result = $service->fixIssue($issue, $fileContent);

        // Assert
        $this->assertNotNull($result);
        $this->assertTrue($result->isIgnored(), 'Issue should be ignored by wildcard pattern');
    }
}

