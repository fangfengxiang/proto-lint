<?php

declare(strict_types=1);

namespace ProtoLint\Console;

use ProtoLint\Config\MappingConfigLoader;
use ProtoLint\Domain\PhpMetadata;
use ProtoLint\Linter\ShadowLintEngine;
use ProtoLint\Locator\ClassLocator;
use ProtoLint\Parser\DescriptorReader;
use ProtoLint\Parser\PhpContractParser;
use ProtoLint\Parser\ProtoParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * shadow-lint command: Shadow traffic payload audit.
 *
 * Task 7.5: Binds --config, calls PayloadParser + DiffEngine, outputs audit report.
 *
 * Usage: proto-lint shadow-lint --config="proto-mapping.json"
 */
#[AsCommand(name: 'shadow-lint', description: 'Audit shadow traffic payloads against .proto contracts and PHP DTOs')]
final class ShadowLintCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to proto-mapping.json configuration file', 'proto-mapping.json')
            ->addOption('source-dir', 's', InputOption::VALUE_REQUIRED, 'Source directory for PHP files')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Show verbose debug output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        $sourceDir = $input->getOption('source-dir');
        $verbose = (bool) $input->getOption('verbose');

        try {
            $mappingLoader = new MappingConfigLoader();
            $mappingConfig = $mappingLoader->load($configPath);

            $output->writeln('<info>[INFO] Shadow-lint: auditing traffic payload against contract</info>');

            $protoParser = new ProtoParser();
            $protoParser->ensureProtocAvailable();

            $binaryData = $protoParser->compile($mappingConfig->targetProtoFiles);
            $descriptorReader = new DescriptorReader();
            $protoMetadata = $descriptorReader->read($binaryData);

            if ($verbose) {
                $output->writeln('<comment>[DEBUG] Proto metadata loaded: ' . count($protoMetadata->messagesByName) . ' message(s)</comment>');
            }

            $srcDir = $sourceDir ?? dirname($configPath) . '/src';
            $classLocator = new ClassLocator($srcDir);
            $phpContractParser = new PhpContractParser();

            $allMessages = [];
            // Collect DTO classes from both request and response mappings
            $allFieldClassMappings = array_merge(
                $mappingConfig->request->fieldClassMappings,
                $mappingConfig->response->fieldClassMappings,
            );
            foreach ($allFieldClassMappings as $jsonKey => $fqcn) {
                if ($fqcn === '' || $fqcn === null) {
                    continue;
                }
                $filePath = $classLocator->resolve($fqcn);
                if ($filePath === null) {
                    continue;
                }
                $phpMetadata = $phpContractParser->parseFile($filePath);
                foreach ($phpMetadata->messagesByName as $msgFqcn => $msg) {
                    $allMessages[$msgFqcn] = $msg;
                }
            }

            $phpMetadata = new PhpMetadata([], $allMessages);

            $shadowEngine = new ShadowLintEngine();

            // Audit request payload
            $requestReport = $shadowEngine->audit(
                $mappingConfig->request->payload,
                $mappingConfig->request->fieldClassMappings,
                $phpMetadata,
                $protoMetadata,
            );

            // Audit response payload
            $responseReport = $shadowEngine->audit(
                $mappingConfig->response->payload,
                $mappingConfig->response->fieldClassMappings,
                $phpMetadata,
                $protoMetadata,
            );

            // Merge both reports into a single output
            $combinedOutput = $requestReport->format() . "\n" . $responseReport->format();
            $exitCode = ($requestReport->getExitCode() !== 0 || $responseReport->getExitCode() !== 0) ? 1 : 0;

            foreach (explode("\n", $combinedOutput) as $line) {
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

            return $exitCode;
        } catch (\Throwable $e) {
            $output->writeln('<error>[FATAL] ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
