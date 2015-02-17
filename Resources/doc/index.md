Getting Started With PhpSandboxBundle
=====================================

With this bundle you can run PHP Code in a sandbox or in your current environment.
Otherwise you can use it for multi-tasking purposes running child processes in background.

[![Build Status](https://secure.travis-ci.org/fracasula/PhpSandboxBundle.png)](http://travis-ci.org/fracasula/PhpSandboxBundle)

## Prerequisites

This bundle requires Symfony 2.1+.


## Installation


### Step 1: Download PhpSandboxBundle using the composer

Add PhpSandboxBundle in your composer.json:

```js
"require": {
	"fracasula/phpsandboxbundle": "dev-master"
}
```

Now tell composer to download the bundle by running the command:

```bash
$ php composer.phar update fracasula/phpsandboxbundle
```

Composer will install the bundle to your project `vendor/fracasula` directory.

### Step 2: Enable the bundle

Enable the bundle in your kernel:

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
	$bundles = array(
		// ...
		new FraCasula\Bundle\PhpSandboxBundle\FraCasulaPhpSandboxBundle(),
	);
}
```

## Examples

### Run PHP Code in the current environment

Note: sharing functions, classes and propagating errors/exceptions (like eval)

```php
<?php

class Test
{
	public $x;
}

// ... inside a controller

$sandbox = $this->container->get('fra_casula_php_sandbox');
$result = $sandbox->run('$test = new Test(); $text->x = 5; echo $test->x;');

echo $result; // will output 5

// or...

$result = $sandbox->run('echo intval($_SANDBOX["arg1"]) * 2;', array('arg1' => '10'));

echo $result; // 20
```

### Run PHP Code in a separate sandbox

Note: without class/functions sharing and without errors propagating

The code is executed in a separated process

```php
<?php

$variables = array('arg1' => '3');

$result = $sandbox->runStandalone('echo intval($_SERVER["arg1"]) * 2;', $variables);

echo $result; // 6
```

Another example:

```php
<?php

use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxNotice;
use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxWarning;
use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxError;

// ...

try
{
	$sandbox->runStandalone('$arr = array(1, 2, 3); echo $arr[100];');
}
catch (PhpSandboxNotice $e)
{
	// this will print:
	// [NOTICE OCCURRED] PHP Notice:  Undefined offset: 100 in - on line 1
	echo '[NOTICE OCCURRED] ' . $e->getMessage();
}
```

### Run PHP Code in background

Note: process forking, so without class/functions sharing and without errors propagating

The code is executed in a separated child process

```php
$sandbox->runInBackground
(
	'imagecopyresized(/* ... */)',
	array('arg1', 'arg2'),
	true // TRUE means "wait for child response" | FALSE don't wait
);
```
