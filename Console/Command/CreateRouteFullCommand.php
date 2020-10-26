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

class CreateRouteFullCommand extends AbstractModuleCommand
{
    const FULL_ROUTE_MAPPER = [
        'frontend' => 'standard',
        'adminhtml' => 'admin'
    ];

    /**
     * Command option params
     */
    const FULL_ROUTER_AREA = 'route-full-area';
    const FULL_ROUTE_ID = 'route-full-name';
    const FULL_ROUTE_FRONT_NAME = 'route-full-frontname';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_ROUTE_ID = 'Invalid route id!';
    const MESSAGE_INVALID_ROUTE_AREA = 'Invalid route area!';
    const MESSAGE_INVALID_ROUTE_FRONT_NAME = 'Invalid route front name!';

    /**
     * @var CreateRouteCommand
     */
    protected $createRouteCommand;

    /**
     * @var CreateControllerCommand
     */
    protected $createControllerCommand;

    /**
     * @var CreateLayoutHandleCommand
     */
    protected $createLayoutHandleCommand;

    /**
     * CreateRouteFullCommand constructor.
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
     * @param CreateRouteCommand $createRouteCommand
     * @param CreateControllerCommand $createControllerCommand
     * @param CreateLayoutHandleCommand $createLayoutHandleCommand
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
        CreateRouteCommand $createRouteCommand,
        CreateControllerCommand $createControllerCommand,
        CreateLayoutHandleCommand $createLayoutHandleCommand,
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
        $this->createRouteCommand = $createRouteCommand;
        $this->createControllerCommand = $createControllerCommand;
        $this->createLayoutHandleCommand = $createLayoutHandleCommand;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:route-full:create')
            ->setDescription('Creates a set of controller, route and page layout handle');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::FULL_ROUTE_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'Route id'
            ),
            new InputOption(
                self::FULL_ROUTE_FRONT_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Route front name'
            ),
            new InputOption(
                self::FULL_ROUTER_AREA,
                null,
                InputOption::VALUE_REQUIRED,
                'Route area'
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

        if (!$input->getOption(self::FULL_ROUTER_AREA)) {
            $question = new Question(
                '<question>Route area: (' . implode(',', self::AREAS) . ') (Default: frontend)</question> ',
                'frontend'
            );

            $input->setOption(
                self::FULL_ROUTER_AREA,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::FULL_ROUTE_ID)) {
            $question = new Question('<question>Route id:</question> ');

            $input->setOption(
                self::FULL_ROUTE_ID,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::FULL_ROUTE_FRONT_NAME)) {
            $question = new Question('<question>Route front name:</question> ');

            $input->setOption(
                self::FULL_ROUTE_FRONT_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Command for "full route" creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $routeId = $input->getOption(self::FULL_ROUTE_ID);
        $routeArea = $input->getOption(self::FULL_ROUTER_AREA);
        $routeFrontName = $input->getOption(self::FULL_ROUTE_FRONT_NAME);

        if (!$routeId) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTE_ID) . '</error>');
            return;
        }

        if (!$routeArea || !in_array($routeArea, self::AREAS)) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTE_AREA) . '</error>');
            return;
        }

        if (!$routeFrontName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTE_FRONT_NAME) . '</error>');
            return;
        }

        $this->createFullRouteFiles($moduleInput, $routeId, $routeArea, $routeFrontName);
    }

    /**
     * Creates a "full route" files (controller, route, layout)
     * @param string $moduleInput
     * @param string $routeId
     * @param string $routeArea
     * @param string $routeFrontName
     */
    protected function createFullRouteFiles($moduleInput, $routeId, $routeArea, $routeFrontName)
    {
        $this->createController($moduleInput, $routeArea);
        $this->createRoute($moduleInput, $routeArea, $routeId, $routeFrontName);
        $this->createLayoutHandle($moduleInput, $routeArea, $routeId);
    }

    /**
     * Creates a controller
     * @param string $moduleInput
     * @param string $routeArea
     */
    protected function createController($moduleInput, $routeArea)
    {
        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $moduleInput,
            '--' . CreateControllerCommand::CONTROLLER_PATH => 'Index/Index',
            '--' . CreateControllerCommand::CONTROLLER_AREA => $routeArea,
            '--' . CreateControllerCommand::CONTROLLER_REQUEST => 'GET'
        ]);
        $input->setInteractive(true);

        try {
            $this->createControllerCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Creates a route
     * @param string $moduleInput
     * @param string $routeArea
     * @param string $routeId
     * @param string $routeFrontName
     */
    protected function createRoute($moduleInput, $routeArea, $routeId, $routeFrontName)
    {
        $router = self::FULL_ROUTE_MAPPER[$routeArea] ?? '';
        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $moduleInput,
            '--' . CreateRouteCommand::ROUTER_ID => $router,
            '--' . CreateRouteCommand::ROUTE_ID => $routeId,
            '--' . CreateRouteCommand::ROUTE_FRONT_NAME => $routeFrontName,
            '--' . CreateRouteCommand::ROUTE_AREA => $routeArea
        ]);
        $input->setInteractive(true);

        try {
            $this->createRouteCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Creates a layout handle
     * @param string $moduleInput
     * @param string $routeArea
     * @param string $routeId
     */
    protected function createLayoutHandle($moduleInput, $routeArea, $routeId)
    {
        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $moduleInput,
            '--' . CreateLayoutHandleCommand::HANDLE_NAME => $routeId . '_index_index',
            '--' . CreateLayoutHandleCommand::HANDLE_AREA => $routeArea
        ]);
        $input->setInteractive(true);

        try {
            $this->createLayoutHandleCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
