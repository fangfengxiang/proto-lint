<?php

declare(strict_types=1);

namespace PhpProtoLint\Console;

use PhpProtoLint\Config\BulkConfigLoader;
use PhpProtoLint\Domain\PhpMetadata;
use PhpProtoLint\Linter\LintEngine;
use PhpProtoLint\Locator\ClassLocator;
use PhpProtoLint\Parser\DescriptorReader;
use PhpProtoLint\Parser\PhpContractParser;
use PhpProtoLint\Parser\ProtoParser;
use PhpProtoLint\Support\NamespacePrefixes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * check command: Contract consistency check.
 *
 * Task 7.4: Binds --config, calls LintEngine, outputs lint report.
 *
 * Usage: php-proto-lint check --config="proto-bulk.json"
 */
#[AsCommand(name: 'check', description: 'Validate PHP code contracts against .proto definitions')]
final class CheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to proto-bulk.json configuration file',
                'proto-bulk.json',
            )
            ->addOption(
                'verbose',
                'v',
                InputOption::VALUE_NONE,
                'Show verbose debug output',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        $verbose = (bool) $input->getOption('verbose');

        try {
            $bulkLoader = new BulkConfigLoader();
            $bulkConfig = $bulkLoader->load($configPath);

            $output->writeln('<info>[INFO] Scanning target directory: ' . $bulkConfig->sourceDir . ' ...</info>');

            // Parse proto contract
            $protoParser = new ProtoParser();
            $protoParser->ensureProtocAvailable();

            $protoFiles = [];
            foreach ($bulkConfig->services as $service) {
                foreach ($service->methods as $method) {
                    $files = $method->targetProtoFiles ?? [$bulkConfig->defaultTargetProto];
                    foreach ($files as $file) {
                        $protoFiles[$file] = $file;
                    }
                }
            }
            if (empty($protoFiles)) {
                $protoFiles = [$bulkConfig->defaultTargetProto];
            }

            $binaryData = $protoParser->compile(array_values($protoFiles));
            $descriptorReader = new DescriptorReader();
            $protoMetadata = $descriptorReader->read($binaryData);

            if ($verbose) {
                $output->writeln('<comment>[DEBUG] Proto metadata loaded: ' . count($protoMetadata->services) . ' service(s)</comment>');
            }

            // Parse PHP source with recursive descent
            $classLocator = new ClassLocator($bulkConfig->sourceDir);
            $phpContractParser = new PhpContractParser();

            $allServices = [];
            $allMessages = [];

            foreach (array_keys($bulkConfig->services) as $serviceName) {
                $serviceFile = $this->findServiceFile($serviceName, $bulkConfig->sourceDir, $classLocator);
                if ($serviceFile === null) {
                    $output->writeln('<error>[ERROR] Service class not found: ' . $serviceName . '</error>');

                    continue;
                }

                $phpMetadata = $phpContractParser->parseWithDescent($serviceFile, $classLocator);
                foreach ($phpMetadata->services as $svc) {
                    $allServices[] = $svc;
                }
                foreach ($phpMetadata->messagesByName as $fqcn => $msg) {
                    $allMessages[$fqcn] = $msg;
                }
            }

            $phpMetadata = new PhpMetadata($allServices, $allMessages);

            // Run lint engine
            $lintEngine = new LintEngine($bulkConfig->ruleOverrides);
            $report = $lintEngine->check($protoMetadata, $phpMetadata, $bulkConfig->sourceDir);

            // Output report
            foreach (explode("\n", $report->format()) as $line) {
                if ($line === '') {
                    continue;
                }
                if (str_starts_with($line, '[ERROR]') || str_starts_with($line, '[FATAL]')) {
                    $output->writeln('<error>' . $line . '</error>');
                } elseif (str_starts_with($line, '[WARNING]')) {
                    $output->writeln('<comment>' . $line . '</comment>');
                } elseif (str_starts_with($line, '[OK]')) {
                    $output->writeln('<info>' . $line . '</info>');
                } else {
                    $output->writeln($line);
                }
            }

            return $report->getExitCode();
        } catch (\Throwable $e) {
            $output->writeln('<error>[FATAL] ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    /**
     * Find the PHP service file by scanning the source directory.
     */
    private function findServiceFile(string $serviceName, string $sourceDir, ClassLocator $locator): ?string
    {
        foreach (NamespacePrefixes::SERVICE_PREFIXES as $prefix) {
            $fqcn = $prefix . $serviceName;
            $path = $locator->resolve($fqcn);
            if ($path !== null) {
                return $path;
            }
        }

        // Fallback: search by class name in source dir
        return $this->scanForClass($serviceName, $sourceDir);
    }

    /**
     * Scan source directory for a class by name.
     */
    private function scanForClass(string $className, string $dir): ?string
    {
        if (!is_dir($dir)) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }
            $code = @file_get_contents($fileInfo->getRealPath());
            if ($code === false) {
                continue;
            }
            if (preg_match('/\b(?:class|interface|enum)\s+' . preg_quote($className, '/') . '\b/', $code)) {
                return $fileInfo->getRealPath();
            }
        }

        return null;
    }
}
