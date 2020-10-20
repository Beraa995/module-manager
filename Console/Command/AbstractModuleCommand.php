<?php
namespace Mistlanto\ModuleManager\Console\Command;

use _HumbugBox10beb0db089e\Roave\BetterReflection\Reflection\ReflectionNamedType;
use DOMException;
use Magento\Framework\App\Utility\Files;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\ReadFactory as DirectoryReadFactory;
use Magento\Framework\Filesystem\Directory\WriteFactory as DirectoryWriteFactory;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Filesystem\File\ReadFactory;
use Magento\Framework\Filesystem\File\WriteFactory as FileWriteFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Xml\Generator;
use Magento\Framework\Xml\Parser;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Mistlanto abstract command class.
 */
abstract class AbstractModuleCommand extends Command
{
    const CODE_DIRECTORY = BP . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'code' . DIRECTORY_SEPARATOR;
    const MODULE_NAME = 'Mistlanto_ModuleManager';
    const CLASS_FILE_NAME = 'classBase';
    const FUNCTION_FILE_NAME = 'functionBase';
    const FILES_DIR_NAME = 'Files';
    const APP_CODE = 'app/code/';
    const NAMESPACE_REPLACE = '{namespace_replace}';
    const USE_REPLACE = '{use_replace}';
    const CLASSNAME_REPLACE = '{class_name_replace}';
    const PARENT_REPLACE = '{parent_replace}';
    const IMPLEMENTS_REPLACE = '{implements_replace}';
    const PROPERTIES_REPLACE = '{properties_replace}';
    const FUNCTIONS_REPLACE = '{functions_replace}';
    const FUNCTION_MODIFIER_REPLACE = '{access_modifier_function}';
    const FUNCTION_NAME_REPLACE = '{function_name}';
    const FUNCTION_ARGUMENT_REPLACE = '{function_arguments}';
    const PUBLIC_FUNCTION = 'public function';
    const AREAS = [
        'frontend',
        'adminhtml'
    ];

    /**
     * Command option params
     */
    const MODULE_OPTION_NAME = 'module';

    /**
     * Messages
     */
    const MESSAGE_DIRECTORY_CREATED = 'Directory %1 is successfully created!';
    const MESSAGE_FILE_FILLED_WITH_THE_CONTENT = 'Content is added to the %1!';
    const MESSAGE_FILE_FILLED_WITH_THE_CONTENT_ERROR = 'Content can\'t be added to the %1!';
    const MESSAGE_FILE_FILLED_WITH_THE_CONTENT_NONE = 'No content is added to the %1!';
    const MESSAGE_FILE_CREATED = 'File %1 is successfully created!';
    const MESSAGE_FILE_EXIST = 'File %1 already exist!';
    const MESSAGE_MODULE_NOT_EXIST = 'Module doesn\'t exist in code directory!';
    const MESSAGE_MODULE_MISSING = 'Module name is missing!';

    /**
     * XML constants
     */
    const MAIN_XML_ATTRIBUTE_NAME = 'xmlns:xsi';
    const MAIN_XML_ATTRIBUTE_VALUE = 'http://www.w3.org/2001/XMLSchema-instance';
    const MODULE_XML_SCHEMA_ATTRIBUTE = 'xsi:noNamespaceSchemaLocation';

    /**
     * @var DirectoryWriteFactory
     */
    protected $directoryWriteFactory;

    /**
     * @var ConsoleOutput
     */
    protected $output;

    /**
     * @var Generator
     */
    protected $xmlGenerator;

    /**
     * @var Dir
     */
    protected $moduleReader;

    /**
     * @var ReadFactory
     */
    protected $fileRead;

    /**
     * @var FileWriteFactory
     */
    protected $fileWrite;

    /**
     * @var DirectoryReadFactory
     */
    protected $directoryReadFactory;

    /**
     * @var Files
     */
    protected $filesUtility;

    /**
     * @var Parser
     */
    protected $xmlParser;

    /**
     * @var File
     */
    protected $file;

