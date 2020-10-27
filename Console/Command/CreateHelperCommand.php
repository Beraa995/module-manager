<?php
namespace Mistlanto\ModuleManager\Console\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CreateHelperCommand extends AbstractModuleCommand
{
    const ABSTRACT_HELPER_FULL = 'Magento\\Framework\\App\\Helper\\AbstractHelper';
    const ABSTRACT_HELPER_SHORT = 'AbstractHelper';

    /**
     * Command option params
     */
    const HELPER_CLASS = 'helper-class';

    /**
     * Command messages
     */
    const MESSAGE_INVALID_HELPER_CLASS = 'Invalid helper class!';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mistlanto:helper:create')
            ->setDescription('Creates a helper');

        parent::configure();
    }

    /**
     * @inheridoc
     */
    protected function getOptions()
    {
        return [
            new InputOption(
                self::HELPER_CLASS,
                null,
                InputOption::VALUE_REQUIRED,
                'Helper class.'
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

        if (!$input->getOption(self::HELPER_CLASS)) {
            $question = new Question(
                '<question>Helper class in Helper folder: (default: Data)</question> ',
                'Data'
            );

            $input->setOption(
                self::HELPER_CLASS,
                $questionHelper->ask($input, $output, $question)
            );
        }
    }

    /**
     * Command for helper creation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $moduleInput = $input->getOption(self::MODULE_OPTION_NAME);
        $helperClass = $input->getOption(self::HELPER_CLASS);

        if (!$helperClass) {
            $output->writeln('<error>' . __(self::MESSAGE_INVALID_HELPER_CLASS) . '</error>');
            return;
        }

        $this->createHelperClass($moduleInput, $helperClass);
    }

    /**
     * Creates a helper class
     * @param string $moduleInput
     * @param string $helperClass
     */
    protected function createHelperClass($moduleInput, $helperClass)
    {
        $moduleDirInCode = $this->getModuleDirInCode(
            $this->getModuleDir('Helper', $moduleInput)
        );
        $helperClass = $moduleDirInCode . DIRECTORY_SEPARATOR . trim(
            str_replace('\\', '/', $helperClass),
            '/'
        );
        $classSplit = $this->parseClassString($helperClass);

        $this->createClass(
            $helperClass . '.php',
            $classSplit['ns'] ?? '',
            ['use ' . self::ABSTRACT_HELPER_FULL . ';'],
            $classSplit['className'] ?? '',
            self::ABSTRACT_HELPER_SHORT,
            '',
            '',
            '',
            true
        );
    }
}
