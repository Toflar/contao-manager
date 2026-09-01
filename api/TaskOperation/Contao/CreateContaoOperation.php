<?php

declare(strict_types=1);

/*
 * This file is part of Contao Manager.
 *
 * (c) Contao Association
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerApi\TaskOperation\Contao;

use Contao\ManagerApi\ApiKernel;
use Contao\ManagerApi\Composer\Environment;
use Contao\ManagerApi\Task\TaskConfig;
use Contao\ManagerApi\TaskOperation\AbstractInlineOperation;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_INSTALL')]
class CreateContaoOperation extends AbstractInlineOperation
{
    private const SUPPORTED_VERSIONS = ['4.13.*', '5.3.*', '5.7.*', '6.0.*'];

    private readonly string|null $version;

    private string|null $publicDir;

    public function __construct(
        TaskConfig $taskConfig,
        private readonly Environment $environment,
        ApiKernel $kernel,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct($taskConfig);

        $this->version = $taskConfig->getOption('version');
        $this->publicDir = $taskConfig->getState('public-dir');

        if (!\in_array($this->version, self::SUPPORTED_VERSIONS, true)) {
            throw new \InvalidArgumentException('Unsupported Contao version');
        }

        if (null !== $this->publicDir) {
            return;
        }

        // We must use the kernel at runtime here because the parameter is not dynamic
        if ($kernel->getProjectDir() === $kernel->getPublicDir()) {
            throw new \RuntimeException('Cannot install without a public directory.');
        }

        $taskConfig->setState('public-dir', $this->publicDir = $kernel->getPublicDir());
    }

    public function getSummary(): string
    {
        return 'composer create-project contao/managed-edition:'.$this->version.' --no-install';
    }

    protected function getName(): string
    {
        return 'create-project';
    }

    protected function doRun(): bool
    {
        $protected = [
            $this->environment->getJsonFile(),
            $this->environment->getLockFile(),
            $this->environment->getVendorDir(),
        ];

        if ($this->filesystem->exists($protected)) {
            throw new \RuntimeException('Cannot install into existing application');
        }

        $this->filesystem->dumpFile(
            $this->environment->getJsonFile(),
            $this->generateComposerJson(
                $this->taskConfig->getOption('version'),
                (bool) $this->taskConfig->getOption('core-only', false),
            ),
        );

        return true;
    }

    private function generateComposerJson(string $version, bool $coreOnly = false): string
    {
        $data = [
            'type' => 'project',
            'require' => [
                'contao/conflicts' => '@dev',
                'contao/manager-bundle' => $version,
            ],
            'extra' => [
                'public-dir' => basename((string) $this->publicDir),
                'contao-component-dir' => 'assets',
            ],
            'scripts' => [
                'post-install-cmd' => [
                    '@php vendor/bin/contao-setup',
                ],
                'post-update-cmd' => [
                    '@php vendor/bin/contao-setup',
                ],
            ],
        ];

        if ($this->isUnstable($version)) {
            $data['require']['contao/core-bundle'] = $version;
        }

        if (!$coreOnly) {
            $data['require']['contao/calendar-bundle'] = $version;
            $data['require']['contao/comments-bundle'] = $version;
            $data['require']['contao/faq-bundle'] = $version;
            $data['require']['contao/listing-bundle'] = $version;
            $data['require']['contao/news-bundle'] = $version;
            $data['require']['contao/newsletter-bundle'] = $version;
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function isUnstable(string $version): bool
    {
        return false;
    }
}
