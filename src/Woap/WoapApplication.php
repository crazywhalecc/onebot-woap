<?php

namespace Woap;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Woap\Command\RunCommand;

class WoapApplication extends Application
{
    const VERSION = '0.1.0-alpha';

    public function __construct()
    {
        parent::__construct('onebot-woap', self::VERSION);
        $this->addCommands([
            new RunCommand()
        ]);
        $this->setDefaultCommand('run');
    }

    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        $this->checkEnvironment();
        return parent::run($input, $output);
    }

    private function checkEnvironment()
    {
        // 首先声明全局变量，因为此项目可能被打包为phar或者micro作为整体分发
        if (!defined('WORKING_DIR')) {
            define('WORKING_DIR', getcwd());
        }
    }
}