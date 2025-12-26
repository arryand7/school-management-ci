<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI entry point to run database seeders.
 */
class Seed extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            show_error('The seeder controller is only accessible via the CLI.', 403);
        }

        $this->load->library('DummyDataSeeder');
    }

    /**
     * php index.php cli/seed/dummy [fresh]
     *
     * @param string|null $mode Optional "fresh" flag to remove previously seeded rows.
     * @return void
     */
    public function dummy($mode = null)
    {
        $options = [];
        if (is_string($mode) && in_array(strtolower($mode), ['fresh', '--fresh'], true)) {
            $options['fresh'] = true;
        }

        try {
            $result = $this->dummydataseeder->seed($options);
            $this->output
                ->set_content_type('text/plain')
                ->set_output($this->formatResult($result));
        } catch (Throwable $e) {
            log_message('error', 'Dummy data seeding failed: ' . $e->getMessage());
            $message = 'Seeding failed: ' . $e->getMessage();
            $this->output
                ->set_status_header(500)
                ->set_content_type('text/plain')
                ->set_output($message . PHP_EOL);
        }
    }

    /**
     * Turn the seeder report array into nice CLI output.
     *
     * @param array $report
     * @return string
     */
    private function formatResult(array $report)
    {
        if (empty($report)) {
            return "Seeder finished but did not return a summary.\n";
        }

        $lines   = ["Dummy data seeding completed:\n"];
        $padding = 0;

        foreach ($report as $key => $value) {
            $padding = max($padding, strlen($key));
        }

        foreach ($report as $key => $value) {
            if (is_array($value)) {
                $lines[] = sprintf("%s:", ucfirst($key));
                foreach ($value as $childKey => $childValue) {
                    $lines[] = sprintf("  - %s: %s", ucfirst($childKey), $childValue);
                }
                continue;
            }

            $lines[] = sprintf("%s%s : %s", ucfirst($key), str_repeat(' ', $padding - strlen($key)), $value);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
