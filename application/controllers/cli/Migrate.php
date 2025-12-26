<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI entry point to run database migrations.
 */
class Migrate extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            show_error('The migration controller is only accessible via the CLI.', 403);
        }

        error_reporting(-1);
        ini_set('display_errors', '1');

        $this->config->set_item('migration_enabled', true);
        $this->load->library('migration');
    }

    /**
     * php index.php cli/migrate/latest
     */
    public function latest()
    {
        $this->runLatest();
    }

    /**
     * php index.php cli/migrate/fresh
     */
    public function fresh()
    {
        $rolledBack = $this->migration->version(0);
        if ($rolledBack === false) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('text/plain')
                ->set_output("Rollback failed: " . $this->migration->error_string() . PHP_EOL);
            return;
        }

        $this->runLatest();
    }

    /**
     * php index.php cli/migrate/version 1
     *
     * @param string|null $target
     */
    public function version($target = null)
    {
        if ($target === null || !ctype_digit((string) $target)) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('text/plain')
                ->set_output("Usage: php index.php cli/migrate/version <number>" . PHP_EOL);
            return;
        }

        $result = $this->migration->version((int) $target);
        if ($result === false) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('text/plain')
                ->set_output("Migration failed: " . $this->migration->error_string() . PHP_EOL);
            return;
        }

        $this->output
            ->set_content_type('text/plain')
            ->set_output("Migrated to version " . $target . PHP_EOL);
    }

    /**
     * Run latest migration with consistent output.
     */
    private function runLatest()
    {
        try {
            $result = $this->migration->latest();
        } catch (Throwable $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('text/plain')
                ->set_output("Migration failed: " . $e->getMessage() . PHP_EOL);
            return;
        }
        if ($result === false) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('text/plain')
                ->set_output("Migration failed: " . $this->migration->error_string() . PHP_EOL);
            return;
        }

        $this->output
            ->set_content_type('text/plain')
            ->set_output("Migrations complete." . PHP_EOL);
    }
}
