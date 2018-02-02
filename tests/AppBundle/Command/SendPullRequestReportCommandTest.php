<?php

namespace tests\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SendPullRequestReportCommandTest extends KernelTestCase
{
    public function testExecute()
    {
        $kernel = static::createKernel();
        $kernel->boot();

        $application = new Application($kernel);

        $command = $application->find('pull_request:report:send_mail');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $output = $commandTester->getDisplay();
        $this->assertRegExp('/Pull requests Reporter/', $output);
        $this->assertRegExp('/\/\/ List of recipients/', $output);
        $this->assertRegExp('/waiting for code review/', $output);
        $this->assertRegExp('/waiting for QA feedback/', $output);
        $this->assertRegExp('/waiting for PM feedback/', $output);
    }
}
