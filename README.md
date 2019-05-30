# php-exec-jobs
Execute shell scripts in order with named arguments and error checking.

## Usage

```php
<?php

use Crit/ExecJob/Job;

$job = new Job();

// $job->setArgWrapper(':', ':');

$job->arg('firstanme', 'John');
$job->arg('lastname', 'Doe');
$job->arg('interface', 'eth0');

$job->must('echo <firstname> <lastname>');
$job->may('hostname');
$job->must('ifconfig <interface>');

$ok = $job->run();

echo json_encode($job->output());
echo json_encode($job->errors());

echo $ok ? "Run successful" : "Run failed!";
```
