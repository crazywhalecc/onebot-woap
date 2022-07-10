<?php

namespace Woap\Command;

use OneBot\V12\Exception\OneBotException;
use OneBot\V12\OneBotBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Woap\Woap;

class RunCommand extends Command
{
    protected static $defaultName = 'run';

    protected function configure()
    {
        $this->setDescription('运行 onebot-woap');
        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, '配置文件路径');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $find_list = [
            WORKING_DIR . '/woap.php',
            WORKING_DIR . '/woap.json',
            WORKING_DIR . '/woap.yaml'
        ];
        // 检测配置文件
        $conf_path = $input->getOption('config');
        if ($conf_path !== null) {
            $find_list = [$conf_path];
        }
        foreach ($find_list as $v) {
            $pathinfo = pathinfo($v);
            if (file_exists($v) && $pathinfo['extension'] === 'php') {
                $conf = require $v;
                // ob_logger()->debug('PHP配置文件加载成功：' . $v);
                break;
            } elseif (file_exists($v) && $pathinfo['extension'] === 'json') {
                $conf = json_decode(file_get_contents($v), true);
                // ob_logger()->debug('JSON配置文件加载成功：' . $v);
                break;
            } elseif (file_exists($v) && ($pathinfo['extension'] === 'yaml' || $pathinfo['extension'] === 'yml')) {
                $conf = Yaml::parseFile($v);
                // ob_logger()->debug('YAML配置文件加载成功：' . $v);
                break;
            }
        }
        if (!isset($conf)) {
            $output->writeln('<error>配置文件加载失败，请检查配置文件是否存在！</error>');
            $output->write('<comment>是否在当前目录生成一个默认配置文件 (woap.yaml) ？[Y/n] </comment>');
            $a = trim(fgets(STDIN));
            if ($a === 'Y' || $a === 'y' || $a === '') {
                copy(__DIR__ . '/../../../woap.default.yaml', WORKING_DIR . '/woap.yaml');
                $output->writeln('<info>配置文件生成成功，请重新运行命令！</info>');
                return 0;
            }
            $output->writeln('用户取消');
            return 1;
        }
        try {
            // 补充上必需的几个参数
            $conf['name'] = 'onebot-woap';
            $conf['platform'] = 'wechat';
            $conf['self_id'] = '';
            Woap::createFromConfig($conf)->run();
        } catch (Throwable $e) {
            // ob_logger()->error($e->getMessage());
            if ($e instanceof OneBotException) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            } else {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                // ob_logger()->error($e->getTraceAsString());
            }
            return 1;
        }
        return 0;
    }
}