    /**
     * CreateModuleCommand constructor.
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
        string $name = null
    ) {
        parent::__construct($name);
        $this->directoryWriteFactory = $directoryWriteFactory->create(self::CODE_DIRECTORY);
        $this->directoryReadFactory = $directoryReadFactory;
        $this->output = $output;
        $this->xmlGenerator = $xmlGenerator;
        $this->moduleReader = $moduleReader;
        $this->fileRead = $fileRead;
        $this->fileWrite = $fileWrite;
        $this->filesUtility = $filesUtility;
        $this->xmlParser = $xmlParser;
        $this->file = $file;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $options = array_merge(
            [
                new InputOption(
                    self::MODULE_OPTION_NAME,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Module Name'
                )
            ],
            $this->getOptions()
        );

        $this->setDefinition($options);

        parent::configure();
    }

    /**
     * @param string $configFileName
     * @param bool $onlyNode
     * @return array
     */
    protected function getConfigXmlData($configFileName, $onlyNode = false)
    {
        //@TODO Create custom method for faster results
        $files = $this->filesUtility->getXmlCatalogFiles($configFileName);

        $data = [];
        foreach ($files as $file) {
            $fileDir = dirname($file[0]);
            $fileName = basename($file[0]);

            if (!strrpos($fileDir, "/etc")) {
                continue;
            }
            $pathInEtc = substr($fileDir, strrpos($fileDir, "/etc") + 5, strlen($fileDir));
            $content = '';
            $mainNode = '';
            try {
                $content = $this->directoryReadFactory->create($fileDir)->readFile($fileName);
                $xmlArray = $this->xmlParser->loadXML($content)->xmlToArray();
                $mainNode = array_key_first($xmlArray);
            } catch (FileSystemException $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            } catch (ValidatorException $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            } catch (LocalizedException $e) {
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            }

            $matches = [];
            preg_match_all('/schemaLocation="(urn\:magento\:[^"]*)"/i', $content, $matches);
            if (isset($matches[1])) {
                if ($onlyNode) {
                    $data[] = $mainNode;
                } else {
                    $data[] = [
                        'node' => $mainNode,
                        'urn' => $matches[1],
                        'path' => $pathInEtc
                    ];
                }
            }
        }

        $data = array_unique($data, SORT_REGULAR);

        return $data ?? null;
    }

    /**
     * Fills xml file with given data
     * @param string $file
     * @param array $content
     * @return void
     * @throws DOMException
     */
    protected function fillXmlFile($file, $content)
    {
        if (!is_array($content)) {
            $this->output->writeln(__(self::MESSAGE_FILE_FILLED_WITH_THE_CONTENT_NONE, $file));
            return;
        }

        $firstNode = array_keys($content);

        if (!isset($firstNode[0])) {
            $this->output->writeln(__(self::MESSAGE_FILE_FILLED_WITH_THE_CONTENT_NONE, $file));
            return;
        }

        // Current routes.xml content
        $currentRoutesContent = $this->xmlParser->load($file)->getDom();
        $childNodes = $currentRoutesContent->childNodes;
        $destinationNode = null;
        // XML created from the $content array
        $newDom = $this->xmlGenerator->arrayToXml($content)->getDom();
        $routerDomElement = $newDom->getElementsByTagName($firstNode[0])->item(0);

        $hasNodes = true;

        while ($hasNodes) {
            foreach ($childNodes as $node) {
                if ($this->compareNodes($node, $routerDomElement)) {
                    $routerDomElement = $routerDomElement->firstChild;
                    $destinationNode = $node;
                } else {
                    $childNodes = [];
                }

                if ($node->nodeType === XML_ELEMENT_NODE) {
                    $childNodes = $currentRoutesContent->getElementsByTagName($node->tagName)->item(0)->childNodes;
                }

                if (!count($childNodes)) {
                    $hasNodes = false;
                }
            }
        }

        if ($destinationNode !== null) {
            $destinationNode->appendChild($currentRoutesContent->importNode($routerDomElement, true));
        } else {
            $node = $currentRoutesContent->importNode($routerDomElement, true);
            $currentRoutesContent->documentElement->appendChild($node);
        }

        $currentRoutesContent->preserveWhiteSpace = false;
        $currentRoutesContent->formatOutput = true;

        // Formatting xml
        $newXmlContent = $currentRoutesContent->saveXML();
        $currentRoutesContent->loadXML($newXmlContent);

        if ($currentRoutesContent->save($file)) {
            $this->output->writeln(__(self::MESSAGE_FILE_FILLED_WITH_THE_CONTENT, $file));
        } else {
            $this->output->writeln(__(self::MESSAGE_FILE_FILLED_WITH_THE_CONTENT_ERROR, $file));
        }
    }

