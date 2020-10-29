<?php
namespace BKozlic\ModuleManager\Console\Command;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Utility\Files;
use Magento\Framework\Filesystem\Directory\ReadFactory as DirectoryReadFactory;
use Magento\Framework\Filesystem\Directory\WriteFactory as DirectoryWriteFactory;
use Magento\Framework\Filesystem\File\ReadFactory;
use Magento\Framework\Filesystem\File\WriteFactory as FileWriteFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Xml\Generator;
use Magento\Framework\Xml\Parser;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateCronCommand extends AbstractModuleCommand
{
    const CRON_CONFIG_FILE = 'crontab.xml';

    /**
     * Command option params
     */
    const CRON_GROUP = 'cron-group';
    const CRON_NAME = 'cron-name';
    const CRON_INSTANCE = 'cron-instance';
    const CRON_METHOD = 'cron-method';
    const CRON_SCHEDULE = 'cron-schedule';


    /**
     * Command messages
     */
    const MESSAGE_INVALID_CRON_GROUP = 'Invalid cron group!';
    const MESSAGE_INVALID_CRON_NAME = 'Invalid cron name!';
    const MESSAGE_INVALID_CRON_INSTANCE = 'Invalid cron instance!';
    const MESSAGE_INVALID_CRON_METHOD = 'Invalid cron method!';
    const MESSAGE_INVALID_CRON_SCHEDULE = 'Invalid cron schedule!';

    /**
     * @var CreateConfigurationFileCommand
     */
    protected $configurationFileCommand;

    /**
     * CreateCronCommand constructor.
     * @param DirectoryWriteFactory $directoryWriteFactory
     * @param DirectoryReadFactory $directoryReadFactory
     * @param ConsoleOutput $output
     * @param Generator $xmlGenerator
     * @param Parser $xmlParser
     * @param Dir $moduleReader
     * @param ReadFactory $fileRead
     * @param FileWriteFactory $fileWrite
     * @param Files $filesUtility
     * @param File $file
     * @param ScopeConfigInterface $scopeConfig
     * @param CreateConfigurationFileCommand $configurationFileCommand
     * @param string|null $name
     */
    public function __construct(
        DirectoryWriteFactory $directoryWriteFactory,
        DirectoryReadFactory $directoryReadFactory,
        ConsoleOutput $output,
        Generator $xmlGenerator,
        Parser $xmlParser,
        Dir $moduleReader,
        ReadFactory $fileRead,
        FileWriteFactory $fileWrite,
        Files $filesUtility,
        File $file,
        ScopeConfigInterface $scopeConfig,
        CreateConfigurationFileCommand $configurationFileCommand,
        string $name = null
    ) {
        parent::__construct(
            $directoryWriteFactory,
            $directoryReadFactory,
            $output,
            $xmlGenerator,
            $xmlParser,
            $moduleReader,
            $fileRead,
            $fileWrite,
            $filesUtility,
            $file,
            $scopeConfig,
            $name
        );
        $this->configurationFileCommand = $configurationFileCommand;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('manager:cron:create')
            ->setDescription('Creates a cronjob');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::CRON_GROUP,
                null,
                InputOption::VALUE_REQUIRED,
                'Cron Job group.'
            ),
            new InputOption(
                self::CRON_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Cron Job name.'
            ),
            new InputOption(
                self::CRON_INSTANCE,
                null,
                InputOption::VALUE_REQUIRED,
                'Cron Job instance.'
            ),
            new InputOption(
                self::CRON_METHOD,
                null,
                InputOption::VALUE_REQUIRED,
                'Cron Job method.'
            ),
            new InputOption(
                self::CRON_SCHEDULE,
                null,
                InputOption::VALUE_REQUIRED,
                'Cron Job Schedule.'
            )
        ];
    }

    /**
     * @inheridoc
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        if (!$input->getOption(self::CRON_GROUP)) {
            $question = new Question('<question>Cron Group: (Default: default)</question> ', 'default');

            $input->setOption(
                self::CRON_GROUP,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::CRON_NAME)) {
            $question = new Question('<question>Cron Name:</question> ');

            $input->setOption(
                self::CRON_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::CRON_INSTANCE)) {
            $question = new Question('<question>Cron class in Cron folder: (example: BKozlic/CronClass)</question> ');

            $input->setOption(
                self::CRON_INSTANCE,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::CRON_METHOD)) {
            $question = new Question('<question>Cron method name: (Default: execute)</question> ', 'execute');

            $input->setOption(
                self::CRON_METHOD,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::CRON_SCHEDULE)) {
            $question = new Question(
                '<question>Schedule: (Default: * * * * *)</question> ',
                '* * * * *'
            );

            $input->setOption(
                self::CRON_SCHEDULE,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Command for cron job creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $cronGroup = $input->getOption(self::CRON_GROUP);
        $cronName = $input->getOption(self::CRON_NAME);
        $cronInstance = $input->getOption(self::CRON_INSTANCE);
        $cronMethod = $input->getOption(self::CRON_METHOD);
        $cronSchedule = $input->getOption(self::CRON_SCHEDULE);

        if (!$cronGroup) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_CRON_GROUP) . '</error>');
            return;
        }

        if (!$cronName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_CRON_NAME) . '</error>');
            return;
        }

        if (!$cronInstance) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_CRON_INSTANCE) . '</error>');
            return;
        }

        if (!$cronMethod) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_CRON_METHOD) . '</error>');
            return;
        }

        if (!$cronSchedule) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_CRON_SCHEDULE) . '</error>');
            return;
        }

        $cronInstance = $this->getInstanceByModuleFolder($moduleInput, 'Cron', $cronInstance);

        $this->createCronInXml($moduleInput, $cronGroup, $cronName, $cronInstance, $cronMethod, $cronSchedule);
        $this->createCronClass($cronInstance, $cronMethod);
    }

    /**
     * Creates class for the cron job
     * @param string $cronInstance
     * @param string $cronMethod
     */
    protected function createCronClass($cronInstance, $cronMethod)
    {
        $classSplit = $this->parseClassString($cronInstance);

        $this->createClass(
            $cronInstance . '.php',
            $classSplit['ns'] ?? '',
            [],
            $classSplit['className'] ?? '',
            '',
            '',
            '',
            $this->createFunctionString(self::PUBLIC_FUNCTION, $cronMethod),
            true
        );
    }

    /**
     * Creates cron nodes in crontab.xml
     * @param string $module
     * @param string $group
     * @param string $cronName
     * @param string $instance
     * @param string $method
     * @param string $schedule
     */
    protected function createCronInXml($module, $group, $cronName, $instance, $method, $schedule)
    {
        $cronContent = [
            'group' => [
                '_attribute' => [
                    'id' => $group
                ],
                '_value' => [
                    'job' => [
                        '_attribute' => [
                            'name' => $cronName,
                            'instance' => trim(str_replace('/', '\\', $instance), '\\'),
                            'method' => $method
                        ],
                        '_value' => [
                            'schedule' => [
                                '_attribute' => [],
                                '_value' => $schedule
                            ]
                        ]
                    ]
                ]
            ],
        ];

        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $module,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_NAME => self::CRON_CONFIG_FILE,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_CONTENT => $cronContent
        ]);
        $input->setInteractive(true);

        try {
            $this->configurationFileCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
