<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Init_schema extends CI_Migration
{
    /**
     * Absolute path to the canonical schema dump.
     *
     * @var string
     */
    private $schemaFile;

    public function __construct()
    {
        parent::__construct();

        $this->load->dbforge();
        $primarySchema = APPPATH . 'migrations/001_create_schema.sql';
        $legacySchema  = FCPATH . 'backup/Installasi/database.sql';

        $this->schemaFile = is_file($primarySchema) ? $primarySchema : $legacySchema;
    }

    public function up()
    {
        if ($this->db->table_exists('sch_settings')) {
            return;
        }

        if (!is_file($this->schemaFile)) {
            throw new RuntimeException('Schema file not found at ' . $this->schemaFile);
        }

        $sql = file_get_contents($this->schemaFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Schema file is empty or unreadable.');
        }

        $connection = $this->db->conn_id;
        if (!mysqli_multi_query($connection, $sql)) {
            throw new RuntimeException('Failed to import schema: ' . mysqli_error($connection));
        }

        // Consume all result sets so the connection state stays clean.
        do {
            if ($result = mysqli_store_result($connection)) {
                mysqli_free_result($result);
            }
        } while (mysqli_more_results($connection) && mysqli_next_result($connection));
    }

    public function down()
    {
        $tables = $this->db->list_tables();
        if (empty($tables)) {
            return;
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            $this->dbforge->drop_table($table, true);
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }
}
