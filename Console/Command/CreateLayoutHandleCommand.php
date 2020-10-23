<?php
namespace Mistlanto\ModuleManager\Console\Command;

use DOMException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateLayoutHandleCommand extends AbstractModuleCommand
{
    const HANDLE_SCHEMA_LOCATION = 'urn:magento:framework:View/Layout/etc/page_configuration.xsd';

    /**
     * Command option params
     */
    const HANDLE_NAME = 'handle-name';
    const HANDLE_AREA = 'handle-area';
    const HANDLE_FILE_CONTENT = 'handle-content';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_HANDLE_NAME = 'Invalid handle name!';
    const MESSAGE_INVALID_HANDLE_AREA = 'Invalid handle area!';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:handle:create')
            ->setDescription('Creates a page layout handle');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::HANDLE_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'Layout Handle file name'
            ),
            new InputOption(
                self::HANDLE_AREA,
                null,
                InputOption::VALUE_REQUIRED,
                'Layout Handle file area'
            ),
            new InputOption(
                self::HANDLE_FILE_CONTENT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Layout Handle file content array'
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
        $handleAreas = array_merge(self::AREAS, [self::BASE_AREA]);

        if (!$input->getOption(self::HANDLE_AREA)) {
            $question = new Question(
                '<question>Handle area: (' . implode(',', $handleAreas) . ') (Default: frontend)</question> ',
                'frontend'
            );

            $input->setOption(
                self::HANDLE_AREA,
                $questionHelper->ask($input, $output, $question)
            );
        }

        if (!$input->getOption(self::HANDLE_NAME)) {
            $question = new Question('<question>Handle Name:</question> ');

            $input->setOption(
                self::HANDLE_NAME,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Command for layout handle creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws DOMException
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $handleName = $input->getOption(self::HANDLE_NAME);
        $handleArea = $input->getOption(self::HANDLE_AREA);
        $content = $input->getOption(self::HANDLE_FILE_CONTENT);
        $handleAreas = array_merge(self::AREAS, [self::BASE_AREA]);

        if (!$handleName) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_HANDLE_NAME) . '</error>');
            return;
        }

        if (!$handleArea || !in_array($handleArea, $handleAreas)) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_HANDLE_AREA) . '</error>');
            return;
        }

        $this->createHandle($moduleInput, $handleName, $handleArea, $content);
    }

    /**
     * Creates layout handle xml file in the module's view folder
     * @param string $moduleInput
     * @param string $handleName
     * @param string $handleArea
     * @param array $content
     * @throws DOMException
     * @return void
     */
    protected function createHandle($moduleInput, $handleName, $handleArea, $content)
    {
        $moduleDir = $this->getModuleDir('view' . DIRECTORY_SEPARATOR . $handleArea, $moduleInput);
        $filePath = $moduleDir . DIRECTORY_SEPARATOR . $handleName . '.xml';

        $this->generateXml(
            $filePath,
            ['page' => [
                '_attribute' => [
                    self::MAIN_XML_ATTRIBUTE_NAME => self::MAIN_XML_ATTRIBUTE_VALUE,
                    self::MODULE_XML_SCHEMA_ATTRIBUTE => self::HANDLE_SCHEMA_LOCATION,
                ],
                '_value' => null,
            ]],
            false
        );
        $this->fillXmlFile($filePath, $content);
    }
}
