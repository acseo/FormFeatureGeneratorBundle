ACSEOFormFeatureGeneratorBundle
-------------------------------

A bundle use Behat and Mink to test your form from CSV Files
#Installation using Composer
Add the bundle in your composer.json
```js
{
    "require": {
        "acseo/formfeaturegenerator-bundle": "dev-master"
    }
}
```
Download bundle by running the command:
``` bash
$ php composer.phar update acseo/formfeaturegenerator-bundle
```
Composer will install the bundle in `vendor/ACSEO` directory.

#How to use
We used selenium in our code, so you have to download [Selenium server](http://docs.seleniumhq.org/download/).

Update your app/AppKernel.php

```bash
new ACSEO\FormFeatureGeneratorBundle\ACSEOFormFeatureGeneratorBundle()
```

then, you have to execute the comamnd below. This command will create files in your Bundle/Features directory.

``` bash
$ php app/console acseo:generate:feature YourBundleName GetUrl FormId --username=USERNAME --password=PASWORD --login-url=LOGINURL --login-form-id=LOGINFORMID --class-error=CLASSERROR 
```
####Required Argument
```bash
YourBundleName: The name of your Bundle (ex. AcmeDemoBundle)
GetUrl: the url contain the form to test (ex: /product/new)
FormId: id/class of your form in the page of GetUrl
```

####Optional
```bash
USERNAME: username to login (if you have to login before test your form)
PASSWORD: idem username
LOGINURL: url to login
LOGINFORMID: id/class of your form to login
CLASSERROR: Class of your message error.
```
The command will create **behat.yml** in your project directory. You have to put a value for **base_url**.

####Overload function

You can overload function by create your own function in your feature class

For example overload the function clientConnect

```bash
// YourBundle/Features/Context/FeatureContext
protected function clientConnect ()
{
    parent::clientConnect();
    // Your code
}
```
####Use entity manager

Also you can execute query from your feature.

Example:
```bash
// YourBundle/Features/Context/FeatureContext
//.....
protected function YourFunctionName()
{
    $doctrine = $this->kernel->getContainer()->get('doctrine');
    $em = $doctrine->getManager();
    $em->clear();
    $entity = $em->getRepository('YourBundleName:Entity')->find(1);
    // manage your entity
}
//.....
```
#Test your form
In `YourBundleName/Features/Data/` there is a CSV file created by the command `acseo:generate:feature ...`

You have to fill the CSV file by value and execute the command below to test your form
``` bash
$ bin/behat src/YourBundleName/Features/demoTest.feature
```

You can create your own feature in `/Features` directory.
