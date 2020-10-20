<?php
namespace Mistlanto\ModuleManager\Console\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * A command class for module creation.
 */
class CreateModuleCommand extends AbstractModuleCommand
{
    /**
     * Command option params
     */
    const MODULE_DEPENDENCY_NAME = 'deps';

    /**
     * Command messages
     */
    const MESSAGE_MODULE_EXIST = 'Module already exist in code directory!';
    const MESSAGE_MODULE_MISSING = 'Module name is missing!';
    const MESSAGE_MODULE_NAME_INCORRECT = 'Module name is not valid!';
    const MESSAGE_DIRECTORY_CREATION = 'Directory can\'t be created!';
    const MESSAGE_VALIDATION = 'Validation failed!';

    /**
     * XML constants
     */
    const MODULE_XML_SCHEMA_ATTRIBUTE_VALUE = 'urn:magento:framework:Module/etc/module.xsd';

    /**
     * Files constants
     */
    const REGISTRATION_FILE_NAME = 'registrationBase';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:module:create')
            ->setDescription('Creates module with required files');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::MODULE_DEPENDENCY_NAME,
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Module Dependencies'
            )
        ];
    }

    /**
     * Generates content for module.xml
     * @param string $modulePath
     * @param string $moduleInput
     * @param array $dependencies
     * @return void
     */
    protected function fillModuleXml($modulePath, $moduleInput, $dependencies)
    {
        $deps = [
            'sequence' => []
        ];

        if (!empty($dependencies)) {
            foreach ($dependencies as $dependency) {
                $deps['sequence'][]= [
                    '_attribute' => [
                        'name' => $dependency
                    ],
                    '_value' => []
                ];
            }
        }

        $this->generateXml(
            $modulePath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'module.xml',
            ['config' => [
                '_attribute' => [
                    self::MAIN_XML_ATTRIBUTE_NAME => self::MAIN_XML_ATTRIBUTE_VALUE,
                    self::MODULE_XML_SCHEMA_ATTRIBUTE => self::MODULE_XML_SCHEMA_ATTRIBUTE_VALUE,
                ],
                '_value' => [
                    'module' => [
                        '_attribute' => [
                            'name' => $moduleInput
                        ],
                        '_value' => !empty($deps['sequence']) ? $deps : []
                    ],
                ],
            ]],
            'true',
            'module'
        );
    }

    /**
     * @inheridoc
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        if (!$input->getOption(self::MODULE_DEPENDENCY_NAME)) {
            $confirmationQuestion = new ConfirmationQuestion('<question>Does module have dependencies? (y/n)</question> ', false);

            if ($questionHelper->ask($input, $output, $confirmationQuestion)) {
                $question = new Question('<question>Enter comma separated module names:</question> ', '');

                $input->setOption(
                    self::MODULE_DEPENDENCY_NAME,
                    explode(',', $questionHelper->ask($input, $output, $question))
                );
            }
        }
    }

    /**
     * Creates module with required files
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $dependencies = $input->getOption(self::MODULE_DEPENDENCY_NAME);

        if (!$moduleInput) {
            $output->writeln('<error>' . __(self::MESSAGE_MODULE_MISSING) . '</error>');
            return;
        }

        if (!preg_match('/^[A-Za-z]+_[A-Za-z]+$/', $moduleInput)) {
            $output->writeln('<error>' . self::MESSAGE_MODULE_NAME_INCORRECT . '</error>');
            return;
        }

        $folderNames = explode('_', $moduleInput);
        $folderNames = array_map([$this, 'firstUpper'], $folderNames);
        $moduleName = implode('_', $folderNames);
        $modulePath = implode(DIRECTORY_SEPARATOR, $folderNames);
        $registrationFileContent = str_replace(
            '{Replace}',
            $moduleName,
            $this->getFileContents(
                self::FILES_DIR_NAME . DIRECTORY_SEPARATOR . self::REGISTRATION_FILE_NAME
            )
        );

        if ($this->isModuleExist($modulePath)) {
            $output->writeln('<error>' . __(self::MESSAGE_MODULE_EXIST) . '</error>');
            return;
        }

        $this->createDirectory($modulePath);
        $this->createFile($modulePath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'module.xml');
        $this->fillModuleXml($modulePath, $moduleName, $dependencies);
        $this->writeToFile(
            $modulePath . DIRECTORY_SEPARATOR . 'registration.php',
            $registrationFileContent,
            true
        );
    }
}
