<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tools\CaptainHook;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IO;
use CaptainHook\App\Console\IOUtil;
use CaptainHook\App\Exception\ActionFailed;
use CaptainHook\App\Git\Range\Detector\PrePush;
use CaptainHook\App\Hook\Action;
use CaptainHook\App\Hook\Constrained;
use CaptainHook\App\Hook\Restriction;
use CaptainHook\App\Hook\Util;
use CaptainHook\App\Hooks;
use CaptainHook\Secrets\Detector;
use CaptainHook\Secrets\Entropy\Shannon;
use CaptainHook\Secrets\Regex\Supplier\Ini;
use CaptainHook\Secrets\Regex\Supplier\Json;
use CaptainHook\Secrets\Regex\Supplier\PHP;
use CaptainHook\Secrets\Regex\Supplier\Yaml;
use CaptainHook\Secrets\Regexer;
use Exception;
use SebastianFeldmann\Git\Diff\File;
use SebastianFeldmann\Git\Repository;

/**
 * Variant of CaptainHook's built-in BlockSecrets action that takes an
 * `excludedPaths` option (array of regex patterns matched against
 * `$file->getName()`) and skips any matching file. Used for paths the
 * project knows are safe but that trip the entropy detector — typically
 * minified browser bundles, package-manager lockfiles, and similar
 * generated artifacts.
 *
 * Implementation note: this is a structural copy of
 * CaptainHook\App\Hook\Diff\Action\BlockSecrets with one extra
 * `isPathExcluded()` short-circuit at the top of the per-file loop in
 * `execute()`. Subclassing wasn't viable because the original class keeps
 * all its setup state and helpers private. Keep the file/method shape in
 * sync with upstream when captainhook is upgraded.
 */
class BlockSecretsExcludingPaths implements Action, Constrained
{
    private IO $io;

    private Detector $detector;

    /** @var array<string> */
    private array $allowed;

    /** @var array<string> */
    private array $excludedPaths;

    /** @var array<string, string> */
    private array $info = [];

    private float $entropyThreshold;

    /** @var array<string, class-string> */
    private array $fileTypeSupplier = [
        'json' => Json::class,
        'php'  => PHP::class,
        'yml'  => Yaml::class,
        'ini'  => Ini::class,
    ];

    public static function getRestriction(): Restriction
    {
        return new Restriction('pre-commit', 'pre-push');
    }

    public function execute(Config $config, IO $io, Repository $repository, Config\Action $action): void
    {
        $this->io = $io;
        $this->setUp($action->getOptions());

        $filesFailed  = 0;
        $filesToCheck = $this->getChanges($repository);

        foreach ($filesToCheck as $file) {
            if ($this->isPathExcluded($file->getName())) {
                $io->write('  ' . IOUtil::PREFIX_OK . ' ' . $file->getName() . ' (excluded by path)', true, IO::VERBOSE);
                continue;
            }
            if ($this->isSecretInFile($file->getName(), $this->getLines($file))) {
                $filesFailed++;
                $io->write('  ' . IOUtil::PREFIX_FAIL . ' ' . $file->getName() . $this->errorDetails($file->getName()));
                continue;
            }
            $io->write('  ' . IOUtil::PREFIX_OK . ' ' . $file->getName(), true, IO::VERBOSE);
        }
        if ($filesFailed > 0) {
            $s = $filesFailed > 1 ? 's' : '';
            throw new ActionFailed('Found secrets in ' . $filesFailed . ' file' . $s);
        }
    }

