<?php
namespace Mistlanto\ModuleManager\Console\Command;

use DOMException;
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
    const ROUTER_ID = 'routerid';
    const ROUTE_ID = 'routeid';
    const ROUTE_FRONT_NAME = 'routefront';
    const ROUTE_AREA = 'routearea';
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
     * @param InputInterface $input
     * @param OutputInterface $output
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
     * @throws DOMException
     */
    protected function createRoute($moduleInput, $routeId, $routeArea, $routeFrontName, $routerId)
    {
        $moduleDir = null;
        $area = $routeArea;
        $router = null;

        if ($routeArea && in_array($routeArea, self::AREAS)) {
            if ($routeArea !== 'frontend') {
                $area = 'adminhtml';
                $moduleDir = $this->getModuleDir('etc/adminhtml', $moduleInput);
                $router = !trim($routerId) ? 'admin' : $routerId;
            } else {
                $area = 'frontend';
                $moduleDir = $this->getModuleDir('etc/frontend', $moduleInput);
                $router = !trim($routerId) ? 'standard' : $routerId;
            }
        }

        if (!$moduleDir) {
            $this->output->writeln('<error>' . __(self::MESSAGE_MODULE_NOT_EXIST) . '</error>');
            return;
        }

        if (!$router) {
            $this->output->writeln('<error>' . __(self::MESSAGE_INVALID_ROUTER_ID) . '</error>');
            return;
        }

        $file = $moduleDir . DIRECTORY_SEPARATOR . self::ROUTES_FILE;
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

        if (!$this->file->fileExists($file)) {
            $this->createRoutesFile($moduleInput, self::ROUTES_FILE, $area);
            $this->fillXmlFile($file, $routeContentArrayForXml, 'router');
        } else {
            $this->fillXmlFile($file, $routeContentArrayForXml, 'router');
        }
    }

    /**
     * Creates a routes.xml configuration file
     * @param string $module
     * @param string $file
     * @param string $area
     * @return void
     */
    protected function createRoutesFile($module, $file, $area)
    {
        $input = new ArrayInput([
            '--' . self::MODULE_OPTION_NAME => $module,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_NAME => $file,
            '--' . CreateConfigurationFileCommand::CONFIGURATION_FILE_AREA_NAME => $area
        ]);

        try {
            $this->configurationFileCommand->run($input, $this->output);
        } catch (\Exception $e) {
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