    /**
     * Returns function string based on provided data
     * @param string $modifier
     * @param string $functionName
     * @param string $functionArguments
     * @return string
     */
    protected function createFunctionString($modifier, $functionName, $functionArguments)
    {
        $function =  $this->getFileContents(
            self::FILES_DIR_NAME . DIRECTORY_SEPARATOR . self::FUNCTION_FILE_NAME
        );

        return $this->removeEmptyLines(str_replace(
            [
                self::FUNCTION_MODIFIER_REPLACE,
                self::FUNCTION_NAME_REPLACE,
                self::FUNCTION_ARGUMENT_REPLACE
            ],
            [
                $modifier,
                $functionName,
                $functionArguments,
            ],
            $function
        ));
    }

    /**
     * Returns data of the method in the class
     * @param string $class
     * @param string $method
     * @return null
     */
    protected function getMethodData($class, $method)
    {
        try {
            $reflectionClass = new ReflectionClass($class);
            $methodData['isPublic'] = $reflectionClass->getMethod($method)->isPublic();
            $methodData['params'] = [];

            foreach ($reflectionClass->getMethod($method)->getParameters() as $param) {
                $methodData['params'][] = [
                    'type' => $param->getType()->getName(),
                    'name' => $param->getName()
                ];
            }

            return $methodData;
        } catch (\ReflectionException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '<error>');
        }

