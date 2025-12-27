<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_identity_fields extends CI_Migration
{
    public function up()
    {
        $this->load->dbforge();

        $this->add_students_identity_fields();
        $this->add_online_admissions_identity_fields();
        $this->add_staff_identity_fields();
    }

    public function down()
    {
        $this->load->dbforge();

        $this->drop_students_identity_fields();
        $this->drop_online_admissions_identity_fields();
        $this->drop_staff_identity_fields();
    }

    private function add_students_identity_fields()
    {
        $fields = array();

        if (!$this->db->field_exists('nik', 'students')) {
            $fields['nik'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('nisn', 'students')) {
            $fields['nisn'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('kk_no', 'students')) {
            $fields['kk_no'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('birth_place', 'students')) {
            $fields['birth_place'] = array('type' => 'VARCHAR', 'constraint' => 100, 'null' => true);
        }

        if (!empty($fields)) {
            $this->dbforge->add_column('students', $fields);
        }
    }

    private function add_online_admissions_identity_fields()
    {
        $fields = array();

        if (!$this->db->field_exists('nik', 'online_admissions')) {
            $fields['nik'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('nisn', 'online_admissions')) {
            $fields['nisn'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('kk_no', 'online_admissions')) {
            $fields['kk_no'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('birth_place', 'online_admissions')) {
            $fields['birth_place'] = array('type' => 'VARCHAR', 'constraint' => 100, 'null' => true);
        }

        if (!empty($fields)) {
            $this->dbforge->add_column('online_admissions', $fields);
        }
    }

    private function add_staff_identity_fields()
    {
        $fields = array();

        if (!$this->db->field_exists('nik', 'staff')) {
            $fields['nik'] = array('type' => 'VARCHAR', 'constraint' => 50, 'null' => true);
        }
        if (!$this->db->field_exists('birth_place', 'staff')) {
            $fields['birth_place'] = array('type' => 'VARCHAR', 'constraint' => 100, 'null' => true);
        }

        if (!empty($fields)) {
            $this->dbforge->add_column('staff', $fields);
        }
    }

    private function drop_students_identity_fields()
    {
        foreach (array('nik', 'nisn', 'kk_no', 'birth_place') as $column) {
            if ($this->db->field_exists($column, 'students')) {
                $this->dbforge->drop_column('students', $column);
            }
        }
    }

    private function drop_online_admissions_identity_fields()
    {
        foreach (array('nik', 'nisn', 'kk_no', 'birth_place') as $column) {
            if ($this->db->field_exists($column, 'online_admissions')) {
                $this->dbforge->drop_column('online_admissions', $column);
            }
        }
    }

    private function drop_staff_identity_fields()
    {
        foreach (array('nik', 'birth_place') as $column) {
            if ($this->db->field_exists($column, 'staff')) {
                $this->dbforge->drop_column('staff', $column);
            }
        }
    }
}
