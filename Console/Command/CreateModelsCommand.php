<?php
namespace Mistlanto\ModuleManager\Console\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreateModelsCommand extends AbstractModuleCommand
{
    const MODEL_PARENT = '\\Magento\\Framework\\Model\\AbstractModel';
    const COLLECTION_PARENT = '\\Magento\\Framework\\Model\\ResourceModel\\Db\\Collection\\AbstractCollection';
    const RESOURCE_MODEL_PARENT = '\\Magento\\Framework\\Model\\ResourceModel\\Db\\AbstractDb';
    const MODEL_PARENT_EXTENSIBLE = '\\Magento\\Framework\\Model\\AbstractExtensibleModel';
    const MODEL_IDENTITY_INTERFACE = '\\Magento\\Framework\\DataObject\\IdentityInterface';
    const MODEL_EXTENSIBLE_INTERFACE = '\\Magento\\Framework\\Api\\ExtensibleDataInterface';

    /**
     * Command option params
     */
    const MODEL_NAME = 'model-name';
    const MODEL_DATABASE_NAME = 'model-db-name';
    const MODEL_DATABASE_PRIMARY_ID_COLUMN = 'model-db-id';
    const MODEL_EXTENSIBLE = 'model-is-extensible';
    const MODEL_IDENTITY = 'model-is-identity';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_MODEL_NAME = 'Invalid model name!';
    const MESSAGE_INVALID_DB_NAME = 'Invalid db name!';
    const MESSAGE_INVALID_DB_ID = 'Invalid db primary id!';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:crud:create')
            ->setDescription('Creates a CRUD models.');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::MODEL_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Name of the model class.'
            ),
            new InputOption(
                self::MODEL_DATABASE_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Database name.'
            ),
            new InputOption(
                self::MODEL_DATABASE_PRIMARY_ID_COLUMN,
                null,
                InputOption::VALUE_REQUIRED,
                'Database primary id.'
            ),
            new InputOption(
                self::MODEL_EXTENSIBLE,
                null,
                InputOption::VALUE_OPTIONAL,
                'If model is extensible.'
            ),
            new InputOption(
                self::MODEL_IDENTITY,
                null,
                InputOption::VALUE_OPTIONAL,
                'If model should implement Identity interface.'
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

        if (!$input->getOption(self::MODEL_NAME)) {
            $question = new Question('<question>Model name:</question> ', '');

            $input->setOption(
                self::MODEL_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::MODEL_DATABASE_NAME)) {
            $question = new Question('<question>Database name for the resource model:</question> ', '');

            $input->setOption(
                self::MODEL_DATABASE_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::MODEL_DATABASE_PRIMARY_ID_COLUMN)) {
            $question = new Question('<question>Database id field name:</question> ', '');

            $input->setOption(
                self::MODEL_DATABASE_PRIMARY_ID_COLUMN,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::MODEL_EXTENSIBLE)) {
            $confirmationQuestion = new ConfirmationQuestion(
                '<question>Is the model extensible? (y/n)</question> ',
                false
            );

            $input->setOption(
                self::MODEL_EXTENSIBLE,
                $questionHelper->ask($input, $output, $confirmationQuestion)
            );
        }

        if (!$input->getOption(self::MODEL_IDENTITY)) {
            $confirmationQuestion = new ConfirmationQuestion(
                '<question>Should model implement Identity interface? (y/n)</question> ',
                false
            );

            $input->setOption(
                self::MODEL_IDENTITY,
                $questionHelper->ask($input, $output, $confirmationQuestion)
            );
        }
    }

    /**
     * Creates model, resource model and collection classes
     * @param string $moduleInput
     * @param string $modelName
     * @param string $dbName
     * @param string $primaryField
     * @param bool $isExtensible
     * @param bool $isIdentity
     */
    protected function createModelClasses($moduleInput, $modelName, $dbName, $primaryField, $isExtensible, $isIdentity)
    {
        $moduleDirPath = $this->getModuleDir('Model', $moduleInput);
        $modulePathInCode = $this->getModuleDirInCode($moduleDirPath);

        if (!$modulePathInCode) {
            $this->output->writeln('<error>' . __(self::MESSAGE_MODULE_NOT_EXIST) . '</error>');
        }

        //@TODO Service contracts

        $this->createModel($modulePathInCode, $modelName, $dbName, $isExtensible, $isIdentity);
        $this->createResourceModel($modulePathInCode, $modelName, $dbName, $primaryField);
        $this->createCollectionModel($modulePathInCode, $modelName, $dbName);
    }

    /**
     * Creates a resource model class
     * @param string $modulePathInCode
     * @param string $modelName
     * @param string $dbName
     * @param string $primaryField
     */
    protected function createResourceModel($modulePathInCode, $modelName, $dbName, $primaryField)
    {
        $class = implode(DIRECTORY_SEPARATOR, [$modulePathInCode, 'ResourceModel', $modelName]);
        $parent = self::RESOURCE_MODEL_PARENT;
        $classSplit = $this->parseClassString($class);
        $parentClassSplit = $this->parseClassString($parent);
        $functions = $this->createResourceModelFunctions($dbName, $primaryField);

        $this->createClass(
            $class . '.php',
            $classSplit['ns'] ?? '',
            'use ' . trim($parent, '\\') . ';',
            $classSplit['className'] ?? '',
            $parentClassSplit['className'] ?? '',
            '',
            '',
            $functions,
            true
        );
    }

    /**
     * Creates a collection class
     * @param string $modulePathInCode
     * @param string $modelName
     * @param string $dbName
     */
    protected function createCollectionModel($modulePathInCode, $modelName, $dbName)
    {
        $class = implode(DIRECTORY_SEPARATOR, [$modulePathInCode, 'ResourceModel', $modelName, 'Collection']);
        $parent = self::COLLECTION_PARENT;
        $classSplit = $this->parseClassString($class);
        $parentClassSplit = $this->parseClassString($parent);
        $functions = $this->createCollectionFunctions($modelName);
        $properties = $this->createCollectionProperties($dbName);
        $uses = $this->createCollectionUses($parent, $modulePathInCode, $modelName);

        $this->createClass(
            $class . '.php',
            $classSplit['ns'] ?? '',
            $uses,
            $classSplit['className'] ?? '',
            $parentClassSplit['className'] ?? '',
            '',
            $properties,
            $functions,
            true
        );
    }

    /**
     * Returns use namespaces group string for the model class
     * @param string $parent
     * @param string $modulePathInCode
     * @param string $modelName
     * @return string
     */
    protected function createCollectionUses($parent, $modulePathInCode, $modelName)
    {
        $pathTrimmed = str_replace('/', '\\', trim($modulePathInCode, '\\'));
        $modelPath = $pathTrimmed . '\\' . $modelName;
        $resourceModelPath = $pathTrimmed . '\\ResourceModel\\' . $modelName;

        $uses = [
            'use ' . trim($parent, '\\') . ';',
            'use ' . $modelPath . ' as ' . $modelName . 'Model;',
            'use ' . $resourceModelPath . ' as ' . $modelName . 'Resource;'
        ];

        return implode("\n", $uses);
    }

    /**
     * Returns properties for the collection class
     * @param string $dbName
     * @return array
     */
    protected function createCollectionProperties($dbName)
    {
        return [
            $this->createPropertyString('protected $_eventPrefix', '\'' . $dbName . '_collection\''),
            $this->createPropertyString('protected $_eventObject', '\'' . $dbName . '_collection\'')
        ];
    }

    /**
     * Returns functions for the collection
     * @param string $modelName
     * @return string
     */
    protected function createCollectionFunctions($modelName)
    {
        return $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            '_construct',
            '',
            '$this->_init(' . $modelName . 'Model::class, ' . $modelName . 'Resource::class);'
        );
    }

    /**
     * Creates model class
     * @param string $modulePathInCode
     * @param string $modelName
     * @param string $dbName
     * @param bool $isExtensible
     * @param bool $isIdentity
     */
    protected function createModel($modulePathInCode, $modelName, $dbName, $isExtensible, $isIdentity)
    {
        $class = $modulePathInCode . DIRECTORY_SEPARATOR . $modelName;
        $parent = $isExtensible ? self::MODEL_PARENT_EXTENSIBLE : self::MODEL_PARENT;
        $classSplit = $this->parseClassString($class);
        $parentClassSplit = $this->parseClassString($parent);
        $uses = $this->createModelUses($parent, $isExtensible, $isIdentity);
        $functions = $this->createModelFunctions($modelName, $isIdentity);
        $properties = $this->createModelProperties($dbName, $isIdentity);
        $implements = $this->createModelImplements($isIdentity, $isExtensible);

        $this->createClass(
            $class . '.php',
            $classSplit['ns'] ?? '',
            $uses,
            $classSplit['className'] ?? '',
            $parentClassSplit['className'] ?? '',
            $implements,
            $properties,
            $functions,
            true
        );
    }

    /**
     * Returns use namespaces group string for the model class
     * @param string $parent
     * @param string $isExtensible
     * @param string $isIdentity
     * @return string
     */
    protected function createModelUses($parent, $isExtensible, $isIdentity)
    {
        $uses = ['use ' . trim($parent, '\\') . ';'];
        if ($isExtensible) {
            $uses[]= 'use ' . trim(self::MODEL_EXTENSIBLE_INTERFACE, '\\') . ';';
        }

        if ($isIdentity) {
            $uses[]= 'use ' . trim(self::MODEL_IDENTITY_INTERFACE, '\\') . ';';
        }

        return implode("\n", $uses);
    }

    /**
     * Returns implements string for the model class
     * @param bool $isIdentity
     * @param bool $isExtensible
     * @return string
     */
    protected function createModelImplements($isIdentity, $isExtensible)
    {
        $implements = [];
        $classSplitIdentity = $this->parseClassString(self::MODEL_IDENTITY_INTERFACE);
        $classSplitExtensible = $this->parseClassString(self::MODEL_EXTENSIBLE_INTERFACE);

        if ($isIdentity) {
            $implements[]= $classSplitIdentity['className'] ?? '';
        }

        if ($isExtensible) {
            $implements[]= $classSplitExtensible['className'] ?? '';
        }

        return implode(', ', $implements);
    }

    /**
     * Returns properties for the model class
     * @param string $dbName
     * @param bool $isIdentity
     * @return array
     */
    protected function createModelProperties($dbName, $isIdentity)
    {
        $properties = [
            $this->createPropertyString('protected $_cacheTag', '\'' . $dbName . '\''),
            $this->createPropertyString('protected $_eventPrefix', '\'' . $dbName . '\'')
        ];

        if ($isIdentity) {
            $cacheTagConst = $this->createPropertyString('const CACHE_TAG', '\'' . $dbName . '\'');
            return array_merge([$cacheTagConst], $properties);
        }

        return $properties;
    }

    /**
     * Returns functions for the model
     * @param string $modelName
     * @param bool $isIdentity
     * @return array
     */
    protected function createModelFunctions($modelName, $isIdentity)
    {
        $functions = [$this->createFunctionString(
            self::PUBLIC_FUNCTION,
            '_construct',
            '',
            '$this->_init(ResourceModel\\' . $modelName . '::class);'
        )];

        if ($isIdentity) {
            $functions[]= $this->createFunctionString(
                self::PUBLIC_FUNCTION,
                'getIdentities',
                '',
                'return [self::CACHE_TAG . \'_\' . $this->getId()];'
            );
        }

        return $functions;
    }

    /**
     * Returns functions for the resource model
     * @param string $dbName
     * @param string $primaryField
     * @return string
     */
    protected function createResourceModelFunctions($dbName, $primaryField)
    {
        return $this->createFunctionString(
            self::PUBLIC_FUNCTION,
            '_construct',
            '',
            '$this->_init(\'' . $dbName . '\', \'' . $primaryField . '\');'
        );
    }

    /**
     * Creates a CRUD models
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $modelName = $input->getOption(self::MODEL_NAME);
        $dbName = $input->getOption(self::MODEL_DATABASE_NAME);
        $primaryField = $input->getOption(self::MODEL_DATABASE_PRIMARY_ID_COLUMN);
        $isExtensible = $input->getOption(self::MODEL_EXTENSIBLE);
        $isIdentity = $input->getOption(self::MODEL_IDENTITY);

        if (!$modelName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_MODEL_NAME) . '</error>');
            return;
        }

        if (!$dbName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_DB_NAME) . '</error>');
            return;
        }

        if (!$primaryField) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_DB_ID) . '</error>');
            return;
        }

        $this->createModelClasses($moduleInput, $modelName, $dbName, $primaryField, $isExtensible, $isIdentity);
    }
}
