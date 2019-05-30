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
     * Any errors collected during a run. Empty means that the entire transaction was successful. Any
     * successful shell command will add null to the list of errors.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * The return value of each shell command added to the job. If the shell command errored
     * for any reason then the output at that array index will be null.
     *
     * @return array
     */
    public function output()
    {
        return $this->output;
    }

    /**
     * Add a shell command to the current job steps. If the command errors, do not continue to the next
     * step in the list of shell commands.
     *
     * @param string $command
     */
    public function must(string $command)
    {
        $this->input[] = ['directive' => 'must', 'command' => $command];
    }

    /**
     * Add a shell command to the current job steps. If the command errors, continue to the next step
     * in the list of shell commands.
     *
     * @param string $command
     */
    public function may(string $command)
    {
        $this->input[] = ['directive' => 'may', 'command' => $command];
    }

    /**
     * Add a named argument to the current job's context. Any shell command that contains the named argument
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
     * Find/replace all named arguments in shell commands and run each shell command in order added.
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
        $process = proc_open($command, $spec, $pipes, dirname(__FILE__), null);
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
