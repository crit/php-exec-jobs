# php-exec-jobs

Execute shell scripts in order with named arguments and error checking.

## Installation

`composer require crit/php-exec-jobs`

## Reason

You can accomplish something similar to this library by executing the following code for simple use cases:

```php
<?php
$interface = $_GET['interface'];
$interface = escapeshellarg($interface); // sometimes this step is incorrectly skipped by developers
$ip = exec("ip addr show dev $interface |grep inet |awk '{print $2}'");

echo $ip;
```

- What if you want to handle errors (like if `ip` does not exist on the host)? 
- What if you want to do many operations in a single request? 

This library aims to help with that process. It represents the research needed to capture
errors/output using `proc_open()` and giving you the ability to safely parametrize arguments to the shell if needed.

## Usage

```php
<?php

use Crit\ExecJob\Job;

$job = new Job();

// optionally change the wrapping for named arguments 
// defaulted to '<', '>'
// $job->setArgWrapper(':', ':');

// optionally change the shell working directory
// defaulted to the current working directory of the PHP process
// $job->setWorkingDirectory('/sbin');

// optionally set ENV variables for this job
// $job->setEnv('CUSTOM1', 'special-value-1');
// $job->setEnv('CUSTOM2', 'special-value-2');

$job->arg('firstname', $_GET['firstname']); // John
$job->arg('lastname', $_GET['lastname']); // Doe
$job->arg('interface', $_GET['interface']); // eth0

$job->must('echo "<firstname> <lastname>"'); // stops here if shell errors
$job->may('ip addr show dev <interface> |grep inet |awk \'{print $2}\''); // will not stop here if shell errors
$job->may('ifconfig <interface> |grep inet |awk \'{print $2}\''); // will not stop here if shell errors
$job->must('/sbin/someCustomScript <firstname> <lastname>');

$ok = $job->run(); // run commands in order

echo "Output: " . json_encode($job->output());
echo "Errors: " . json_encode($job->errors());

echo $ok ? "Run successful" : "Run failed";
```

### Output

```
> Output: ["John Doe", null, "192.168.10.16", "Hello, John Doe!"]
> Errors: [null, "sh: command not found: ip", null, null]
> Run successful
```
