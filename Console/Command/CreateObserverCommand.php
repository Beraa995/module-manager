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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreateObserverCommand extends AbstractModuleCommand
{
    const OBSERVER_CONFIG_FILE = 'events.xml';

    /**
     * Command option params
     */
    const EVENT_NAME = 'event-name';
    const OBSERVER_NAME = 'observer-name';
    const OBSERVER_INSTANCE = 'observer-instance';
    const OBSERVER_DISABLED = 'observer-disabled';
    const OBSERVER_SHARED = 'observer-shared';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_OBSERVER_EVENT = 'Invalid event name!';
    const MESSAGE_INVALID_OBSERVER_NAME = 'Invalid observer name!';
    const MESSAGE_INVALID_OBSERVER_INSTANCE = 'Invalid observer instance!';

    /**
     * @var CreateConfigurationFileCommand
     */
    protected $configurationFileCommand;

    /**
     * CreateRouteCommand constructor.
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
        $this->setName('manager:observer:create')
            ->setDescription('Creates an observer');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::EVENT_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Name of dispatched event.'
            ),
            new InputOption(
                self::OBSERVER_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Observer name for the events.xml file.'
            ),
            new InputOption(
                self::OBSERVER_INSTANCE,
                null,
                InputOption::VALUE_REQUIRED,
                'Observer class.'
            ),
            new InputOption(
                self::OBSERVER_SHARED,
                null,
                InputOption::VALUE_OPTIONAL,
                'Is shared instance?'
            ),
            new InputOption(
                self::OBSERVER_DISABLED,
                null,
                InputOption::VALUE_OPTIONAL,
                'Is observer disabled'
            ),
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

        if (!$input->getOption(self::EVENT_NAME)) {
            $question = new Question('<question>Event name:</question>');

            $input->setOption(
                self::EVENT_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::OBSERVER_NAME)) {
            $question = new Question('<question>Observer name:</question>');

            $input->setOption(
                self::OBSERVER_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::OBSERVER_INSTANCE)) {
            $question = new Question(
                '<question>Observer class in Observer folder: Example(BKozlic/CustomObserver)</question>'
            );

            $input->setOption(
                self::OBSERVER_INSTANCE,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::OBSERVER_DISABLED)) {
            $confirmationQuestion = new ConfirmationQuestion(
                '<question>Is observer disabled? (y/n) (Default: n)</question> ',
                false
            );

            $input->setOption(
                self::OBSERVER_DISABLED,
                $questionHelper->ask($input, $output, $confirmationQuestion)
            );
        }

        if (!$input->getOption(self::OBSERVER_SHARED)) {
            $confirmationQuestion = new ConfirmationQuestion(
                '<question>Is shared instance? (y/n) (Default: n)</question> ',
                false
            );

            $input->setOption(
                self::OBSERVER_SHARED,
                $questionHelper->ask($input, $output, $confirmationQuestion)
            );
        }
    }

    /**
     * Command for observer creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $event = $input->getOption(self::EVENT_NAME);
        $observerName = $input->getOption(self::OBSERVER_NAME);
        $observerInstance = $input->getOption(self::OBSERVER_INSTANCE);
        $observerDisabled = $input->getOption(self::OBSERVER_DISABLED);
        $observerShared = $input->getOption(self::OBSERVER_SHARED);

        if (!$event) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_OBSERVER_EVENT) . '</error>');
            return;
        }

        if (!$observerName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_OBSERVER_NAME) . '</error>');
            return;
        }

        if (!$observerInstance) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_OBSERVER_INSTANCE) . '</error>');
            return;
        }

        $observerInstance = $this->getInstanceByModuleFolder($moduleInput, 'Observer', $observerInstance);

        $this->createObserverInXml(
            $moduleInput,
            $event,
            $observerName,
            $observerInstance,
            $observerDisabled,
            $observerShared
        );
        $this->createObserverClass($observerInstance);
    }

    /**
     * Creates observer class
     * @param string $observerInstance
     */
    protected function createObserverClass($observerInstance)
    {
        $classSplit = $this->parseClassString($observerInstance);

        $this->createClass(
            $observerInstance . '.php',
            $classSplit['ns'] ?? '',
            [
                'use Magento\Framework\Event\ObserverInterface;',
                'use Magento\Framework\Event\Observer;'
            ],
            $classSplit['className'] ?? '',
            '',
            'ObserverInterface',
            '',
            $this->createFunctionString(
                self::PUBLIC_FUNCTION,
                'execute',
                'Observer $observer',
                '// Observer execution code...'
            ),
            true
        );
    }

    /**
     * Creates observer in the events.xml
     * @param string $moduleInput
     * @param string $event
     * @param string $observerName
     * @param string $observerInstance
     * @param bool $observerDisabled
     * @param bool $observerShared
     */
    protected function createObserverInXml(
        $moduleInput,
        $event,
        $observerName,
        $observerInstance,
        $observerDisabled,
        $observerShared
    ) {
        $observerContent = [
            'event' => [
                '_attribute' => [
                    'name' => $event
                ],
                '_value' => [
                    'observer' => [
                        '_attribute' => [
                            'name' => $observerName,
                            'instance' => trim(str_replace('/', '\\', $observerInstance), '\\')
                        ],
                        '_value' => []
                    ]
                ]
            ],
        ];

        if ($observerDisabled) {
            $observerContent['event']['_value']['observer']['_attribute']['disabled'] = 'true';
        }

        if ($observerShared) {
            $observerContent['event']['_value']['observer']['_attribute']['shared'] = 'true';
        }

        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $moduleInput,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_NAME => self::OBSERVER_CONFIG_FILE,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_CONTENT => $observerContent
        ]);
        $input->setInteractive(true);

        try {
            $this->configurationFileCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
