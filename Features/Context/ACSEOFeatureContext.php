<?php

namespace ACSEO\FormFeatureGeneratorBundle\Features\Context;

use Symfony\Component\HttpKernel\KernelInterface;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Exception\ExpectationException;
use Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Feature context.
 * @Author Safwan Ghamrawi <safwan.ghamrawi@acseo-conseil.fr>
 */
class ACSEOFeatureContext extends MinkContext
                  implements KernelAwareInterface
{
    /**
     * TODO: Remove unecessary Data
     */
    /* Kernel by Mink */
    protected $kernel;

    /* Parameters by Mink */
    protected $parameters;

    /* output console to write on console */
    protected $out;

    /* The directory of import CSV files */
    protected $importFileDir;

    /* List of CSV files */
    protected $csvFiles;

    /* The file open */
    protected $fp;

    /* Error in header */
    protected $headerCSVError;

    /* Error in the test  */
    protected $testError;

    /* All data of table */
    protected $dataTable;

    /* CSV file name */
    protected $filename;

    /* Array of rows numbers has error */
    protected $rowErrors;

    /* Array of name input if has value and invisible */
    protected $visibleErrors;

    /* Is user connected */
    protected $isConnected;

    /* Type of input */
    protected $arrayTypeNotExist;

    /**
     * Initializes context with parameters from behat.yml.
     *
     * @param array $parameters
     */
    public function __construct(array $parameters,$csvDir)
    {
        $this->parameters = $parameters;
        $this->out = new ConsoleOutput();
        $this->importFileDir = $csvDir."/../Data/";
        $this->headerCSVError = false;
        $this->testError = true;
        $this->rowErrors = array();
        $this->visibleErrors = array();
        $this->isConnected = false;
        $this->arrayTypeNotExist = array('submit', 'hidden');
    }

    /**
     * Sets HttpKernel instance.
     * This method will be automatically called by Symfony2Extension ContextInitializer.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Given /^I get csv files$/
     */
    public function iGetCSVFiles()
    {
        $this->csvFiles = $this->importFileDir."*.csv";
    }

    /**
     * @When /^I verify data$/
     */
    public function iVerifyData()
    {
        foreach (glob($this->csvFiles) as $file) {
            $this->fp = fopen($file, 'r');
            $filename = explode("/", $file);
            $this->filename = end($filename);
            $this->out->writeln("Reading the file: ".$this->filename."");
            $this->initKeyDataFromCSVFile();
            $this->verifyCSVFile($file);
            fclose($this->fp);
        }
    }

    /**
     * @When /^I create header$/
     */
    public function iCreateHeader()
    {
        foreach (glob($this->csvFiles) as $file) {
            $this->fp = fopen($file, 'r');
            $filename = explode("/", $file);
            $this->filename = end($filename);
            $this->out->writeln("Reading the file: ".$this->filename."");
            $this->initKeyDataFromCSVFile();
            $this->writeHeaderInCSVFile($file);
            fclose($this->fp);
        }
    }

    /**
     * @Then /^I should check result$/
     */
    public function iShouldCheckResult()
    {
        $throw = false;
        $message = "";

        if (count($this->rowErrors) > 0) {
            $message = $message."Errors in lines: ".implode(",", $this->rowErrors);
            $throw = true;
        }

        if (count($this->visibleErrors) > 0) {
            $message = $message."\n\nError visible input :\n\t".implode("\n\t", $this->visibleErrors);
            $throw =true;
        }
        $message = $message.".";

        if ($throw) {
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * Initialise Key for Data Table
     */
    protected function initKeyDataFromCSVFile()
    {
        $data = fgetcsv($this->fp);
        $this->keyData = explode(";", $data[0]);
    }

    /**
     * Go to getUrl page from CSV file
     * get all input of form
     * create the CSV file with all input in form
     */
    protected function writeHeaderInCSVFile($file)
    {
        $data = fgetcsv($this->fp);
        $this->combineKeyAndData($this->keyData, $data[0]);

        if ($this->dataTable['loginUrl'] != "" && $this->dataTable['username'] != "" && $this->dataTable['password'] != "") {
            $boolLoggedIn = $this->clientConnect();
        }
        $this->visit($this->dataTable['getUrl']);
        $this->exportCSVFileWithHeader($file,false);
    }

    /**
     * Verify form from CSV file
     */
    protected function verifyCSVFile($file)
    {
        $row = 0;
        while (($data = fgetcsv($this->fp)) !== FALSE) {
            $this->testError = true;
            $row++;
            $this->out->writeln("<comment>Testing line: ".$row."</comment>");
            // Get the data row and merge the keyData as key for dataTable
            $this->combineKeyAndData($this->keyData, $data[0]);
            // Create a new client to browse the application
            // TODO: Delete the session to reconnect on each row

            if ($this->dataTable['loginUrl']!="" && $this->isConnected == false) {
                $boolLoggedIn = $this->clientConnect();

                if ($boolLoggedIn == false && $boolLoggedIn != null) {
                    $this->out->writeln("<error>Authentification refused</error>");
                    continue;
                } else {
                    $this->isConnected = true;
                }
            }
            $this->visit($this->dataTable['getUrl']);
            $form = $this->getForm($this->dataTable['formIdOrClass']);
            $this->fillForm($form);
            $this->pressSubmitButton($form);

            if ($this->headerCSVError == true) {
                $this->exportCSVFileWithHeader($file,true);
                $this->out->writeln("<error>Error in CSV file first line.</error>");
                $this->out->writeln("<info>Backup original file and generate a new file called ".$this->filename." with the right input name.</info>");
                break;
            }
            $this->verifySubmitUrl();

            $classErrorName = array_key_exists('classHasError', $this->dataTable) && $this->dataTable['classHasError']!="" ? $this->dataTable['classHasError'] : null;
            $this->verifyErrorMessage($classErrorName);

            if ($this->testError == false) {
                array_push($this->rowErrors, $row);
            }
            $this->getSession()->wait(1000);
//             $this->getSession()->reset();
//             $this->getSession()->stop();
            $this->getSession()->wait(1000);
        }
    }

    /**
     * After submit, Compare current url and the submit url in CSV file.
     */
    protected function verifySubmitUrl()
    {
        if ($this->dataTable['submitUrl']!="") {

            if ($this->getSession()->getCurrentUrl()!= $this->dataTable['submitUrl']) {
                $this->out->writeln("<error>Error in submit url ( the current submit url is: ".$this->getSession()->getCurrentUrl().")</error>");
                $this->testError = false;
            }
        }
    }

    /**
     * Search all error message by the class name
     */
    protected function verifyErrorMessage($classErrorName)
    {
        if ($classErrorName != null) {
            $errors = $this->getSession()->getPage()->findAll("css", ".".$classErrorName);
            $formOK = count($errors) > 0 ? "false" : "true";

            if ($formOK == strtolower(end($this->dataTable)) && $formOK == "true") {
                $this->out->writeln("<bg=green;fg=black>The result is ".$formOK." and the result in CSV file is ".end($this->dataTable)."</bg=green;fg=black>");
            } elseif ($formOK == strtolower(end($this->dataTable)) && $formOK == "false") {
                $this->out->writeln("<bg=yellow;fg=black>The result is ".$formOK." and the result in CSV file is ".end($this->dataTable)."</bg=yellow;fg=black>");
            } else {
                $this->testError = false;
                $this->out->writeln("<error>The result is ".$formOK." and the result in CSV file is ".end($this->dataTable)."</error>");
            }

            if (count($errors)>0) {
                $this->out->writeln("Message error of the class ".$classErrorName.":");

                foreach ($errors as $error) {
                    $this->out->writeln("<error>".$error->getText()."</error>"."\n");
                }
            }
        }
    }

    /**
     * Create data Table by combine key and data
     */
    protected function combineKeyAndData($key,$data)
    {
        $dataTable = explode(";", $data);
        $this->dataTable = array_combine($this->keyData, $dataTable);
    }

    /**
     * Go to Login url and fill the form and submit
     */
    protected function clientConnect()
    {
        $this->visit($this->dataTable['loginUrl']);
        $loginUrl = $this->getSession()->getCurrentUrl();
        $this->fillLoginFormAndPressButton();
        $loggedIn = true;
        if ($loginUrl === $this->getSession()->getCurrentUrl()) {
            $loggedIn=false;
        }

        return $loggedIn;
    }

    /**
     * Fill form and press button
     */
    protected function fillLoginFormAndPressButton()
    {
        $form = $this->getForm($this->dataTable['loginFormIdOrClass']);

        $inputs = $form->findAll("css", "input");
        $submitButton = null;

        foreach ($inputs as $input) {
            $name = $input->getAttribute("name");
            $type = strtolower($input->getAttribute("type"));

            if (preg_match("/(username|email)/i", $name) || $type == "email") {
                $this->fillField($name, $this->dataTable['username']);
            }

            if (strpos($name, 'password')!==false && $type == "password") {
                $this->fillField($name, $this->dataTable['password']);
            }

            if ($type == "submit") {
                $submitButton = $input;
            }
        }

        if ($submitButton == null) {
            $buttons = $this->getSession()->getPage()->findAll("css", "button");
            foreach ($buttons as $button) {
                $type = strtolower($button->getAttribute("type"));

                if ($type == "submit") {
                    $submitButton = $button;
                }
            }
        }
        $submitButton->press();
    }

    /**
     * Get form by ID or Class name
     */
    protected function getForm($formIdClass)
    {
        $form = null;

        if ($formIdClass != "") {
            $form = $this->getSession()->getPage()->find("css", "form#".$formIdClass);
            if ($form == NULL)
                $form = $this->getSession()->getPage()->find("css", "form.".$formIdClass);
        } else {
            $form = $this->getSession()->getPage()->find("css", "form");
        }

        if ($form == null) {
            $this->out->writeln("<error>Form not found.</error>");

            return 0;
        }

        return $form;
    }

    /**
     * Fill the form
     */
    protected function fillForm($form)
    {
        $this->fillAllInputInForm($form);
        $this->fillAllSelectInForm($form);
        $this->fillAllTextareaInForm($form);
    }

    /**
     * Fill all input in form $form
     */
    protected function fillAllInputInForm($form)
    {
        $inputs = $form->findAll("css", "input");
        $arrayTypeText = array("text","password","email","number","date","datetime","tel","time","url");
        foreach ($inputs as $input) {
            $fillById = false;
            $name = $input->getAttribute("name");
            $type = strtolower($input->getAttribute("type"));

            if ($name == NULL) {
                $name = $input->getAttribute("id");
                $fillById = true;
            }

            if (!in_array($type, $this->arrayTypeNotExist) && !$fillById) {
                $this->isNameExistInCSVFile($name);
            }

            if (!$this->headerCSVError && !in_array($type, $this->arrayTypeNotExist)) {
                $filterName = $this->filterNameFunction($name);

                // Call to user methode fillName, (Name is the name of the input by removing all special characters and capitalize the lettre afeter each special character )
                if (method_exists($this, "fill".ucfirst($filterName))) {
                    call_user_func_array(array($this, "fill".ucfirst($filterName)), array($this->dataTable[$name]));
                    continue;
                }

                if ($input->isVisible()) {
                    if (in_array($type, $arrayTypeText)) {
                        $this->fillField($name, $this->dataTable[$name]);
                    } elseif ($type == "radio") {
                        if ($input->getAttribute("value") == $this->dataTable[$name])
                            $input->check();
                    } elseif ($type == "checkbox") {
                        if ($this->dataTable[$name]== 1)
                            $this->checkOption($name);
                    } elseif ($type == "file") {
                        $input->attachFile($this->dataTable[$name]);
                    }
//                 } else {
//                     if (in_array($name, $this->dataTable) && $this->dataTable[$name]!="") {
//                         array_push($this->visibleErrors, $name);
//                         $this->testError = false;
//                     }
                }

                if ($fillById && array_key_exists($name, $this->dataTable) && $this->dataTable[$name] != "") {
                    $this->getSession()->wait(3000);
                    $this->getSession()->getPage()->find("css",".token-input-selected-dropdown-item-bootstrap")->click();
                }
            }
        }
    }

    /**
     * Fill all Select in form
     */
    protected function fillAllSelectInForm($form)
    {
        $selects = $form->findAll("css", "select");

        foreach ($selects as $select) {
            $name = $select->getAttribute("name");
            $this->isNameExistInCSVFile($name);

            $filterName = $this->filterNameFunction($name);

            if (method_exists($this, "fill".ucfirst($filterName))) {
                call_user_func_array(array($this, "fill".ucfirst($filterName)), array($this->dataTable[$name]));
                continue;
            }

            if (!$this->headerCSVError && $this->dataTable[$name]!="") {

                if ($select->isVisible()) {
                    $this->selectOption($name, $this->dataTable[$name]);
                } else {

                    if ($this->dataTable[$name]!="") {
                        array_push($this->visibleErrors, $name);
                        $this->testError = false;
                    }
                }
            }
        }
    }

    /**
     * Fill all Textarea in form
     */
    protected function fillAllTextareaInForm($form)
    {
        $textareas = $form->findAll("css", "textarea");
        foreach ($textareas as $textarea) {
            $name = $textarea->getAttribute("name");
            $this->isNameExistInCSVFile($name);

            $filterName = $this->filterNameFunction($name);

            if (method_exists($this, "fill".ucfirst($filterName))) {
                call_user_func_array(array($this, "fill".ucfirst($filterName)), array($this->dataTable[$name]));
                continue;
            }

            if (!$this->headerCSVError && $this->dataTable[$name]!="") {

                if ($textarea->isVisible()) {
                    $this->fillField($name, $this->dataTable[$name]);
                } else {

                    if ($this->dataTable[$name] != "") {
                        array_push($this->visibleErrors, $name);
                        $this->testError = false;
                    }
                }
            }
        }
    }

    /**
     * Filter the name given by removing sepcial characters and upper the letter after.
     */
    protected function filterNameFunction($name)
    {
        $ptn = "/_[a-z]?/";
        $name = preg_replace('/\(|\[|\]|\(|-\)/','_',$name);
        $result = preg_replace_callback(
                $ptn,
                function ($matches) { return strtoupper(ltrim($matches[0], "_"));},
                $name
            );

        return $result;
    }

    /**
     * Press the submit button in form
     */
    protected function pressSubmitButton($form)
    {
        $form->find("css", "[type='submit']")->press();
    }

    /**
     * Verify if name exist in CSV file
     */
    protected function isNameExistInCSVFile($name)
    {
        $nameTemp = $name;

        if ($nameTemp != "" && !array_key_exists($nameTemp, $this->dataTable)) {
            $this->headerCSVError = true;

            return false;
        }

        return true;
    }

    /**
     * Get the list of all field
     */
    protected function getListInputName($backupCopy)
    {
        $form = $this->getForm($this->dataTable['formIdOrClass']);

        $listAllInputs = array();
        $inputs = $form->findAll("css", "input");
        foreach ($inputs as $input) {
            $name = $input->getAttribute("name");

            if ($name == NULL) {
                $name = $input->getAttribute("id");
            }
            $type = strtolower($input->getAttribute("type"));
                if (!in_array($type, $this->arrayTypeNotExist))
                    array_push($listAllInputs, $name);
        }
        $selects = $form->findAll("css", "select");
        foreach ($selects as $select) {
            $name = $select->getAttribute("name");

            if ($name == NULL) {
                $name = $select->getAttribute("id");
            }
            array_push($listAllInputs, $name);
        }
        $textareas = $form->findAll("css", "textarea");
        foreach ($textareas as $textarea) {
            $name = $textarea->getAttribute("name");

            if ($name == NULL) {
                $name = $textarea->getAttribute("id");
            }
            array_push($listAllInputs, $name);
        }

        return $listAllInputs;
    }

    /**
     * Create the CSV File in Data Dir with all field
     */
    protected function exportCSVFileWithHeader($file, $bakupCopy)
    {
        $listofAllInput = $this->getListInputName($bakupCopy);
        $listofAllInput = array_filter($listofAllInput);

        for ($i = 7; $i>=0; $i--) {
            array_unshift($listofAllInput, $this->keyData[$i]);
        }

        array_push($listofAllInput, "result");
        $pathFile = $this->importFileDir.$this->filename;

        if ($bakupCopy) {
            copy($pathFile, $pathFile.'.bak');
        }

        $writeFile = fopen($pathFile, "w");
        $data = array_slice($this->dataTable, 0,8);

        for ($i=count($data);$i<count($listofAllInput);$i++) {
            array_push($data, "");
        }

        fputcsv($writeFile, $listofAllInput,";");
        fputcsv($writeFile, $data,";");
        fclose($writeFile);
    }
}
