<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_api_keys extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
        $this->load->dbforge();
    }

    public function up()
    {
        $fields = array(
            'google_maps_api_key' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'firebase_service_account_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'yandex_translate_api_key' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'paymongo_public_key' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'paymongo_secret_key' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
        );

        foreach ($fields as $name => $definition) {
            if (!$this->db->field_exists($name, 'sch_settings')) {
                $this->dbforge->add_column('sch_settings', array($name => $definition));
            }
        }
    }

    public function down()
    {
        $columns = array(
            'google_maps_api_key',
            'firebase_service_account_json',
            'yandex_translate_api_key',
            'paymongo_public_key',
            'paymongo_secret_key',
        );

        foreach ($columns as $column) {
            if ($this->db->field_exists($column, 'sch_settings')) {
                $this->dbforge->drop_column('sch_settings', $column);
            }
        }
    }
}
