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
    /* Comment */
    protected $kernel;
    
    /* Comment */
    private $parameters;

    /* Comment */
    private $out;

    /* Comment */
    private $importFileDir;

    /* Comment */
    private $csvFiles;

    /* Comment */
    private $keyData;

    /* Comment */
    private $pointerFile;
    
    /* Comment */
    private $fp;
    
    /* Comment */
    private $error;
    
    /* Comment */
    private $testError;
    
    /* Comment */
    protected  $dataTable;
    
    /* Comment */
    private $filename;
    
    /* Comment */
    private $rowErrors;
    
    /* Comment */
    private $visibleErrors;
    
    /* Comment */
    private $isConnected;

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
        $this->error=false;
        $this->testError = true;
        $this->rowErrors = array();
        $this->visibleErrors = array();
        $this->isConnected = false;
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
            $this->initKeyDataFromCSVFile($file);
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
            $this->initKeyDataFromCSVFile($file);
            $this->writeHeaderInCSVFile($file);
            fclose($this->fp);
        }
    }

    /**
     * @Then /^I should check result$/
     */
    public function iShouldCheckResult()
    {
        if (count($this->rowErrors) > 0 || count($this->visibleErrors) > 0 ) {
            throw new ExpectationException("Errors in lines: ".implode(",", $this->rowErrors)."\n\nError visible input :\n\t".implode("\n\t", $this->visibleErrors).".", $this->getSession());
        }
    }

    /**
     * Description
     */
    private function initKeyDataFromCSVFile($file)
    {
        $data = fgetcsv($this->fp);
        $this->keyData = explode(";", $data[0]);
        $this->pointerFile = ftell($this->fp);
    }

    /**
     * Description
     */
    private function writeHeaderInCSVFile($file)
    {
        $data = fgetcsv($this->fp);
        $this->combineKeyAndData($this->keyData, $data[0]);
        if ($this->dataTable['loginUrl'] != "" && $this->dataTable['username'] != "" && $this->dataTable['password'] != "") {
            $this->clientConnect();
        }
        $this->visit($this->dataTable['getUrl']);
        $this->exportCSVFileWithHeader($file,false);
    }

    /**
     * Description
     */
    private function verifyCSVFile($file)
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
            $form = $this->getForm();
            $this->fillForm($form);
            $this->pressSubmitButton($form);

            // TODO: check if we can delete the wait instruction
            $this->getSession()->wait(10000);
            if ($this->error == true) {
                $this->exportCSVFileWithHeader($file,true);
                $this->out->writeln("<error>Error in CSV file first line.</error>");
                $this->out->writeln("<info>Backup original file and generate a new file called ".$this->filename." with the right input name.</info>");
                break;
            }
            if ($this->dataTable['submitUrl']!="") {
                if ($this->getSession()->getCurrentUrl()!= $this->dataTable['submitUrl']) {
                    $this->out->writeln("<error>Error in submit url ( the current submit url is: ".$this->getSession()->getCurrentUrl().")</error>");
                    $this->testError = false;
                }
            }

            // TODO: create a specific method for this test
            $classHasError = array_key_exists('classHasError', $this->dataTable) && $this->dataTable['classHasError']!="" ? $this->dataTable['classHasError'] : null;
            if ($classHasError != null) {
                $errors = $this->getSession()->getPage()->findAll("css", ".".$classHasError);
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
                    $this->out->writeln("Message error of the class ".$classHasError.":");
                    foreach ($errors as $error) {
                        $this->out->writeln("<error>".$error->getText()."</error>"."\n");
                    }
                }
            }
            if ($this->testError == false)
                array_push($this->rowErrors, $row);
        }
        $this->visit("/");
    }

    protected function combineKeyAndData($key,$data)
    {
        $dataTable = explode(";", $data);
        $this->dataTable = array_combine($this->keyData, $dataTable);
    }

    protected function clientConnect()
    {
        $this->visit($this->dataTable['loginUrl']);
        $loginUrl = $this->getSession()->getCurrentUrl();
        $buttonName = $this->fillLoginFormAndPressButton();
        $loggedIn = true;
        if ($loginUrl === $this->getSession()->getCurrentUrl()) {
            $loggedIn=false;
        }
        return $loggedIn;
    }

    /**
     * Description
     */
    protected function fillLoginFormAndPressButton()
    {
        $form = null;
        if ($this->dataTable['loginFormIdOrClass'] != "") {
            $form = $this->getSession()->getPage()->find("css", "form#".$this->dataTable['loginFormIdOrClass']);
            if ($form == NULL)
                $form = $this->getSession()->getPage()->find("css", "form.".$this->dataTable['loginFormIdOrClass']);
        } else {
            $form = $this->getSession()->getPage()->find("css", "form");
        }
        if ($form == null) {
            $this->out->writeln("<error>login form not found</error>");

            return 0;
        }
        $inputs = $form->findAll("css", "input");
        $arrayTypeNotExist = array('submit', 'hidden');
        $submitButton="";
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

        if ($submitButton=="") {
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
     * Description
     */
    protected function getForm(){
        // TODO : factorize form selection in one method
        $form = null;
        if ($this->dataTable['formIdOrClass'] != "") {
            $form = $this->getSession()->getPage()->find("css", "form#".$this->dataTable['formIdOrClass']);
            if ($form == NULL)
                $form = $this->getSession()->getPage()->find("css", "form.".$this->dataTable['formIdOrClass']);
        } else {
            $form = $this->getSession()->getPage()->find("css", "form");
        }
        if ($form == null) {
            $this->out->writeln("<error>Form not found.</error>");
        
            return 0;
        }
        return $form;
    }


    public function fillAcseomedecin()
    {
        $input->setValue( $this->dataTable[$name]);
        $this->getSession()->wait(15000);
    }
    /**
     * Description
     */
    protected function fillForm($form)
    {
        $inputs = $form->findAll("css", "input");
        $arrayTypeNotExist = array('submit', 'hidden');
        
        // TODO : initilize with null value
        $submitButton="";

        foreach ($inputs as $input) {
            $name = $input->getAttribute("name");
            $type = strtolower($input->getAttribute("type"));
            $nameTemp = $name;
            // TODO : rewrite logic
            //if ($name == null) {
            //    $name = $input->getAttribute("id");
            //}
            if ($name !=NULL) {
                if (!array_key_exists($nameTemp, $this->dataTable) && $nameTemp != "" && !in_array($type, $arrayTypeNotExist)) {
                    preg_match_all('/\[([^\]]+)\]/', $nameTemp, $nameTemp); //recuperer juste le nom du input name (ex. acseo_form_enfant[nom] et on recupere nom)
                    $nameTemp = end($nameTemp[1]);
                    if (!array_key_exists($nameTemp, $this->dataTable)) {
                        $this->error = true;
                    }
                }
                if (!$this->error && !in_array($type, $arrayTypeNotExist)) {
                    /*
                    // TODO: implement a specific function call for a given input
                    // http://www.php.net/manual/fr/function.method-exists.php
                    if (method_exists($this, "fill".^ucfirst($name)) {
                            call_user_func_array(array($this, "fill".^ucfirst($name)), $this->dataTable[$name]); 
                    }
                    */
                    else  if ($input->isVisible()) {
                        // TODO: create a specific fill method for each input : fillText(), fillPassword, etc.
                        if ($type == "text") {
                            $input->setValue( $this->dataTable[$name]);
                        } elseif ($type =="password") {
                            $this->fillField($name, $this->dataTable[$name]);
                        } elseif ($type =="email") {
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
                    } else {
                        if ($this->dataTable[$name]!="") {
                            array_push($this->visibleErrors, $name);
                            $this->testError = false;
                        }
                    }
                }
            } else {
                // TODO : delete this specific part of code and implement 
                $id = $input->getAttribute("id");
                if (array_key_exists($id, $this->dataTable) && $this->dataTable[$id] != "") {
                    $this->fillField($id,$this->dataTable[$id]);
                    $this->getSession()->wait(3000);
                    $this->getSession()->getPage()->find("css",".token-input-selected-dropdown-item-bootstrap")->click();
                }
            }
        }
        $selects = $form->findAll("css", "select");
        foreach ($selects as $select) {
            $name = $select->getAttribute("name");
            $nameTemp = $name;
            // TODO: create method cleanFieldName()
            if (!array_key_exists($nameTemp, $this->dataTable) && $nameTemp != "") {
                preg_match_all('/\[([^\]]+)\]/', $nameTemp, $nameTemp); //recuperer juste le nom du input name (ex. acseo_form_enfant[nom] et on recupere nom)
                $nameTemp = end($nameTemp[1]);
                if (!array_key_exists($nameTemp, $this->dataTable)) {
                    $this->error = true;
                }
            }
            /*
                    // TODO: implement a specific function call for a given input
                    // http://www.php.net/manual/fr/function.method-exists.php
                    if (method_exists($this, "fill".^ucfirst($name)) {
                            call_user_func_array(array($this, "fill".^ucfirst($name)), $this->dataTable[$name]); 
                    }
                    */
            if (!$this->error && $this->dataTable[$name]!="") {
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
        $textareas = $form->findAll("css", "textarea");
        foreach ($textareas as $textarea) {
            $name = $textarea->getAttribute("name");
            $nameTemp = $name;
            // TODO: use method cleanFieldName()
            if (!array_key_exists($nameTemp, $this->dataTable) && $nameTemp != "") {
                preg_match_all('/\[([^\]]+)\]/', $nameTemp, $nameTemp); //recuperer juste le nom du input name (ex. acseo_form_enfant[nom] et on recupere nom)
                $nameTemp = end($nameTemp[1]);
                if (!array_key_exists($nameTemp, $this->dataTable)) {
                    $this->error = true;
                }
            }
            if (!$this->error && $this->dataTable[$name]!="") {
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
    protected function pressSubmitButton($form){
        $form->find("css", "[type='submit']")->press();
    }

    protected function getListInputName($backupCopy)
    {
        $form = null;
        //TODO: use getForm()
        if ($this->dataTable['formIdOrClass'] != "") {
            $form = $this->getSession()->getPage()->find("css", "form#".$this->dataTable['formIdOrClass']);
            if ($form == NULL)
                $form = $this->getSession()->getPage()->find("css", "form.".$this->dataTable['formIdOrClass']);
        } else {
            $form = $this->getSession()->getPage()->find("css", "form");
        }
        if ($form == null) {
            $this->out->writeln("<error>submit form not found</error>");

            return 0;
        }
        $arrayTypeNotExist = array('submit', 'hidden');
        $listAllInputs = array();
        $inputs = $form->findAll("css", "input");
        foreach ($inputs as $input) {
            $name = $input->getAttribute("name");
            $type = strtolower($input->getAttribute("type"));
                if (!in_array($type, $arrayTypeNotExist))
                    array_push($listAllInputs, $name);
        }
        $selects = $form->findAll("css", "select");
        foreach ($selects as $select) {
            $name = $select->getAttribute("name");
            array_push($listAllInputs, $name);
        }
        $textareas = $form->findAll("css", "textarea");
        foreach ($textareas as $textarea) {
            $name = $textarea->getAttribute("name");
            array_push($listAllInputs, $name);
        }

        return $listAllInputs;
    }

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
        
        for($i=count($data);$i<count($listofAllInput);$i++) {
            array_push($data, "");
        }
        
        fputcsv($writeFile, $listofAllInput,";");
        fputcsv($writeFile, $data,";");
        fclose($writeFile);
    }
}
