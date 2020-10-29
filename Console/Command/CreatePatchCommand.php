<?php
namespace BKozlic\ModuleManager\Console\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreatePatchCommand extends AbstractModuleCommand
{
    const PATCH_TYPES = [
        'schema' => 'Magento\\Framework\\Setup\\Patch\\SchemaPatchInterface',
        'data' => 'Magento\\Framework\\Setup\\Patch\\DataPatchInterface'
    ];

    /**
     * Command option params
     */
    const PATCH_NAME = 'patch-name';
    const PATCH_TYPE = 'patch-type';
    const PATCH_REVERTIBLE = 'patch-revertible';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_PATCH_NAME = 'Invalid patch name!';
    const MESSAGE_INVALID_PATCH_TYPE = 'Invalid patch type!';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('manager:patch:create')
            ->setDescription('Creates a patch');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::PATCH_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Patch name.'
            ),
            new InputOption(
                self::PATCH_TYPE,
                null,
                InputOption::VALUE_REQUIRED,
                'Patch type.'
            ),
            new InputOption(
                self::PATCH_REVERTIBLE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Is patch revertible.'
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

        if (!$input->getOption(self::PATCH_NAME)) {
            $question = new Question('<question>Patch Name:</question> ');

            $input->setOption(
                self::PATCH_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::PATCH_TYPE)) {
            $question = new ChoiceQuestion(
                '<question>Patch Type:</question> ',
                array_keys(self::PATCH_TYPES)
            );

            $input->setOption(
                self::PATCH_TYPE,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::PATCH_REVERTIBLE)) {
            $confirmationQuestion = new ConfirmationQuestion(
                '<question>Is patch revertible? (y/n) (Default: n)</question> ',
                false
            );

            $input->setOption(
                self::PATCH_REVERTIBLE,
                $questionHelper->ask($input, $output, $confirmationQuestion)
            );
        }
    }

    /**
     * Command for patch creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $patchName = $input->getOption(self::PATCH_NAME);
        $patchType = $input->getOption(self::PATCH_TYPE);
        $patchRevertible = $input->getOption(self::PATCH_REVERTIBLE);

        if (!$patchName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_PATCH_NAME) . '</error>');
            return;
        }

        if (!$patchType || !in_array($patchType, array_keys(self::PATCH_TYPES))) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_PATCH_TYPE) . '</error>');
            return;
        }

        $this->createPatchClass($moduleInput, $patchName, $patchType, $patchRevertible);
    }

    /**
     * Returns data needed for construct method based on patch type
     * @param string $patchType
     * @return array
     */
    protected function getDataForConstructMethod($patchType)
    {
        if ($patchType === 'data') {
            return [
                'property' => $this->createPropertyString('protected $moduleDataSetup'),
                'import' => 'use Magento\\Framework\\Setup\\ModuleDataSetupInterface;',
                'argument' => 'ModuleDataSetupInterface $moduleDataSetup',
                'body' => '$this->moduleDataSetup = $moduleDataSetup;'
            ];
        } elseif ($patchType === 'schema') {
            return [
                'property' => $this->createPropertyString('protected $schemaSetup'),
                'import' => 'use Magento\\Framework\\Setup\\SchemaSetupInterface;',
                'argument' => 'SchemaSetupInterface $schemaSetup',
                'body' => '$this->schemaSetup = $schemaSetup;'
            ];
        }

        return [];
    }

    /**
     * Creates a patch class
     * @param string $moduleInput
     * @param string $patchName
     * @param string $patchType
     * @param bool $patchRevertible
     */
    protected function createPatchClass($moduleInput, $patchName, $patchType, $patchRevertible)
    {
        $patchClass = $this->getInstanceByModuleFolder(
            $moduleInput,
            'Setup/Patch/' . $this->firstUpper($patchType),
            $patchName
        );
        $constructData = $this->getDataForConstructMethod($patchType);
        $classSplit = $this->parseClassString($patchClass);
        $imports = $this->createPatchImports($patchType, $patchRevertible);
        $constructImport = isset($constructData['import']) ? [$constructData['import']] : [];
        $functions = $this->getPatchFunctions($patchRevertible, $constructData);

        $this->createClass(
            $patchClass . '.php',
            $classSplit['ns'] ?? '',
            array_merge($constructImport, $imports['imports']),
            $classSplit['className'] ?? '',
            '',
            $imports['implements'],
            $constructData['property'] ?? '',
            $functions,
            true
        );
    }

    /**
     * Creates functions string for the patch class
     * @param bool $patchRevertible
     * @param array $constructData
     * @return array
     */
    protected function getPatchFunctions($patchRevertible, $constructData)
    {
        $functions = [];

        // construct function
        $functions[] = $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            '__construct',
            $constructData['argument'] ?? '',
            $constructData['body'] ?? ''
        );

        // apply function
        $functions[] = $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'apply'
        );

        // getDependencies Function
        $functions[] = $this->createFunctionString(
            self::PUBLIC_STATIC_FUNCTION,
            'getDependencies',
            '',
            'return [];'
        );

        // getAliases Function
        $functions[] = $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            'getAliases',
            '',
            'return [];'
        );

        if ($patchRevertible) {
            // revert Function
            $functions[] = $this->createFunctionString(
                self::PUBLIC_FUNCTION,
                'revert'
            );
        }

        return $functions;
    }

    /**
     * Create patch class imports string
     * @param string $patchType
     * @param bool $patchRevertible
     * @return array
     */
    protected function createPatchImports($patchType, $patchRevertible)
    {
        $imports = ['use ' . self::PATCH_TYPES[$patchType] . ';'];
        $typeInterface = $this->parseClassString(self::PATCH_TYPES[$patchType]);
        $implements = $typeInterface['className'];

        if ($patchRevertible) {
            $imports[] = 'use Magento\\Framework\\Setup\\Patch\\PatchRevertableInterface;';
            $implements.= ', PatchRevertableInterface';
        }

        return ['imports' => $imports, 'implements' => $implements];
    }
}
