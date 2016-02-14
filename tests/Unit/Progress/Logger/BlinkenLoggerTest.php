<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Tests\Unit\Progress\Logger;

use PhpBench\Model\IterationResult;
use PhpBench\Model\ParameterSet;
use PhpBench\Model\Variant;
use PhpBench\Progress\Logger\BlinkenLogger;
use PhpBench\Util\TimeUnit;
use Symfony\Component\Console\Output\BufferedOutput;

class BlinkenLoggerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->output = new BufferedOutput();
        $this->timeUnit = new TimeUnit(TimeUnit::MICROSECONDS, TimeUnit::MILLISECONDS);

        $this->logger = new BlinkenLogger($this->timeUnit);
        $this->logger->setOutput($this->output);
        $this->benchmark = $this->prophesize('PhpBench\Model\Benchmark');
        $this->subject = $this->prophesize('PhpBench\Model\Subject');
        $this->variant = new Variant(
            $this->subject->reveal(),
            new ParameterSet(),
            10,
            0
        );
        $this->variant->spawnIterations(4);
        $this->benchmark->getSubjects()->willReturn(array(
            $this->subject->reveal(),
        ));
        $this->benchmark->getClass()->willReturn('BenchmarkTest');

        $this->subject->getName()->willReturn('benchSubject');
        $this->subject->getIndex()->willReturn(1);
        $this->subject->getOutputTimeUnit()->willReturn('milliseconds');
        $this->subject->getOutputMode()->willReturn('time');
        $this->subject->getRetryThreshold()->willReturn(10);
    }

    /**
     * It should show the benchmark name and list all of the subjects.
     */
    public function testShowAndList()
    {
        $this->logger->benchmarkStart($this->benchmark->reveal());
        $display = $this->output->fetch();
        $this->assertContains('BenchmarkTest', $display);
        $this->assertContains('#0 benchSubject', $display);
    }

    /**
     * It should initialize the status line.
     */
    public function testIterationStart()
    {
        $this->logger->iterationStart($this->variant[0]);
        $display = $this->output->fetch();
        $this->assertContains(
            '0.000',
            $display
        );
    }

    /**
     * It should show information at the start of the variant.
     */
    public function testIterationsStart()
    {
        $this->logger->variantStart($this->variant);
        $display = $this->output->fetch();
        $this->assertContains(
            'benchSubject',
            $display
        );
        $this->assertContains(
            'parameters []',
            $display
        );
    }

    /**
     * It should show an error if the iteration has an exception.
     */
    public function testIterationException()
    {
        $this->variant->setException(new \Exception('foo'));
        $this->logger->variantEnd($this->variant);
        $this->assertContains('ERROR', $this->output->fetch());
    }

    /**
     * It should show statistics when an iteration is completed (and there
     * were no rejections).
     */
    public function testIterationEndStats()
    {
        foreach ($this->variant as $iteration) {
            $iteration->setResult(new IterationResult(10, 10));
        }
        $this->variant->computeStats();

        $this->logger->variantEnd($this->variant);
        $this->assertContains('RSD/r: 0.00%', $this->output->fetch());
    }
}
