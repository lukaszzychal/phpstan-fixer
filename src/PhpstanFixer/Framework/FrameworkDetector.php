<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Framework;

/**
 * Detects which PHP framework is being used (Laravel, Symfony, etc.).
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class FrameworkDetector
{
    /**
     * Detect framework in the given directory.
     *
     * @param string $projectRoot Path to project root directory
     * @return string|null Framework name ('laravel', 'symfony', 'codeigniter', 'cakephp', 'yii', 'laminas', 'phalcon') or null if not detected
     */
    public function detect(string $projectRoot): ?string
    {
        if (!is_dir($projectRoot)) {
            return null;
        }

        // Check composer.json first (most reliable)
        $composerPath = rtrim($projectRoot, '/') . '/composer.json';
        if (file_exists($composerPath)) {
            $framework = $this->detectFromComposer($composerPath);
            if ($framework !== null) {
                return $framework;
            }
        }

        // Fall back to directory structure
        return $this->detectFromDirectoryStructure($projectRoot);
    }

    /**
     * Detect framework from composer.json.
     *
     * @param string $composerPath Path to composer.json
     * @return string|null Framework name or null if not detected
     */
    private function detectFromComposer(string $composerPath): ?string
    {
        if (!file_exists($composerPath) || !is_readable($composerPath)) {
            return null;
        }

        $content = file_get_contents($composerPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['require']) || !is_array($data['require'])) {
            return null;
        }

        $require = $data['require'];

        // Check for Laravel (has precedence)
        if (isset($require['laravel/framework'])) {
            return 'laravel';
        }

        // Check for Symfony
        if (isset($require['symfony/symfony'])) {
            return 'symfony';
        }

        // Check for Symfony components (common patterns)
        $symfonyPackages = array_filter(
            array_keys($require),
            fn(string $package) => str_starts_with($package, 'symfony/')
        );

        // If multiple Symfony components, likely Symfony project
        // Lower threshold to 2 for better detection (common Symfony apps have at least 2-3 components)
        if (count($symfonyPackages) >= 2) {
            return 'symfony';
        }

        // Check for CodeIgniter
        if (isset($require['codeigniter4/framework'])) {
            return 'codeigniter';
        }

        // Check for CakePHP
        if (isset($require['cakephp/cakephp'])) {
            return 'cakephp';
        }

        // Check for Yii
        if (isset($require['yiisoft/yii']) || isset($require['yiisoft/yii2'])) {
            return 'yii';
        }

        // Check for Laminas (formerly Zend Framework)
        if (isset($require['laminas/laminas-mvc']) || isset($require['laminas/laminas-mvc-skeleton'])) {
            return 'laminas';
        }

        // Check for Phalcon
        if (isset($require['phalcon/cphalcon'])) {
            return 'phalcon';
        }

        return null;
    }

    /**
     * Detect framework from directory structure.
     *
     * @param string $projectRoot Path to project root directory
     * @return string|null Framework name or null if not detected
     */
    private function detectFromDirectoryStructure(string $projectRoot): ?string
    {
        $projectRoot = rtrim($projectRoot, '/');

        // Laravel indicators
        $laravelIndicators = [
            $projectRoot . '/artisan',
            $projectRoot . '/app',
            $projectRoot . '/config',
            $projectRoot . '/routes',
        ];

        $laravelScore = 0;
        foreach ($laravelIndicators as $indicator) {
            if (file_exists($indicator)) {
                $laravelScore++;
            }
        }

        // Need at least 3 indicators for Laravel
        if ($laravelScore >= 3) {
            return 'laravel';
        }

        // Symfony indicators
        $symfonyIndicators = [
            $projectRoot . '/symfony.lock',
            $projectRoot . '/src',
            $projectRoot . '/config',
            $projectRoot . '/public',
        ];

        $symfonyScore = 0;
        foreach ($symfonyIndicators as $indicator) {
            if (file_exists($indicator)) {
                $symfonyScore++;
            }
        }

        // Need at least 3 indicators for Symfony
        if ($symfonyScore >= 3) {
            return 'symfony';
        }

        return null;
    }
}

