<?php

namespace Crit\ExecJob;

class Job
{
    /**
     * Errors collected during a run.
     *
     * @var array
     */
    private $errors = [];

    /**
     * Output collected from each input.
     *
     * @var array
     */
    private $output = [];

    /**
     * Shell commands to be run.
     *
     * @var array
     */
    private $input = [];

    /**
     * Named arguments that will be used by the shell commands in input.
     *
     * @var array
     */
    private $args = [];

    /**
     * Wrapper around named arguments in the shell commands to pattern match.
     *
     * @var array
     */
    private $wrapper = ["<", ">"];

    /**
     * Working directory for shell commands. Null uses the current working directory for the PHP process.
     *
     * @var null|string
     */
    private $workingDir = null;

    /**
     * Env values for this job.
     *
     * @var null|array
     */
    private $env = null;

    /**
     * The error value of each shell command during a run.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * The output value of each shell command during a run.
     *
     * @return array
     */
    public function output()
    {
        return $this->output;
    }

    /**
     * Add a shell command to the current job. If the command errors, do not continue to the next
     * step in the list of shell commands.
     *
     * @param string $command
     */
    public function must(string $command)
    {
        $this->input[] = ['directive' => 'must', 'command' => $command];
    }

    /**
     * Add a shell command to the current job. If the command errors, continue to the next step
     * in the list of shell commands.
     *
     * @param string $command
     */
    public function may(string $command)
    {
        $this->input[] = ['directive' => 'may', 'command' => $command];
    }

    /**
     * Add a named argument to the current job. Any shell command that contains the named argument
     * wrapped correctly will be replaced with the shell escaped value at run time.
     *
     * @param string $name
     * @param string $value
     */
    public function arg(string $name, string $value)
    {
        $this->args[$name] = escapeshellarg($value);
    }

    /**
     * Change the named argument wrappers from their default values for this job.
     *
     * @param string $start
     * @param string $end
     */
    public function setArgWrapper(string $start, string $end)
    {
        $this->wrapper[0] = $start;
        $this->wrapper[1] = $end;
    }

    /**
     * Set the shell working directory for this job. Default's to the current working directory
     * for the PHP process.
     *
     * @param string $path
     */
    public function setWorkingDirectory(string $path)
    {
        if (is_dir($path)) $this->workingDir = $path;
    }

    /**
     * Set an ENV variable for this job.
     *
     * @param string $key
     * @param string $value
     */
    public function setEnv(string $key, string $value)
    {
        if (is_null($this->env)) $this->env = [];

        $this->env[$key] = $value;
    }

    /**
     * Find/replace all named arguments in shell commands and run each shell command in the order added.
     *
     * @return bool A run with no errors on 'must' directives will return true otherwise false.
     */
    public function run()
    {
        $wrapStart = $this->wrapper[0];
        $wrapEnd = $this->wrapper[1];

        foreach ($this->input as $item) {
            if (!$item['command'] || !$item['directive']) {
                $this->errors[] = "malformed input detected: " . json_encode($item);
                continue;
            }

            foreach ($this->args as $name => $value) {
                $needle = "{$wrapStart}{$name}{$wrapEnd}";
                $item['command'] = str_replace($needle, $value, $item['command']);
            }

            $result = $this->exec($item['command']);

            $this->errors[] = $result['error'];
            $this->output[] = $result['output'];

            if ($item['directive'] == 'must' && !empty($result['error'])) return false;
        }

        return true;
    }

    /**
     * Execute shell command as sub process while capturing standard out/error.
     *
     * @param string $command
     * @return array
     */
    private function exec(string $command)
    {
        $result = [
            'error' => null,
            'output' => null,
        ];

        $spec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w"),  // stderr
        );

        $pipes = [];
        $process = proc_open($command, $spec, $pipes, $this->workingDir, $this->env);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);

        if ($output) $result['output'] = trim($output);
        if ($stderr) $result['error'] = trim($stderr);

        return $result;
    }
}
