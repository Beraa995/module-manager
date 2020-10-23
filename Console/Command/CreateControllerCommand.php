<?php
namespace Mistlanto\ModuleManager\Console\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateControllerCommand extends AbstractModuleCommand
{
    /**
     * Controller based constants
     */
    const DEFAULT_AREA = 'frontend';
    const REQUEST = [
        'GET',
        'POST',
        'PUT',
        'DELETE'
    ];
    const CLASSES = [
        'frontend' => [
            'name' => 'Action',
            'useClass' => 'use Magento\Framework\App\Action\Action;'
        ],
        'adminhtml' => [
            'name' => 'Action',
            'useClass' => 'use Magento\Backend\App\Action;'
        ],
        'get' => [
            'name' => 'HttpGetActionInterface',
            'useClass' => 'use Magento\Framework\App\Action\HttpGetActionInterface;'
        ],
        'post' => [
            'name' => 'HttpPostActionInterface',
            'useClass' => 'use Magento\Framework\App\Action\HttpPostActionInterface;'
        ],
        'put' => [
            'name' => 'HttpPutActionInterface',
            'useClass' => 'use Magento\Framework\App\Action\HttpPutActionInterface;'
        ],
        'delete' => [
            'name' => 'HttpDeleteActionInterface',
            'useClass' => 'use Magento\Framework\App\Action\HttpDeleteActionInterface;'
        ],
    ];

    /**
     * Command option params
     */
    const CONTROLLER_PATH = 'controller-path';
    const CONTROLLER_AREA = 'controller-area';
    const CONTROLLER_REQUEST = 'requests';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_CONTROLLER = 'Invalid controller path!';
    const MESSAGE_INVALID_AREA = 'Invalid controller area!';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:controller:create')
            ->setDescription('Creates the controller class');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::CONTROLLER_PATH,
                null,
                InputOption::VALUE_REQUIRED,
                'Controller path'
            ),
            new InputOption(
                self::CONTROLLER_AREA,
                null,
                InputOption::VALUE_OPTIONAL,
                'Controller area'
            ),
            new InputOption(
                self::CONTROLLER_REQUEST,
                null,
                InputOption::VALUE_OPTIONAL,
                'Requests which controller will process separated by comma'
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

        if (!$input->getOption(self::CONTROLLER_AREA)) {
            $question = new Question(
                '<question>Controller area: (' . implode(',', self::AREAS) . ')</question> ',
                ''
            );

            $input->setOption(
                self::CONTROLLER_AREA,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::CONTROLLER_PATH)) {
            $question = new Question('<question>Controller path: (example: Mistlanto/Index)</question> ', '');

            $input->setOption(
                self::CONTROLLER_PATH,
                trim($questionHelper->ask($input, $output, $question), '/')
            );
        }

        if (!$input->getOption(self::CONTROLLER_REQUEST)) {
            $question = new Question(
                '<question>Comma separated requests: (' . implode(',', self::REQUEST) . ')</question> ',
                ''
            );

            $input->setOption(
                self::CONTROLLER_REQUEST,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Returns function string for the controller file
     * @return string
     */
    protected function createExecuteFunction()
    {
        return $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'execute',
            '',
            'return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);'
        );
    }

    /**
     * @param string $module
     * @param string $controller
     * @param string $area
     * @param string $requests
     * @return void
     */
    protected function createController($module, $controller, $area, $requests)
    {
        $controllerArea = $area ? $area : self::DEFAULT_AREA;
        $moduleDir = null;
        $classSplit = null;
        $parent = null;
        $implementedInterfaces = [];
        $useNamespaces = '';

        if (in_array($controllerArea, self::AREAS)) {
            if ($controllerArea !== 'frontend') {
                $moduleDir = $this->getModuleDir('Controller/Adminhtml', $module);
                $parent = self::CLASSES['adminhtml']['name'];
                $useNamespaces .= self::CLASSES['adminhtml']['useClass'];
            } else {
                $moduleDir = $this->getModuleDir('Controller', $module);
                $parent = self::CLASSES['frontend']['name'];
                $useNamespaces .= self::CLASSES['frontend']['useClass'];
            }
        }

        if ($requests) {
            $requestArray = explode(',', $requests);

            foreach ($requestArray as $request) {
                $lowCaseName = trim(strtolower($request));

                if (isset(self::CLASSES[$lowCaseName])) {
                    $useNamespaces .= "\n";
                    $useNamespaces .= self::CLASSES[$lowCaseName]['useClass'];
                    $implementedInterfaces[] = self::CLASSES[$lowCaseName]['name'];
                }
            }
        }

        if (!$moduleDir) {
            $this->output->writeln('<error>' . __(self::MESSAGE_INVALID_AREA) . '</error>');
            return;
        }

        if ($class = $this->getModuleDirInCode($moduleDir)) {
            $class .= DIRECTORY_SEPARATOR . $controller;
            $classSplit = $this->parseClassString($class);
        }

        $file = $moduleDir . DIRECTORY_SEPARATOR . $controller . '.php';
        $this->createClass(
            $file,
            $classSplit['ns'] ?? '',
            $useNamespaces,
            $classSplit['className'] ?? '',
            $parent,
            !empty($implementedInterfaces)
                ? implode(', ', $implementedInterfaces)
                : '',
            '',
            $this->createExecuteFunction()
        );
    }

    /**
     * Creates controller class
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $controllerPath = $input->getOption(self::CONTROLLER_PATH);
        $controllerArea = $input->getOption(self::CONTROLLER_AREA);
        $controllerRequests = $input->getOption(self::CONTROLLER_REQUEST);

        if (!$controllerPath) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_CONTROLLER) . '</error>');
            return;
        }

        $this->createController($moduleInput, $controllerPath, $controllerArea, $controllerRequests);
    }
}
