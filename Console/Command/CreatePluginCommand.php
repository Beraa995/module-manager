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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreatePluginCommand extends AbstractModuleCommand
{
    const PLUGIN_CONFIG_FILE = 'di.xml';

    /**
     * Command option params
     */
    const TARGET_CLASS = 'target-class';
    const TARGET_METHOD = 'target-method';
    const PLUGIN_CLASS = 'plugin-class';
    const PLUGIN_NAME = 'plugin-name';
    const PLUGIN_SORT = 'plugin-sort';
    const PLUGIN_DISABLED = 'plugin-disabled';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_PLUGIN_CLASS = 'Invalid plugin class!';
    const MESSAGE_INVALID_PLUGIN_NAME = 'Invalid plugin name!';
    const MESSAGE_INVALID_PLUGIN_TARGET_CLASS = 'Invalid plugin target class!';
    const MESSAGE_INVALID_PLUGIN_TARGET_METHOD = 'Invalid plugin target method!';
    const MESSAGE_TARGET_METHOD_NOT_PUBLIC = 'Method is not public!';

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
        $this->setName('mistlanto:plugin:create')
            ->setDescription('Creates a plugin');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::TARGET_CLASS,
                null,
                InputOption::VALUE_REQUIRED,
                'Class for which plugin is being created.'
            ),
            new InputOption(
                self::TARGET_METHOD,
                null,
                InputOption::VALUE_REQUIRED,
                'Method on which plugin is being created.'
            ),
            new InputOption(
                self::PLUGIN_CLASS,
                null,
                InputOption::VALUE_REQUIRED,
                'Plugin class.'
            ),
            new InputOption(
                self::PLUGIN_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Plugin name'
            ),
            new InputOption(
                self::PLUGIN_SORT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Plugin sort order.'
            ),
            new InputOption(
                self::PLUGIN_DISABLED,
                null,
                InputOption::VALUE_OPTIONAL,
                'Is plugin disabled?'
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

        if (!$input->getOption(self::PLUGIN_CLASS)) {
            $question = new Question(
                '<question>Plugin class in Plugin folder: (example: Mistlanto/PluginClass)</question> '
            );

            $input->setOption(
                self::PLUGIN_CLASS,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::TARGET_CLASS)) {
            $question = new Question('<question>Target class:</question> ');

            $input->setOption(
                self::TARGET_CLASS,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::TARGET_METHOD)) {
            $question = new Question('<question>Target method:</question> ');

            $input->setOption(
                self::TARGET_METHOD,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::PLUGIN_NAME)) {
            $question = new Question('<question>Plugin name:</question> ');

            $input->setOption(
                self::PLUGIN_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::PLUGIN_SORT)) {
            $question = new Question('<question>Plugin sort number: (Default: No sort)</question> ');

            $input->setOption(
                self::PLUGIN_SORT,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::PLUGIN_DISABLED)) {
            $confirmationQuestion = new ConfirmationQuestion(
                '<question>Is plugin disabled? (y/n) (Default: n)</question> ',
                false
            );

            $input->setOption(
                self::PLUGIN_DISABLED,
                $questionHelper->ask($input, $output, $confirmationQuestion)
            );
        }
    }

    /**
     * Command for plugin creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $pluginClass = $input->getOption(self::PLUGIN_CLASS);
        $targetClass = $input->getOption(self::TARGET_CLASS);
        $targetMethod = $input->getOption(self::TARGET_METHOD);
        $pluginName = $input->getOption(self::PLUGIN_NAME);
        $pluginSort = $input->getOption(self::PLUGIN_SORT);
        $pluginDisabled = $input->getOption(self::PLUGIN_DISABLED);

        if (!$pluginClass) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_PLUGIN_CLASS) . '</error>');
            return;
        }

        if (!$targetClass) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_PLUGIN_TARGET_CLASS) . '</error>');
            return;
        }

        if (!$targetMethod) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_PLUGIN_TARGET_METHOD) . '</error>');
            return;
        }

        if (!$pluginName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_PLUGIN_NAME) . '</error>');
            return;
        }

        $methodData = $this->getMethodData($targetClass, $targetMethod);

        if (!isset($methodData['isPublic'])) {
            return;
        }

        if (!$methodData['isPublic']) {
            $output->writeln('<error>' . __(self::MESSAGE_TARGET_METHOD_NOT_PUBLIC) . '</error>');
            return;
        }

        $targetClass = str_replace('/', '\\', $targetClass);
        $pluginClass = $this->getInstanceByModuleFolder($moduleInput, 'Plugin', $pluginClass);

        $this->createPluginInXml($moduleInput, $pluginClass, $targetClass, $pluginName, $pluginSort, $pluginDisabled);
        $this->createPluginClass($pluginClass, $targetClass, $targetMethod, $methodData);
    }

    /**
     * Creates plugin class
     * @param string $pluginClass
     * @param string $targetClass
     * @param string $targetMethod
     * @param array $methodData
     */
    protected function createPluginClass($pluginClass, $targetClass, $targetMethod, $methodData)
    {
        $classSplit = $this->parseClassString($pluginClass);
        $functions = $this->createPluginFunctions($targetClass, $targetMethod, $methodData);

        $this->createClass(
            $pluginClass . '.php',
            $classSplit['ns'] ?? '',
            ['use ' . trim($targetClass, '\\') . ';'],
            $classSplit['className'] ?? '',
            '',
            '',
            '',
            $functions,
            true
        );
    }

    /**
     * Returns functions for the plugin class
     * @param string $targetClass
     * @param string $targetMethod
     * @param array $methodData
     * @return array
     */
    protected function createPluginFunctions($targetClass, $targetMethod, $methodData)
    {
        $classSplit = $this->parseClassString($targetClass);
        $subjectArgument = $classSplit['className'] . ' $subject, ';
        $parameters = [];
        $functions = [];

        foreach ($methodData['params'] as $param) {
            $parameters[] = '$' . $param['name'];
        }

        $functions[]= $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'before' . $this->firstUpper($targetMethod),
            $subjectArgument . implode(', ', $parameters),
            'return [' . implode(', ', $parameters) . '];'
        );

        $functions[]= $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'around' . $this->firstUpper($targetMethod),
            $subjectArgument . '\\Closure $proceed, ' . implode(', ', $parameters),
            'return $proceed();'
        );

        $functions[]= $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'after' . $this->firstUpper($targetMethod),
            $subjectArgument . '$result, ' . implode(', ', $parameters),
            'return $result;'
        );

        return $functions;
    }

    /**
     * Adds plugin definition in the xml
     * @param string $module
     * @param string $pluginClass
     * @param string $targetClass
     * @param string $name
     * @param string|int $pluginSort
     * @param bool $pluginDisabled
     */
    protected function createPluginInXml($module, $pluginClass, $targetClass, $name, $pluginSort, $pluginDisabled)
    {
        $pluginContent = [
            'type' => [
                '_attribute' => [
                    'name' => trim($targetClass, '\\')
                ],
                '_value' => [
                    'plugin' => [
                        '_attribute' => [
                            'name' => $name,
                            'type' => trim(str_replace('/', '\\', $pluginClass), '\\')
                        ],
                        '_value' => []
                    ]
                ]
            ],
        ];

        if (is_numeric($pluginSort)) {
            $pluginContent['type']['_value']['plugin']['_attribute']['sortOrder'] = $pluginSort;
        }

        if ($pluginDisabled) {
            $pluginContent['type']['_value']['plugin']['_attribute']['disabled'] = 'true';
        }

        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $module,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_NAME => self::PLUGIN_CONFIG_FILE,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_CONTENT => $pluginContent
        ]);
        $input->setInteractive(true);

        try {
            $this->configurationFileCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
