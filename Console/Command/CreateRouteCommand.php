<?php
namespace Mistlanto\ModuleManager\Console\Command;

use DOMException;
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

class CreateRouteCommand extends AbstractModuleCommand
{
    /**
     * Command option params
     */
    const ROUTER_ID = 'router-id';
    const ROUTE_ID = 'route-id';
    const ROUTE_FRONT_NAME = 'route-frontname';
    const ROUTE_AREA = 'route-area';
    const FRONT_ROUTERS = [
        'robots',
        'urlrewrite',
        'standard',
        'cms',
        'default'
    ];
    const ADMIN_ROUTERS = [
        'admin',
        'default'
    ];
    const ROUTES_FILE = 'routes.xml';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_ROUTER_ID = 'Invalid router id!';
    const MESSAGE_INVALID_ROUTE_ID = 'Invalid route id!';
    const MESSAGE_INVALID_ROUTE_AREA = 'Invalid route area!';
    const MESSAGE_INVALID_ROUTE_FRONT_NAME = 'Invalid route front name!';

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
        $this->setName('mistlanto:route:create')
            ->setDescription('Creates a route in routes.xml');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::ROUTER_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'Router id'
            ),new InputOption(
                self::ROUTE_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'Route id'
            ),
            new InputOption(
                self::ROUTE_FRONT_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Route front name'
            ),
            new InputOption(
                self::ROUTE_AREA,
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

        if (!$input->getOption(self::ROUTE_AREA)) {
            $question = new Question(
                '<question>Route area: (' . implode(',', self::AREAS) . ') (Default: frontend)</question> ',
                'frontend'
            );

            $input->setOption(
                self::ROUTE_AREA,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::ROUTER_ID)) {
            $question = new Question('<question>Router id: (Defaults: standard, admin)</question> ', '');

            $input->setOption(
                self::ROUTER_ID,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::ROUTE_ID)) {
            $question = new Question('<question>Route id:</question> ', '');

            $input->setOption(
                self::ROUTE_ID,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::ROUTE_FRONT_NAME)) {
            $question = new Question(
                '<question>Route front name:</question> ',
                ''
            );

            $input->setOption(
                self::ROUTE_FRONT_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Creates a route in the routes.xml
     * @param string $moduleInput
     * @param string $routeId
     * @param string $routeArea
     * @param string $routeFrontName
     * @param string $routerId
     */
    protected function createRoute($moduleInput, $routeId, $routeArea, $routeFrontName, $routerId)
    {
        $router = null;

        if ($routeArea !== 'frontend') {
            $router = !trim($routerId) ? 'admin' : $routerId;
        } else {
            $router = !trim($routerId) ? 'standard' : $routerId;
        }

        if (!$router) {
            $this->output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTER_ID) . '</error>');
            return;
        }

        $routeContentArrayForXml = [
            'router' => [
                '_attribute' => [
                    'id' => $router
                ],
                '_value' => [
                    'route' => [
                        '_attribute' => [
                            'id' => $routeId,
                            'frontName' => $routeFrontName
                        ],
                        '_value' => [
                            'module' => [
                                '_attribute' => [
                                    'name' => $moduleInput,
                                ],
                                '_value' => []
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->createRoutesFile($moduleInput, self::ROUTES_FILE, $routeArea, $routeContentArrayForXml);
    }

    /**
     * Creates a route
     * @param string $module
     * @param string $file
     * @param string $area
     * @param array $content
     * @return void
     */
    protected function createRoutesFile($module, $file, $area, $content)
    {
        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $module,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_NAME => $file,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_AREA_NAME => $area,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_CONTENT => $content
        ]);
        $input->setInteractive(true);

        try {
            $this->configurationFileCommand->run($input, $this->output);
        } catch (Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Creates a route
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws DOMException
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $routeId = $input->getOption(self::ROUTE_ID);
        $routerId = $input->getOption(self::ROUTER_ID);
        $routeArea = $input->getOption(self::ROUTE_AREA);
        $routeFrontName = $input->getOption(self::ROUTE_FRONT_NAME);

        if (!$routeId) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTE_ID) . '</error>');
            return;
        }

        if (!$routeFrontName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTE_FRONT_NAME) . '</error>');
            return;
        }

        $this->createRoute($moduleInput, $routeId, $routeArea, $routeFrontName, $routerId);
    }
}
