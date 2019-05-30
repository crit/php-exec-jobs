<?php

namespace Crit\ExecJob\Tests;

use Crit\ExecJob\Job;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    function testCreate()
    {
        $this->assertInstanceOf('Crit\ExecJob\Job', new Job());
    }

    function testJobSteps()
    {
        $job = new Job();

        $job->must('echo must1');
        $job->may('ehco typo1');
        $job->must('echo must2');

        $ok = $job->run();

        $this->assertTrue($ok, json_encode($job->errors()));

        $job = new Job();

        $job->must('echo must1');
        $job->may('echo may1');
        $job->must('ehco typo1');

        $ok = $job->run();

        $this->assertFalse($ok);
    }

    function testJobNamedArgs()
    {
        $job = new Job();

        $job->arg('firstname', 'John');
        $job->arg('lastname', 'Doe');

        $job->must('echo <firstname>');
        $job->may('echo <lastname>');

        $ok = $job->run();

        $this->assertTrue($ok, json_encode($job->errors()));
        $this->assertEquals(['John', 'Doe'], $job->output());
    }

    function testJobSetArgWrapper()
    {
        $job = new Job();

        $job->setArgWrapper(':', ':');

        $job->arg('firstname', 'John');
        $job->arg('lastname', 'Doe');

        $job->must('echo :firstname:');
        $job->may('echo :lastname:');

        $ok = $job->run();

        $this->assertTrue($ok, json_encode($job->errors()));
        $this->assertEquals(['John', 'Doe'], $job->output());
    }
}
