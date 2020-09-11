<?php
namespace Mistlanto\ModuleManager\Console\Command;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreateConfigurationFileCommand extends AbstractModuleCommand
{
    /**
     * Command option params
     */
    const CONFIGURATION_FILE_NAME = 'cfile';
    const CONFIGURATION_FILE_AREA_NAME = 'carea';

    /**
     * Command messages
     */
    const MESSAGE_CONFIGURATION_INVALID = 'Invalid configuration file!';
    const MESSAGE_INVALID_AREA = 'Invalid configuration file area!';

    /**
     * @var string
     */
    protected $urn = '';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:configuration:create')
            ->setDescription('Creates configuration file in etc folder');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::CONFIGURATION_FILE_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Configuration file'
            ),
            new InputOption(
                self::CONFIGURATION_FILE_AREA_NAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'Configuration file area'
            )
        ];
    }

    /**
     * @inheridoc
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws FileSystemException
     * @throws ValidatorException
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        if (!$input->getOption(self::CONFIGURATION_FILE_NAME)) {
            $question = new Question('<question>Configuration file:</question> ', '');
            $file = $questionHelper->ask($input, $output, $question);
            $configData = [];
            $xmlData = $this->getConfigXmlData($file);

            $input->setOption(
                self::CONFIGURATION_FILE_NAME,
                $file
            );

            if (is_array($xmlData)) {
                foreach ($this->getConfigXmlData($file) as $data) {
                    $configData[] = $data['path'] ? $data['path'] : 'global';
                }
            }

            if (count($configData) > 1) {
                $this->output->writeln(__('You have to select one of the following areas: %1', implode(',', $configData)));

                $question = new Question('<question>Configuration file area:</question> ', '');
                $area = $questionHelper->ask($input, $output, $question);

                $input->setOption(
                    self::CONFIGURATION_FILE_AREA_NAME,
                    $area
                );
            }
        }
    }

    /**
     * Generates configuration file
     * @param string $moduleName
     * @param string $file
     * @param string $urn
     * @param string $etcPath
     * @param string $mainNode
     */
    protected function createConfiguration($moduleName, $file, $urn, $etcPath, $mainNode)
    {
        if (!$etcPath) {
            $etcPath = '';
        }

        $areaPath = $etcPath ? 'etc' . DIRECTORY_SEPARATOR . $etcPath : 'etc';
        $moduleDir = $this->getModuleDir($areaPath, $moduleName);

        $this->createFile($moduleDir . DIRECTORY_SEPARATOR . $file);
        $this->generateXml(
            $moduleDir . DIRECTORY_SEPARATOR . $file,
            [$mainNode => [
                '_attribute' => [
                    self::MAIN_XML_ATTRIBUTE_NAME => self::MAIN_XML_ATTRIBUTE_VALUE,
                    self::MODULE_XML_SCHEMA_ATTRIBUTE => $urn[0] ?? '',
                ],
                '_value' => null,
            ]],
            false
        );
    }

    /**
     * Creates configuration file
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws FileSystemException
     * @throws ValidatorException
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configFileInput = $input->getOption(self::CONFIGURATION_FILE_NAME);
        $configFileAreaInput = $input->getOption(self::CONFIGURATION_FILE_AREA_NAME);
        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);

        if (!$configFileInput) {
            $output->writeln('<error>' . __(self::MESSAGE_CONFIGURATION_INVALID) . '</error>');
            return;
        }

        if ($configFileAreaInput && $configFileAreaInput === 'global') {
            $configFileAreaInput = false;
        }

        $xmlData = $this->getConfigXmlData($configFileInput);
        if ($xmlData && is_array($xmlData)) {
            if (count($xmlData) === 0) {
                $output->writeln('<error>' . __(self::MESSAGE_CONFIGURATION_INVALID) . '</error>');
            } elseif (count($xmlData) === 1 && isset($xmlData[0])) {
                $this->createConfiguration(
                    $moduleInput,
                    $configFileInput,
                    $xmlData[0]['urn'],
                    $xmlData[0]['path'],
                    $xmlData[0]['node']
                );
            } else {
                $hasData = false;
                foreach ($xmlData as $data) {
                    if ($configFileAreaInput === $data['path'] && !$hasData) {
                        $hasData =  true;
                        $this->createConfiguration(
                            $moduleInput,
                            $configFileInput,
                            $data['urn'],
                            $data['path'],
                            $data['node']
                        );
                    }
                }

                if (!$hasData) {
                    $output->writeln('<error>' . __(self::MESSAGE_INVALID_AREA) . '</error>');
                }
            }
        }
    }
}