        return null;
    }

    /**
     * Compares 2 DomDocument nodes
     * @param $firstNode
     * @param $secondNode
     * @return bool
     */
    protected function compareNodes($firstNode, $secondNode)
    {
        $firstNodeAttributes = [];
        $secondNodeAttributes = [];

        if ($firstNode->nodeName === $secondNode->nodeName) {
            foreach ($firstNode->attributes as $attribute) {
                $firstNodeAttributes[] = [
                    'name' => $attribute->name,
                    'value' => $attribute->value
                ];
            }

            foreach ($secondNode->attributes as $attribute) {
                $secondNodeAttributes[] = [
                    'name' => $attribute->name,
                    'value' => $attribute->value
                ];
            }

            if ($firstNodeAttributes === $secondNodeAttributes) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheridoc
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        if (!$input->getOption(self::MODULE_OPTION_NAME)) {
            $question = new Question('<question>Module name:</question> ', '');

            $input->setOption(
                self::MODULE_OPTION_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Check if module is correct
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);

        if (!$moduleInput) {
            $output->writeln('<error>' . __(self::MESSAGE_MODULE_MISSING) . '</error>');
            return;
        }

        $folderNames = explode('_', $moduleInput);
        $modulePath = implode(DIRECTORY_SEPARATOR, $folderNames);

        if (!$this->isModuleExist($modulePath)) {
            $output->writeln('<error>' . __(self::MESSAGE_MODULE_NOT_EXIST) . '</error>');
            return;
        }
    }

    /**
     * Returns command options array
     * @return array
     */
    abstract protected function getOptions();

    /**
     * Returns namespace and class name
     * @param string $classString
     * @return array
     */
    protected function parseClassString($classString)
    {
        $class = explode('\\', str_replace('/', '\\', $classString));
        $className = end($class);
        $classNameSpace = implode('\\', array_slice($class, 0, -1));

        return [
            'ns' => $classNameSpace,
            'className' => $className
        ];
    }

    /**
     * Creates directory
     *
     * @param string $path
     * @return void
     */
    protected function createDirectory($path)
    {
        try {
            $this->directoryWriteFactory->create($path);
            $this->output->writeln(__(self::MESSAGE_DIRECTORY_CREATED, $path));
        } catch (FileSystemException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (ValidatorException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Checks if module dir already exist
     * @param $moduleDirectory
     * @return bool
     */
    protected function isModuleExist($moduleDirectory)
    {
        try {
            return $this->directoryReadFactory->create(self::CODE_DIRECTORY . $moduleDirectory)->isExist();
        } catch (FileSystemException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (ValidatorException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Returns file content
     * @param string $filePathInModule
     * @return string
     */
    protected function getFileContents($filePathInModule)
    {
        $fileRead = $this->fileRead->create($this->getModuleDir($filePathInModule), DriverPool::FILE);
        return $fileRead->readAll();
    }

    /**
     * Write to the file
     * @param string $filePath
     * @param $data
     * @param bool $isCodeDir
     * @return void
     */
    protected function writeToFile($filePath, $data, $isCodeDir = false)
    {
        try {
            $fileCreated = $this->createFile($filePath, $isCodeDir);

            if (!$fileCreated) {
                $this->output->writeln('<error>' . __(self::MESSAGE_FILE_EXIST, $filePath) . '</error>');
            }

            if ($isCodeDir) {
                $this->fileWrite->create(self::CODE_DIRECTORY . $filePath, DriverPool::FILE, 'w')->write($data);
            } else {
                $this->fileWrite->create($filePath, DriverPool::FILE, 'w')->write($data);
            }
        } catch (FileSystemException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Creates class file with the content
     * @param string $file
     * @param string $namespace
     * @param string $use
     * @param string $className
     * @param string $parent
     * @param string $implements
     * @param string $properties
     * @param string $functions
     * @param bool $isCodeDir
     */
    protected function createClass(
        $file,
        $namespace,
        $use,
        $className,
        $parent,
        $implements,
        $properties,
        $functions,
        $isCodeDir = false
    ) {
        $controllerFileContent = $this->getFileContents(
            self::FILES_DIR_NAME . DIRECTORY_SEPARATOR . self::CLASS_FILE_NAME
        );
        $controllerFileContent = str_replace(
            [
                self::NAMESPACE_REPLACE,
                self::USE_REPLACE,
                self::CLASSNAME_REPLACE,
                self::PARENT_REPLACE,
                self::IMPLEMENTS_REPLACE,
                self::PROPERTIES_REPLACE,
                self::FUNCTIONS_REPLACE
            ],
            [
                $namespace,
                PHP_EOL . $use . PHP_EOL,
                $className,
                $parent ? ' extends ' . $parent : '',
                $implements ? ' implements ' . $implements : '',
                $this->indentEachLineOfString($properties),
                PHP_EOL . $this->indentEachLineOfString($functions) . PHP_EOL
            ],
            $controllerFileContent
        );
        $controllerFileContent = preg_replace('/^(?:[\t ]*(?:\r?\n|\r)){2,}/m', '', $controllerFileContent);
        $this->writeToFile(
            $file,
            $controllerFileContent,
            $isCodeDir
        );
    }

    /**
     * Remove empty lines from the string
     * @param string $content
     * @param string $replacement
     * @return string
     */
    protected function removeEmptyLines($content, $replacement = '')
    {
        return preg_replace('/^(?:[\t ]*(?:\r?\n|\r))+/m', $replacement, $content);
    }

    /**
     * Add tab before every new line in the string
     * @param string $content
     * @return string
     */
    protected function indentEachLineOfString($content)
    {
        //@TODO replace spaces with characters
        return preg_replace('/^/m', "    ", $content);
    }


    /**
     * Creates file if does not exist
     *
     * @param string $path
     * @param bool $isCodeDir
     * @return bool
     */
    protected function createFile($path, $isCodeDir = false)
    {
        try {
            if ($this->file->fileExists($path)) {
                $this->output->writeln('<error>' . __(self::MESSAGE_FILE_EXIST, $path) . '</error>');
                return false;
            } else {
                if ($isCodeDir) {
                    $this->directoryWriteFactory->touch(self::CODE_DIRECTORY . $path);
                } else {
                    $this->directoryWriteFactory->touch($path);
                }

                $this->output->writeln(__(self::MESSAGE_FILE_CREATED, $path));
                return true;
            }
        } catch (FileSystemException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        } catch (ValidatorException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Returns module directory path
     * @param string $folderPath
     * @param string $moduleDir
     * @return string
     */
    protected function getModuleDir($folderPath = '', $moduleDir = self::MODULE_NAME)
    {
        return $this->moduleReader->getDir($moduleDir) . DIRECTORY_SEPARATOR . $folderPath;
    }

    /**
     * Generates xml content
     * @param string $modulePath
     * @param array $array
     * @param bool $isCodeDir
     * @param string $indexedArrayItem
     * @return void
     */
    protected function generateXml($modulePath, $array, $isCodeDir = true, $indexedArrayItem = 'item')
    {
        try {
            $this->xmlGenerator->setIndexedArrayItemName($indexedArrayItem);
            $this->xmlGenerator->arrayToXml($array);

            if ($isCodeDir) {
                $this->xmlGenerator->save(self::CODE_DIRECTORY . $modulePath);
            } else {
                $this->xmlGenerator->save($modulePath);
            }
        } catch (DOMException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Capitalize first letter
     * @param $name
     * @return string
     */
    protected function firstUpper($name)
    {
        return ucfirst($name);
    }
}