    private function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $regex) {
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string> $lines
     */
    private function isSecretInFile(string $file, array $lines): bool
    {
        $result = $this->detector->detectIn(implode(PHP_EOL, $lines));
        if ($result->wasSecretDetected()) {
            foreach ($result->matches() as $match) {
                if (!$this->isAllowed($match)) {
                    $this->info[$file] = $match;
                    return true;
                }
            }
        }
        if ($this->containsSuspiciousText($file, $lines)) {
            return true;
        }
        return false;
    }

    /**
     * @param array<string> $lines
     */
    private function containsSuspiciousText(string $file, array $lines): bool
    {
        if ($this->entropyThreshold < 0.1) {
            return false;
        }
        $ext = $this->getFileExtension($file);
        if (!isset($this->fileTypeSupplier[$ext])) {
            return $this->lookForSecretsBruteForce($file, $lines);
        }
        return $this->lookForSecretsWithSupplier($this->fileTypeSupplier[$ext], $lines, $file);
    }

    /**
     * @return array<string>
     */
    private function getLines(File $file): array
    {
        $lines = [];
        foreach ($file->getChanges() as $change) {
            array_push($lines, ...$change->getAddedContent());
        }
        return $lines;
    }

    private function isAllowed(string $blocked): bool
    {
        foreach ($this->allowed as $regex) {
            if (preg_match($regex, $blocked) === 1) {
                return true;
            }
        }
        return false;
    }

    private function setUp(Config\Options $options): void
    {
        $this->detector = Detector::create();

        $this->setUpSuppliers($options);
        $this->setUpBlocked($options);
        $this->entropyThreshold = (float) $options->get('entropyThreshold', 0.0);
        $this->allowed          = $options->get('allowed', []);
        $this->excludedPaths    = $options->get('excludedPaths', []);
    }

    private function setUpSuppliers(Config\Options $options): void
    {
        try {
            $this->detector->useSupplierConfig($options->get('suppliers', []));
        } catch (Exception $e) {
            throw new ActionFailed($e->getMessage(), 0, $e);
        }
    }

    private function setUpBlocked(Config\Options $options): void
    {
        $this->detector->useRegex(...$options->get('blocked', []));
    }

    private function errorDetails(string $file): string
    {
        return ' found <comment>' . $this->info[$file] . '</comment>';
    }

    /**
     * @return array<\SebastianFeldmann\Git\Diff\File>
     */
    private function getChanges(Repository $repository): array
    {
        if (Util::isRunningHook($this->io, Hooks::PRE_PUSH)) {
            $detector = new PrePush();
            $ranges   = $detector->getRanges($this->io);
            $newHash  = 'HEAD';
            $oldHash  = 'HEAD@{1}';
            if (!empty($ranges) && !$ranges[0]->to()->isZeroRev()) {
                $oldHash = $ranges[0]->from()->id();
                $newHash = $ranges[0]->to()->id();
            }
            return $repository->getDiffOperator()->compare($oldHash, $newHash);
        }
        return $repository->getDiffOperator()->compareIndexTo('HEAD');
    }

    private function getFileExtension(string $file): string
    {
        $fileInfo = pathinfo($file);
        return $fileInfo['extension'] ?? '';
    }

    private function isEntropyTooHigh(string $file, string $match): bool
    {
        $entropy = Shannon::entropy($match);
        $this->io->write('Entropy of ' . $match . ' is ' . $entropy, true, IO::DEBUG);
        if ($entropy > $this->entropyThreshold && !$this->isAllowed($match)) {
            $this->info[$file] = $match;
            return true;
        }
        return false;
    }

    /**
     * @param array<string> $lines
     */
    private function lookForSecretsWithSupplier(string $supplierClass, array $lines, string $file): bool
    {
        /** @var \CaptainHook\Secrets\Regex\Grouped $supplier */
        $supplier = new $supplierClass();
        $regexer  = Regexer::create()->useGroupedSupplier($supplier);
        foreach ($lines as $line) {
            $result = $regexer->detectIn($line);
            if (!$result->wasSecretDetected()) {
                continue;
            }
            if ($this->isEntropyTooHigh($file, $result->matches()[0])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string> $lines
     */
    private function lookForSecretsBruteForce(string $file, array $lines): bool
    {
        $matches = [];
        if (preg_match_all('#\b\S{8,}\b#', implode(' ', $lines), $matches)) {
            foreach ($matches[0] as $word) {
                if ($this->isEntropyTooHigh($file, $word)) {
                    return true;
                }
            }
        }
        return false;
    }
}
