<?php

namespace ACSEO\FormFeatureGeneratorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Yaml\Parser;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * GenerateExpeditionCommand.
 *
 *
 */
class GenerateFeatureTestCommand extends ContainerAwareCommand
{
    public function __construct($name = "Feature")
    {
        parent::__construct($name);

    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output); //initialize parent class method
    }

    protected function configure()
    {
        $this
            ->setName('acseo:generate:feature')
            ->setDescription("Generate Feature")
            ->addArgument('bundle', InputArgument::REQUIRED,"Nom du bundle")
            ->addArgument('get-url', InputArgument::REQUIRED,"get url")
            ->addArgument('form-id', InputArgument::REQUIRED,"id du form")
            ->addOption('username',null ,InputOption::VALUE_OPTIONAL,"username to login")
            ->addOption("password",null ,InputOption::VALUE_OPTIONAL,"password to login")
            ->addOption("login-url",null,InputOption::VALUE_OPTIONAL,"url to login")
            ->addOption("login-form-id",null,InputOption::VALUE_OPTIONAL,"login form id")
            ->addOption('class-error',null ,InputOption::VALUE_OPTIONAL,"class error")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
// Initialize Argument/Options
        $bundle = $input->getArgument("bundle");
        $getUrl = $input->getArgument("get-url");
        $formId = $input->getArgument("form-id");
        $loginFormId = $input->getOption("login-form-id");
        $username = $input->getOption("username");
        $password = $input->getOption("password");
        $login_url = $input->getOption("login-url");
        $errorClass = $input->getOption("class-error");

        // GET path of Destination bundle
        $pathDestination = $this->getContainer()->get('kernel')->getBundle($bundle)->getPath();
        // Create Destination directory
        $output->write("<info>Checking if Features folder exist in ".$bundle."...</info>");
        $featurePath = $pathDestination.DIRECTORY_SEPARATOR."Features";
        if (!is_dir($featurePath)) {
            $output->write("<info>Creating...</info>");
            mkdir($featurePath);
        }
        $output->writeln("<info>OK</info>");
        $output->write("<info>Checking if Features/Context folder exist in ".$bundle."...</info>");
        $contextPath = $featurePath."/Context";
        if (!is_dir($contextPath)) {
            mkdir($contextPath);
        }
        $output->writeln("<info>OK</info>");
        $output->write("<info>Checking if Features/Data folder exist in ".$bundle."...</info>");
        $dataPath = $featurePath."/Data";
        if (!is_dir($dataPath)) {
            $output->write("<info>Creating...</info>");
            mkdir($dataPath);
        }
        $output->writeln("<info>OK</info>");
        // Copy file from Form Generator Bundle to Destination Bundle
        // Copy demoFeature
        $output->write("<info>Checking if features demo exist in ".$bundle."...</info>");
        $originDemoFeature = __DIR__."/../Features/demoTest.feature";
        $newDemoFeature = $featurePath."/demoTest.feature";
        if (!file_exists($newDemoFeature)) {
            $output->write("<info>Copying...</info>");
            copy($originDemoFeature, $newDemoFeature);
        }
        $output->writeln("<info>OK</info>");
        // Copy demoHeaderCSV
        $output->write("<info>Checking if features demo header exist in ".$bundle."...</info>");
        $originDemoFeature = __DIR__."/../Features/demoHeaderCSV.feature";
        $newDemoFeature = $featurePath.DIRECTORY_SEPARATOR."demoHeaderCSV.feature";
        if (!file_exists($newDemoFeature)) {
            $output->write("<info>Copying...</info>");
            copy($originDemoFeature, $newDemoFeature);
        }
        $output->writeln("<info>OK</info>");
        // Copy context Feature
        $output->write("<info>Checking if feature context exist in ".$bundle."...</info>");
        $originContextFeature = __DIR__."/../Features/Context/FeatureContext.php.dist";
        $newContextFeature = $contextPath."/FeatureContext.php";
        if (!file_exists($newContextFeature)) {
            $output->write("<info>Copying...</info>");
            copy($originContextFeature, $newContextFeature);
        }
        $output->writeln("<info>OK</info>");
        // Copy behat configuration
        $output->write("<info>Checking if behat config exist in your project...</info>");
        $originBehatConfig = __DIR__."/../Features/behat.yml.dist";
        $arrayPathDestination = explode(DIRECTORY_SEPARATOR, $pathDestination);

        while (end($arrayPathDestination) != "src") {
            array_pop($arrayPathDestination);
        }
        $srcPath = implode(DIRECTORY_SEPARATOR, $arrayPathDestination);
        $newBehatConfig = $srcPath."/../behat.yml";
        if (!file_exists($newBehatConfig)) {
            $output->write("<info>Copying...</info>");
            copy($originBehatConfig, $newBehatConfig);
        }
        $output->writeln("<info>OK</info>");
        $behatConfig = new Parser();
        $behatConfig = $behatConfig->parse(file_get_contents($newBehatConfig));
        if ($behatConfig['default']['extensions']['Behat\MinkExtension\Extension']['base_url'] == null) {
            $output->writeln("<error>You must configure the value base_url in behat.yml</error>");

            return 0;
        }
        // Ajouter le namespace dans le fichier context feature Destination
        if ( strpos(file_get_contents($newContextFeature),"namespace") === false) {
            $arrayPathDestination = explode(DIRECTORY_SEPARATOR."src".DIRECTORY_SEPARATOR, $pathDestination);
            $namespace = str_replace(DIRECTORY_SEPARATOR, "\\", end($arrayPathDestination));
            $namespace = "namespace ".$namespace."\\Features\\Context;";
            $featureContextFile = file($newContextFeature);
            $first_line = array_shift($featureContextFile);       // Remove first line and save it
            array_unshift($featureContextFile, $namespace);  // push second line
            array_unshift($featureContextFile, $first_line);
            $fp = fopen($newContextFeature, 'w');       // Reopen the file
            fwrite($fp, implode("", $featureContextFile));
            fclose($fp);
        }
        // Write CSV file in Data
        $dataFile = $featurePath.DIRECTORY_SEPARATOR."Data".DIRECTORY_SEPARATOR.str_replace("/", "_", $getUrl).".csv";
        $firstLine = array('loginUrl','loginFormIdOrClass','username','password','getUrl','formIdOrClass','submitUrl','classHasError');
        $secondLine = array($login_url,$loginFormId,$username,$password,$getUrl,$formId,"",$errorClass);
        $fp = fopen($dataFile, "w");
        fputcsv($fp, $firstLine,";");
        fputcsv($fp, $secondLine,";");
        fclose($fp);
        // execute la command pour générer le header du fichier CSV
        $behatCommand = explode(DIRECTORY_SEPARATOR."src".DIRECTORY_SEPARATOR, $featurePath);
        //var_dump($behatCommand); die();
        $behatCommand = "bin".DIRECTORY_SEPARATOR."behat src".DIRECTORY_SEPARATOR.end($behatCommand).DIRECTORY_SEPARATOR."demoHeaderCSV.feature";

        $output->writeln("<info>Launching the command ".$behatCommand." to get form inputs</info>");

        $process = new Process($behatCommand);
        $process->run();
        print $process->getOutput();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
        $output->writeln("<info>End.</info>");

        //exec($behatCommand);

    }
}
