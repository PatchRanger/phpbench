<?php

/*
 * This file is part of the PHP Bench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use PhpBench\Result\SuiteResult;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use PhpBench\OptionsResolver\OptionsResolver;
use PhpBench\Console\OutputAware;

abstract class BaseCommand extends Command
{
    protected function generateReports(OutputInterface $output, SuiteResult $results)
    {
        $output->writeln('');

        $configuration = $this->getApplication()->getConfiguration();
        $generators = $configuration->getReportGenerators();
        $reportConfigs = $configuration->getReports();

        foreach ($reportConfigs as $index => $reportConfig) {
            if (!isset($reportConfig['name'])) {
                throw new \InvalidArgumentException(sprintf(
                    'Report configuration #%s has no name',
                    $index
                ));
            }

            if (!isset($generators[$reportConfig['name']])) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown report generator "%s", known generators: "%s"',
                    $reportConfig['name'], implode('", "', array_keys($generators))
                ));
            }
        }

        foreach ($reportConfigs as $reportConfig) {
            $reportName = $reportConfig['name'];
            unset($reportConfig['name']);
            $options = new OptionsResolver();
            $report = $generators[$reportName];
            $report->configure($options);

            try {
                $reportConfig = $options->resolve($reportConfig);
            } catch (UndefinedOptionsException $e) {
                throw new \InvalidArgumentException(sprintf(
                    'Error generating report "%s"', $reportName
                ), null, $e);
            }

            if ($report instanceof OutputAware) {
                $report->setOutput($output);
            }

            $report->generate($results, $reportConfig);
        }
    }

    protected function processReportConfigs($rawConfigs)
    {
        $configuration = $this->getApplication()->getConfiguration();

        $configs = array();
        foreach ($rawConfigs as $rawConfig) {
            // If it doesn't look like a JSON string, assume it is the name of a report
            if (substr($rawConfig, 0, 1) !== '{') {
                $configs[] = array('name' => $rawConfig);
                continue;
            }

            $config = json_decode($rawConfig, true);

            if (null === $config) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not decode JSON string: %s', $rawConfig
                ));
            }

            if (!isset($config['name'])) {
                throw new \InvalidArgumentException(sprintf(
                    'You must include the name of the report ("name") in the report configuration: %s',
                    $rawConfig
                ));
            }

            $configs[] = $config;
        }

        if (!$configs) {
            return;
        }

        $configuration->setReports($configs);
    }
}
