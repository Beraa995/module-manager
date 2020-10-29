<?php
namespace Mistlanto\ModuleManager\Console\Command;

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

class CreateConsoleCommand extends AbstractModuleCommand
{
    const COMMAND_CONFIG_FILE = 'di.xml';
    const COMMAND_LIST_INTERFACE = 'Magento\\Framework\\Console\\CommandListInterface';
    const PARENT_COMMAND = 'Symfony\\Component\\Console\\Command\\Command';
    const PARENT_COMMAND_SHORT = 'Command';
    const OUTPUT_INTERFACE = 'Symfony\\Component\\Console\\Output\\OutputInterface';
    const OUTPUT_INTERFACE_SHORT = 'OutputInterface';
    const INPUT_INTERFACE = 'Symfony\\Component\\Console\\Input\\InputInterface';
    const INPUT_INTERFACE_SHORT = 'InputInterface';

    /**
     * Command option params
     */
    const COMMAND_NAME_XML = 'command-xml-name';
    const COMMAND_EXECUTE_NAME = 'command-execute-name';
    const COMMAND_CLASSNAME = 'command-classname';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_COMMAND_NAME_XML = 'Invalid xml command name!';
    const MESSAGE_INVALID_COMMAND_EXECUTE_NAME = 'Invalid command execute name!';
    const MESSAGE_INVALID_COMMAND_CLASSNAME = 'Invalid command class name!';

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
        $this->setName('mistlanto:command:create')
            ->setDescription('Creates a console command');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::COMMAND_NAME_XML,
                null,
                InputOption::VALUE_REQUIRED,
                'Command name in di.xml.'
            ),
            new InputOption(
                self::COMMAND_EXECUTE_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Command execute name.'
            ),
            new InputOption(
                self::COMMAND_CLASSNAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Command class name.'
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

        if (!$input->getOption(self::COMMAND_NAME_XML)) {
            $question = new Question('<question>Command name in XML:</question> ');

            $input->setOption(
                self::COMMAND_NAME_XML,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::COMMAND_EXECUTE_NAME)) {
            $question = new Question('<question>Command name for CLI: (Example: mistlanto:create)</question> ');

            $input->setOption(
                self::COMMAND_EXECUTE_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::COMMAND_CLASSNAME)) {
            $question = new Question('<question>Class Name:</question> ');

            $input->setOption(
                self::COMMAND_CLASSNAME,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Command for console command creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $commandXmlName = $input->getOption(self::COMMAND_NAME_XML);
        $commandClassName = $input->getOption(self::COMMAND_CLASSNAME);
        $commandExecuteName = $input->getOption(self::COMMAND_EXECUTE_NAME);

        if (!$commandXmlName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_COMMAND_NAME_XML) . '</error>');
            return;
        }

        if (!$commandClassName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_COMMAND_CLASSNAME) . '</error>');
            return;
        }

        if (!$commandExecuteName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_COMMAND_EXECUTE_NAME) . '</error>');
            return;
        }

        $commandClassName = $this->getInstanceByModuleFolder(
            $moduleInput,
            'Console/Command',
            $commandClassName
        );

        $this->createCommandInXml($moduleInput, $commandXmlName, $commandClassName);
        $this->createCommandClass($commandClassName, $commandExecuteName);
    }

    /**
     * Creates class for the console command
     * @param string $commandClassName
     * @param string $commandExecuteName
     */
    protected function createCommandClass($commandClassName, $commandExecuteName)
    {
        $classSplit = $this->parseClassString($commandClassName);
        $functions = $this->createConsoleFunctions($commandExecuteName);

        $this->createClass(
            $commandClassName . '.php',
            $classSplit['ns'] ?? '',
            [
                'use ' . self::INPUT_INTERFACE . ';',
                'use ' . self::OUTPUT_INTERFACE . ';',
                'use ' . self::PARENT_COMMAND . ';',
            ],
            $classSplit['className'] ?? '',
            self::PARENT_COMMAND_SHORT,
            '',
            '',
            $functions,
            true
        );
    }

    /**
     * Returns functions for the console class
     * @param string $commandExecuteName
     * @return array
     */
    protected function createConsoleFunctions($commandExecuteName)
    {
        $functions = [];

        // Content for configure method
        $configureString = implode(PHP_EOL, [
            '$this->setName(\'' . $commandExecuteName . '\');',
            $this->indentEachLine('$this->setDefinition([]);'),
            $this->indentEachLine('parent::configure();')
        ]);

        $functions[]= $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'configure',
            '',
            $configureString
        );

        $functions[]= $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'execute',
            self::INPUT_INTERFACE_SHORT . ' $input, ' . self::OUTPUT_INTERFACE_SHORT . ' $output',
            '// Command execution logic'
        );

        return $functions;
    }

    /**
     * Creates command nodes in di.xml
     * @param string $module
     * @param string $commandXmlName
     * @param string $commandClassName
     */
    protected function createCommandInXml($module, $commandXmlName, $commandClassName)
    {
        $commandContent = [
            'type' => [
                '_attribute' => [
                    'name' => self::COMMAND_LIST_INTERFACE
                ],
                '_value' => [
                    'arguments' => [
                        '_attribute' => [],
                        '_value' => [
                            'argument' => [
                                '_attribute' => [
                                    'name' => 'commands',
                                    'xsi:type' => 'array'
                                ],
                                '_value' => [
                                    'item' => [
                                        '_attribute' => [
                                            'name' => $commandXmlName,
                                            'xsi:type' => 'object'
                                        ],
                                        '_value' => $commandClassName
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
        ];

        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $module,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_NAME => self::COMMAND_CONFIG_FILE,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_AREA_NAME => 'global',
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_CONTENT => $commandContent
        ]);
        $input->setInteractive(true);

        try {
            $this->configurationFileCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
