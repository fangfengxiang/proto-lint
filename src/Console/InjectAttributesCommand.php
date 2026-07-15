<?php

declare(strict_types=1);

namespace PhpProtoLint\Console;

use PhpProtoLint\Config\BulkConfigLoader;
use PhpProtoLint\Config\MappingConfigLoader;
use PhpProtoLint\Injector\InjectAttributesEngine;
use PhpProtoLint\Locator\ClassLocator;
use PhpProtoLint\Parser\DescriptorReader;
use PhpProtoLint\Parser\ProtoParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * inject-attributes command: Lossless annotation injection.
 * Task 7.6: Binds --config, calls AttributeInjector / DelimiterSandboxInjector.
 */
#[AsCommand(name: 'inject-attributes', description: 'Inject or update Proto annotations in PHP source code')]
final class InjectAttributesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to proto-bulk.json', 'proto-bulk.json')
            ->addOption('php7', null, InputOption::VALUE_NONE, 'Use PHP 7 delimiter sandbox mode')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be injected without writing')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Show verbose debug output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        $php7Mode = (bool) $input->getOption('php7');
        $dryRun = (bool) $input->getOption('dry-run');
        $verbose = (bool) $input->getOption('verbose');

        try {
            $bulkLoader = new BulkConfigLoader();
            $bulkConfig = $bulkLoader->load($configPath);

            $protoParser = new ProtoParser();
            $protoParser->ensureProtocAvailable();

            $protoFiles = [];
            $fieldClassMappings = [];

            foreach ($bulkConfig->services as $svcName => $svcConfig) {
                foreach ($svcConfig->methods as $methodConfig) {
                    if ($methodConfig->mappingFile === null) {
                        continue;
                    }
                    $files = $methodConfig->targetProtoFiles ?? [$bulkConfig->defaultTargetProto];
                    foreach ($files as $f) {
                        $protoFiles[$f] = $f;
                    }
                    $mapLoader = new MappingConfigLoader();
                    $mapConfig = $mapLoader->load($methodConfig->mappingFile);
                    $fieldClassMappings[$svcName][$methodConfig->name] = array_merge(
                        $mapConfig->request->fieldClassMappings,
                        $mapConfig->response->fieldClassMappings,
                    );
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

            $classLocator = new ClassLocator($bulkConfig->sourceDir);
            $engine = new InjectAttributesEngine();
            $result = $engine->inject($protoMetadata, $classLocator, $fieldClassMappings, $php7Mode, $dryRun);

            foreach ($result['output'] as $line) {
                if (str_starts_with($line, '[ERROR]') || str_starts_with($line, '[FATAL]')) {
                    $output->writeln('<error>' . $line . '</error>');
                } elseif (str_starts_with($line, '[OK]')) {
                    $output->writeln('<info>' . $line . '</info>');
                } elseif (str_starts_with($line, '[INJECTED]')) {
                    $output->writeln('<comment>' . $line . '</comment>');
                } else {
                    $output->writeln($line);
                }
            }

            return $result['exitCode'];
        } catch (\Throwable $e) {
            $output->writeln('<error>[FATAL] ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
