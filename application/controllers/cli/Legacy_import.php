<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI import for legacy staff, student, and PPDB data.
 */
class Legacy_import extends CI_Controller
{
    private $role_ids = array();
    private $custom_fields = array();
    private $class_cache = array();
    private $section_cache = array();
    private $class_section_cache = array();
    private $lang_id = 4;
    private $session_id = null;

    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            show_error('Legacy import is only accessible via the CLI.', 403);
        }

        error_reporting(-1);
        ini_set('display_errors', '1');

        $this->load->database();
        $this->load->library('Enc_lib');
    }

    /**
     * php index.php cli/legacy_import/run --path=/path/to/csv --wipe=1
     */
    public function run()
    {
        $args = $this->parse_args();
        $path = isset($args['path']) ? rtrim($args['path'], "/\\") : FCPATH . 'storage/import';
        $wipe = isset($args['wipe']) && $args['wipe'] === '1';

        $datasets = $this->load_datasets($path);
        if ($datasets === false) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('text/plain')
                ->set_output("Missing CSV files under: " . $path . PHP_EOL);
            return;
        }

        $settings = $this->db->select('lang_id, session_id')->from('sch_settings')->limit(1)->get()->row_array();
        if (!empty($settings['lang_id'])) {
            $this->lang_id = (int) $settings['lang_id'];
        }
        if (!empty($settings['session_id'])) {
            $this->session_id = (int) $settings['session_id'];
        }

        if ($wipe) {
            $this->wipe_dummy_data();
        }

        $this->seed_roles_permissions();
        $this->ensure_classes_sections();

        $custom_columns = $this->prepare_custom_fields($datasets);

        $staff_stats = $this->import_staff($datasets['staff'], $datasets['staff_email'], $custom_columns['staff']);
        $student_stats = $this->import_students($datasets['students'], $datasets['student_email'], $custom_columns['student']);
        $ppdb_stats = $this->import_ppdb($datasets['ppdb'], $custom_columns['ppdb']);

        $output = array(
            "Import finished.",
            "Staff: inserted {$staff_stats['inserted']}, updated {$staff_stats['updated']}, email-only {$staff_stats['email_only']}, recovered {$staff_stats['recovered']}",
            "Students: inserted {$student_stats['inserted']}, updated {$student_stats['updated']}",
            "PPDB: inserted {$ppdb_stats['inserted']}, skipped {$ppdb_stats['skipped']}",
        );

        $this->output
            ->set_content_type('text/plain')
            ->set_output(implode(PHP_EOL, $output) . PHP_EOL);
    }

    private function parse_args()
    {
        $args = array();
        $argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
        foreach ($argv as $arg) {
            if (strpos($arg, '--') !== 0) {
                continue;
            }
            $parts = explode('=', substr($arg, 2), 2);
            $key = $parts[0];
            $value = isset($parts[1]) ? $parts[1] : '';
            $args[$key] = $value;
        }
        return $args;
    }

    private function load_datasets($path)
    {
        $staff_path = $path . '/data_guru.csv';
        $student_path = $path . '/data_siswa.csv';
        $ppdb_path = $path . '/data_ppdb.csv';
        $staff_email_path = $path . '/staff_email.csv';
        $student_email_path = $path . '/student_email.csv';

        if (!file_exists($staff_path) || !file_exists($student_path) || !file_exists($ppdb_path)) {
            return false;
        }

        $data = array();
        $data['staff'] = $this->read_csv_assoc($staff_path);
        $data['students'] = $this->read_csv_assoc($student_path);
        $data['ppdb'] = $this->read_csv_assoc($ppdb_path);
        $data['staff_email'] = file_exists($staff_email_path) ? $this->read_csv_assoc($staff_email_path, ';') : array();
        $data['student_email'] = file_exists($student_email_path) ? $this->read_csv_assoc($student_email_path, ';') : array();

        return $data;
    }

    private function read_csv_assoc($path, $delimiter = ',')
    {
        $rows = array();
        if (!file_exists($path)) {
            return $rows;
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($headers === false) {
            fclose($handle);
            return $rows;
        }
        $headers = array_map('trim', $headers);
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($data) !== count($headers)) {
                $data = array_pad($data, count($headers), null);
            }
            $row = array();
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? $data[$index] : null;
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function wipe_dummy_data()
    {
        $superadmin = $this->db->select('id')->from('staff')->order_by('id')->limit(1)->get()->row_array();
        $superadmin_id = $superadmin ? (int) $superadmin['id'] : 0;

        $this->db->where('role', 'student')->delete('users');
        $this->db->where('role', 'parent')->delete('users');
        $this->db->empty_table('student_session');
        $this->db->empty_table('students');
        $this->db->empty_table('online_admissions');
        $this->db->empty_table('online_admission_custom_field_value');
        $this->db->empty_table('student_doc');
        $this->db->empty_table('student_timeline');

        if ($superadmin_id > 0) {
            $this->db->where('staff_id !=', $superadmin_id)->delete('staff_roles');
            $this->db->where('id !=', $superadmin_id)->delete('staff');
        } else {
            $this->db->empty_table('staff_roles');
            $this->db->empty_table('staff');
        }

        $custom_ids = $this->db->select('id')
            ->from('custom_fields')
            ->where_in('belong_to', array('student', 'staff', 'online_admission'))
            ->get()->result_array();

        if (!empty($custom_ids)) {
            $ids = array();
            foreach ($custom_ids as $row) {
                $ids[] = (int) $row['id'];
            }
            $this->db->where_in('custom_field_id', $ids)->delete('custom_field_values');
        }
    }

    private function seed_roles_permissions()
    {
        $this->role_ids['Teacher'] = $this->get_role_id('Teacher');
        $this->role_ids['Security'] = $this->ensure_role('Security');
        $this->role_ids['Kebersihan'] = $this->ensure_role('Kebersihan');
        $this->role_ids['Driver'] = $this->ensure_role('Driver');
        $this->role_ids['Pegawai Tidak Tetap'] = $this->ensure_role('Pegawai Tidak Tetap');

        $this->ensure_role_permissions($this->role_ids['Security'], array(78, 79, 80, 81, 82, 83, 84, 85, 43), 1, 1, 0, 0);
        $this->ensure_role_permissions($this->role_ids['Driver'], array(37, 38, 39, 259, 260, 261, 262, 174), 1, 0, 0, 0);
        $this->ensure_role_permissions($this->role_ids['Kebersihan'], array(43), 1, 0, 0, 0);
        $this->ensure_role_permissions($this->role_ids['Pegawai Tidak Tetap'], array(88, 109, 129, 43), 1, 0, 0, 0);
    }

    private function get_role_id($name)
    {
        $row = $this->db->select('id')->from('roles')->where('name', $name)->get()->row_array();
        return $row ? (int) $row['id'] : null;
    }

    private function ensure_role($name)
    {
        $existing = $this->get_role_id($name);
        if ($existing) {
            return $existing;
        }

        $this->db->insert('roles', array(
            'name' => $name,
            'slug' => null,
            'is_active' => 0,
            'is_system' => 0,
            'is_superadmin' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => '0000-00-00',
        ));

        return (int) $this->db->insert_id();
    }

    private function ensure_role_permissions($role_id, $perm_ids, $can_view, $can_add, $can_edit, $can_delete)
    {
        foreach ($perm_ids as $perm_id) {
            $exists = $this->db->select('id')->from('roles_permissions')
                ->where('role_id', $role_id)
                ->where('perm_cat_id', $perm_id)
                ->get()->row_array();
            if ($exists) {
                continue;
            }

            $this->db->insert('roles_permissions', array(
                'role_id' => $role_id,
                'perm_cat_id' => $perm_id,
                'can_view' => $can_view,
                'can_add' => $can_add,
                'can_edit' => $can_edit,
                'can_delete' => $can_delete,
                'created_at' => date('Y-m-d H:i:s'),
            ));
        }
    }

    private function ensure_classes_sections()
    {
        $class_names = array('X', 'XI', 'XII');
        $section_names = array('1', '2', '3', 'KHOS');

        foreach ($class_names as $class_name) {
            $class_id = $this->get_or_create_class($class_name);
            foreach ($section_names as $section_name) {
                $section_id = $this->get_or_create_section($section_name);
                $this->get_or_create_class_section($class_id, $section_id);
            }
        }
    }

    private function get_or_create_class($name)
    {
        if (isset($this->class_cache[$name])) {
            return $this->class_cache[$name];
        }
        $row = $this->db->select('id')->from('classes')->where('class', $name)->get()->row_array();
        if ($row) {
            $this->class_cache[$name] = (int) $row['id'];
            return $this->class_cache[$name];
        }

        $this->db->insert('classes', array(
            'class' => $name,
            'is_active' => 'yes',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ));
        $this->class_cache[$name] = (int) $this->db->insert_id();
        return $this->class_cache[$name];
    }

    private function get_or_create_section($name)
    {
        if (isset($this->section_cache[$name])) {
            return $this->section_cache[$name];
        }
        $row = $this->db->select('id')->from('sections')->where('section', $name)->get()->row_array();
        if ($row) {
            $this->section_cache[$name] = (int) $row['id'];
            return $this->section_cache[$name];
        }

        $this->db->insert('sections', array(
            'section' => $name,
            'is_active' => 'yes',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ));
        $this->section_cache[$name] = (int) $this->db->insert_id();
        return $this->section_cache[$name];
    }

    private function get_or_create_class_section($class_id, $section_id)
    {
        $key = $class_id . ':' . $section_id;
        if (isset($this->class_section_cache[$key])) {
            return $this->class_section_cache[$key];
        }
        $row = $this->db->select('id')->from('class_sections')
            ->where('class_id', $class_id)
            ->where('section_id', $section_id)
            ->get()->row_array();
        if ($row) {
            $this->class_section_cache[$key] = (int) $row['id'];
            return $this->class_section_cache[$key];
        }

        $this->db->insert('class_sections', array(
            'class_id' => $class_id,
            'section_id' => $section_id,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        $this->class_section_cache[$key] = (int) $this->db->insert_id();
        return $this->class_section_cache[$key];
    }

    private function prepare_custom_fields($datasets)
    {
        $staff_mapped = array(
            'NO', 'P I N', 'NIP', 'N I K', 'NUPTK', 'Nama', 'Tempat Lahir',
            'Tanggal Lahir', 'Jenis Kelamin', 'Agama', 'Telp', 'HP',
        );

        $student_mapped = array(
            'No.', 'NIS', 'NISN', 'NIK', 'Kartu Keluarga', 'Nama',
            'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Agama',
            'Golongan Darah', 'Tinggi Badan', 'Alamat Sekarang', 'Nomor HP',
            'Propinsi Asal', 'Kota Asal', 'Kode Pos Asal', 'Nama Asal Sekolah',
            'Tanggal Masuk', 'Email',
            'Nama Ayah', 'Telp Ayah', 'HP Ayah', 'Pekerjaan Ayah',
            'Nama Ibu', 'Telp Ibu', 'HP Ibu', 'Pekerjaan Ibu',
            'Nama Wali', 'Telp Wali', 'HP Wali', 'Email Wali', 'Alamat Wali', 'Pekerjaan Wali',
        );

        $ppdb_mapped = array(
            'NOMOR FORMULIR', 'NAMA PESERTA', 'NAMA PANGGILAN', 'TEMPAT LAHIR',
            'TANGGAL LAHIR', 'JENIS KELAMIN', 'AGAMA', 'NISN', 'NIK', 'EMAIL',
            'ALAMAT SEKARANG', 'PROPINSI', 'KOTA', 'KODE POS', 'NO. HP',
            'NAMA SEKOLAH ASAL', 'NAMA AYAH', 'NO HP', 'PEKERJAAN', 'NAMA IBU',
            'NO HP.1', 'PEKERJAAN.1', 'NAMA WALI', 'NO HP.2', 'PEKERJAAN.2',
            'KETERANGAN',
        );

        $custom_columns = array(
            'staff' => $this->build_custom_columns($datasets['staff'], $staff_mapped, 'staff'),
            'student' => $this->build_custom_columns($datasets['students'], $student_mapped, 'student'),
            'ppdb' => $this->build_custom_columns($datasets['ppdb'], $ppdb_mapped, 'online_admission', true),
        );

        return $custom_columns;
    }

    private function build_custom_columns($rows, $mapped, $belong_to, $use_online_table = false)
    {
        $columns = array();
        if (empty($rows)) {
            return $columns;
        }
        $headers = array_keys($rows[0]);
        foreach ($headers as $header) {
            if (in_array($header, $mapped, true)) {
                continue;
            }
            $columns[] = $header;
            $this->ensure_custom_field($header, $belong_to);
        }
        return $columns;
    }

    private function ensure_custom_field($name, $belong_to)
    {
        if (!isset($this->custom_fields[$belong_to])) {
            $this->custom_fields[$belong_to] = array();
        }
        if (isset($this->custom_fields[$belong_to][$name])) {
            return $this->custom_fields[$belong_to][$name];
        }

        $row = $this->db->select('id')->from('custom_fields')
            ->where('name', $name)
            ->where('belong_to', $belong_to)
            ->get()->row_array();
        if ($row) {
            $this->custom_fields[$belong_to][$name] = (int) $row['id'];
            return $this->custom_fields[$belong_to][$name];
        }

        $this->db->insert('custom_fields', array(
            'name' => $name,
            'belong_to' => $belong_to,
            'type' => 'text',
            'bs_column' => 12,
            'validation' => 0,
            'field_values' => null,
            'show_table' => null,
            'visible_on_table' => 0,
            'weight' => null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ));

        $this->custom_fields[$belong_to][$name] = (int) $this->db->insert_id();
        return $this->custom_fields[$belong_to][$name];
    }

    private function import_staff($staff_rows, $staff_email_rows, $custom_columns)
    {
        $stats = array('inserted' => 0, 'updated' => 0, 'email_only' => 0, 'recovered' => 0);
        $email_map = $this->build_email_map($staff_email_rows, 'NIP');
        $existing_nips = array();
        $staff_map = array();

        foreach ($staff_rows as $row) {
            $nip = $this->normalize_code($row['NIP']);
            if ($nip === null) {
                continue;
            }
            $staff_map[$nip] = $row;
            $existing_nips[$nip] = true;
            $email_info = isset($email_map[$nip]) ? $email_map[$nip] : null;

            $role_id = $this->map_staff_role($row['Jenis Pegawai']);
            $status = $this->clean_value($row['Status Pegawai']);
            $is_active = ($status === 'Aktif') ? 1 : 0;

            $email = $email_info ? $this->clean_value($email_info['Email Address']) : '';
            $raw_password = $email_info ? $this->clean_value($email_info['Password']) : null;
            $password = $raw_password ? $this->enc_lib->passHashEnc($raw_password) : $this->enc_lib->passHashEnc($nip);

            $contact_no = $this->normalize_phone($row['HP']);
            if ($contact_no === null) {
                $contact_no = $this->normalize_phone($row['Telp']);
            }

            $dob = $this->parse_date($row['Tanggal Lahir']);
            if ($dob === null) {
                $dob = '1970-01-01';
            }

            $data = array(
                'employee_id' => $nip,
                'lang_id' => $this->lang_id,
                'currency_id' => 0,
                'department' => null,
                'designation' => null,
                'qualification' => '',
                'work_exp' => '',
                'name' => $this->clean_value($row['Nama']) ?: '',
                'surname' => '',
                'father_name' => '',
                'mother_name' => '',
                'contact_no' => $contact_no ?: '',
                'emergency_contact_no' => '',
                'email' => $email ?: '',
                'dob' => $dob,
                'marital_status' => '',
                'date_of_joining' => null,
                'date_of_leaving' => null,
                'local_address' => '',
                'permanent_address' => '',
                'note' => '',
                'image' => '',
                'password' => $password,
                'gender' => $this->normalize_gender($row['Jenis Kelamin']),
                'account_title' => '',
                'bank_account_no' => '',
                'bank_name' => '',
                'ifsc_code' => '',
                'bank_branch' => '',
                'payscale' => '',
                'basic_salary' => null,
                'epf_no' => '',
                'contract_type' => '',
                'shift' => '',
                'location' => '',
                'facebook' => '',
                'twitter' => '',
                'linkedin' => '',
                'instagram' => '',
                'resume' => '',
                'joining_letter' => '',
                'resignation_letter' => '',
                'other_document_name' => '',
                'other_document_file' => '',
                'user_id' => 0,
                'is_active' => $is_active,
                'verification_code' => '',
                'disable_at' => null,
                'nik' => $this->normalize_code($row['N I K']),
                'birth_place' => $this->clean_value($row['Tempat Lahir']),
            );

            $existing = $this->db->select('id')->from('staff')->where('employee_id', $nip)->get()->row_array();
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('staff', $data);
                $staff_id = (int) $existing['id'];
                $stats['updated']++;
            } else {
                $this->db->insert('staff', $data);
                $staff_id = (int) $this->db->insert_id();
                $stats['inserted']++;
            }

            $this->upsert_staff_role($staff_id, $role_id);

            $this->insert_custom_values('staff', $staff_id, $custom_columns, $row, false);
        }

        foreach ($email_map as $nip => $info) {
            if (isset($existing_nips[$nip])) {
                continue;
            }
            $name = $this->clean_value($info['Name']);
            $email = $this->clean_value($info['Email Address']);
            $raw_password = $this->clean_value($info['Password']);
            $password = $raw_password ? $this->enc_lib->passHashEnc($raw_password) : $this->enc_lib->passHashEnc($nip);

            $data = array(
                'employee_id' => $nip,
                'lang_id' => $this->lang_id,
                'currency_id' => 0,
                'department' => null,
                'designation' => null,
                'qualification' => '',
                'work_exp' => '',
                'name' => $name ?: '',
                'surname' => '',
                'father_name' => '',
                'mother_name' => '',
                'contact_no' => '',
                'emergency_contact_no' => '',
                'email' => $email ?: '',
                'dob' => '1970-01-01',
                'marital_status' => '',
                'date_of_joining' => null,
                'date_of_leaving' => null,
                'local_address' => '',
                'permanent_address' => '',
                'note' => '',
                'image' => '',
                'password' => $password,
                'gender' => '',
                'account_title' => '',
                'bank_account_no' => '',
                'bank_name' => '',
                'ifsc_code' => '',
                'bank_branch' => '',
                'payscale' => '',
                'basic_salary' => null,
                'epf_no' => '',
                'contract_type' => '',
                'shift' => '',
                'location' => '',
                'facebook' => '',
                'twitter' => '',
                'linkedin' => '',
                'instagram' => '',
                'resume' => '',
                'joining_letter' => '',
                'resignation_letter' => '',
                'other_document_name' => '',
                'other_document_file' => '',
                'user_id' => 0,
                'is_active' => 0,
                'verification_code' => '',
                'disable_at' => null,
                'nik' => null,
                'birth_place' => null,
            );

            $existing = $this->db->select('id')->from('staff')->where('employee_id', $nip)->get()->row_array();
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('staff', $data);
                $staff_id = (int) $existing['id'];
                $stats['updated']++;
            } else {
                $this->db->insert('staff', $data);
                $staff_id = (int) $this->db->insert_id();
                $stats['inserted']++;
            }

            $this->upsert_staff_role($staff_id, $this->role_ids['Pegawai Tidak Tetap']);
            $stats['email_only']++;
        }

        $expected_nips = array_unique(array_merge(array_keys($staff_map), array_keys($email_map)));
        foreach ($expected_nips as $nip) {
            $existing = $this->db->select('id')->from('staff')->where('employee_id', $nip)->get()->row_array();
            if ($existing) {
                continue;
            }

            $row = isset($staff_map[$nip]) ? $staff_map[$nip] : null;
            $email_info = isset($email_map[$nip]) ? $email_map[$nip] : null;
            $is_email_only = $row === null;

            $role_id = $row ? $this->map_staff_role($row['Jenis Pegawai']) : $this->role_ids['Pegawai Tidak Tetap'];
            $status = $row ? $this->clean_value($row['Status Pegawai']) : null;
            $is_active = $is_email_only ? 0 : (($status === 'Aktif') ? 1 : 0);

            $email = $email_info ? $this->clean_value($email_info['Email Address']) : '';
            $raw_password = $email_info ? $this->clean_value($email_info['Password']) : null;
            $password = $raw_password ? $this->enc_lib->passHashEnc($raw_password) : $this->enc_lib->passHashEnc($nip);

            $contact_no = $row ? $this->normalize_phone($row['HP']) : null;
            if ($contact_no === null && $row) {
                $contact_no = $this->normalize_phone($row['Telp']);
            }

            $dob = $row ? $this->parse_date($row['Tanggal Lahir']) : null;
            if ($dob === null) {
                $dob = '1970-01-01';
            }

            $data = array(
                'employee_id' => $nip,
                'lang_id' => $this->lang_id,
                'currency_id' => 0,
                'department' => null,
                'designation' => null,
                'qualification' => '',
                'work_exp' => '',
                'name' => $row ? ($this->clean_value($row['Nama']) ?: '') : ($email_info ? ($this->clean_value($email_info['Name']) ?: '') : ''),
                'surname' => '',
                'father_name' => '',
                'mother_name' => '',
                'contact_no' => $contact_no ?: '',
                'emergency_contact_no' => '',
                'email' => $email ?: '',
                'dob' => $dob,
                'marital_status' => '',
                'date_of_joining' => null,
                'date_of_leaving' => null,
                'local_address' => '',
                'permanent_address' => '',
                'note' => '',
                'image' => '',
                'password' => $password,
                'gender' => $row ? $this->normalize_gender($row['Jenis Kelamin']) : '',
                'account_title' => '',
                'bank_account_no' => '',
                'bank_name' => '',
                'ifsc_code' => '',
                'bank_branch' => '',
                'payscale' => '',
                'basic_salary' => null,
                'epf_no' => '',
                'contract_type' => '',
                'shift' => '',
                'location' => '',
                'facebook' => '',
                'twitter' => '',
                'linkedin' => '',
                'instagram' => '',
                'resume' => '',
                'joining_letter' => '',
                'resignation_letter' => '',
                'other_document_name' => '',
                'other_document_file' => '',
                'user_id' => 0,
                'is_active' => $is_active,
                'verification_code' => '',
                'disable_at' => null,
                'nik' => $row ? $this->normalize_code($row['N I K']) : null,
                'birth_place' => $row ? $this->clean_value($row['Tempat Lahir']) : null,
            );

            $this->db->insert('staff', $data);
            $staff_id = (int) $this->db->insert_id();
            $this->upsert_staff_role($staff_id, $role_id);
            if ($row) {
                $this->insert_custom_values('staff', $staff_id, $custom_columns, $row, false);
            }
            $stats['recovered']++;
        }

        return $stats;
    }

    private function build_email_map($rows, $key_column)
    {
        $map = array();
        foreach ($rows as $row) {
            if (!isset($row[$key_column])) {
                continue;
            }
            $key = $this->normalize_code($row[$key_column]);
            if ($key === null) {
                continue;
            }
            $map[$key] = $row;
        }
        return $map;
    }

    private function map_staff_role($jenis)
    {
        $jenis = $this->clean_value($jenis);
        if ($jenis === null) {
            return $this->role_ids['Pegawai Tidak Tetap'];
        }

        if (strpos($jenis, 'Guru') !== false) {
            return $this->role_ids['Teacher'];
        }
        if ($jenis === 'Scurity') {
            return $this->role_ids['Security'];
        }
        if ($jenis === 'Kebersihan') {
            return $this->role_ids['Kebersihan'];
        }
        if ($jenis === 'Driver') {
            return $this->role_ids['Driver'];
        }
        if ($jenis === 'Pegawai Tidak Tetap') {
            return $this->role_ids['Pegawai Tidak Tetap'];
        }

        return $this->role_ids['Pegawai Tidak Tetap'];
    }

    private function upsert_staff_role($staff_id, $role_id)
    {
        $existing = $this->db->select('id')->from('staff_roles')->where('staff_id', $staff_id)->get()->row_array();
        $data = array('staff_id' => $staff_id, 'role_id' => $role_id);
        if ($existing) {
            $this->db->where('id', $existing['id'])->update('staff_roles', $data);
        } else {
            $this->db->insert('staff_roles', $data);
        }
    }

    private function import_students($student_rows, $student_email_rows, $custom_columns)
    {
        $stats = array('inserted' => 0, 'updated' => 0);
        $email_map = $this->build_email_map($student_email_rows, 'NIS');

        foreach ($student_rows as $row) {
            $nis = $this->normalize_code($row['NIS']);
            if ($nis === null) {
                continue;
            }
            $email_info = isset($email_map[$nis]) ? $email_map[$nis] : null;
            $email = $email_info ? $this->clean_value($email_info['Email Address']) : $this->clean_value($row['Email']);
            $password = $email_info ? $this->clean_value($email_info['Password']) : $nis;

            $status = $this->clean_value($row['Status']);
            $is_active = ($status === 'Aktif' && $email_info) ? 'yes' : 'no';

            $father_phone = $this->normalize_phone($row['HP Ayah']);
            if ($father_phone === null) {
                $father_phone = $this->normalize_phone($row['Telp Ayah']);
            }
            $mother_phone = $this->normalize_phone($row['HP Ibu']);
            if ($mother_phone === null) {
                $mother_phone = $this->normalize_phone($row['Telp Ibu']);
            }
            $guardian_name = $this->clean_value($row['Nama Wali']);
            $guardian_phone = $this->normalize_phone($row['HP Wali']);
            if ($guardian_phone === null) {
                $guardian_phone = $this->normalize_phone($row['Telp Wali']);
            }
            $guardian_email = $this->clean_value($row['Email Wali']);
            $guardian_address = $this->clean_value($row['Alamat Wali']);
            $guardian_occupation = $this->clean_value($row['Pekerjaan Wali']);

            $guardian_is = $guardian_name ? 'other' : 'father';
            $guardian_relation = $guardian_name ? 'Wali' : 'Father';

            if (!$guardian_name) {
                $guardian_name = $this->clean_value($row['Nama Ayah']);
                $guardian_phone = $father_phone;
                $guardian_email = $this->clean_value($row['Email Ayah']);
                $guardian_address = $this->clean_value($row['Alamat Ayah']);
                $guardian_occupation = $this->clean_value($row['Pekerjaan Ayah']);
            }

            $class_section = $this->parse_class_section($row['Kelas Sekarang']);
            $class_id = $class_section['class_id'];
            $section_id = $class_section['section_id'];

            $data = array(
                'parent_id' => 0,
                'admission_no' => $nis,
                'roll_no' => null,
                'admission_date' => $this->parse_date($row['Tanggal Masuk']),
                'firstname' => $this->clean_value($row['Nama']) ?: '',
                'middlename' => null,
                'lastname' => null,
                'rte' => null,
                'image' => null,
                'mobileno' => $this->normalize_phone($row['Nomor HP']) ?: '',
                'email' => $email ?: '',
                'state' => $this->clean_value($row['Propinsi Asal']),
                'city' => $this->clean_value($row['Kota Asal']),
                'pincode' => $this->clean_value($row['Kode Pos Asal']),
                'religion' => $this->clean_value($row['Agama']),
                'cast' => null,
                'dob' => $this->parse_date($row['Tanggal Lahir']),
                'gender' => $this->normalize_gender($row['Jenis Kelamin']),
                'current_address' => $this->clean_value($row['Alamat Sekarang']),
                'permanent_address' => null,
                'category_id' => null,
                'school_house_id' => null,
                'blood_group' => $this->clean_value($row['Golongan Darah']) ?: '-',
                'hostel_room_id' => null,
                'adhar_no' => null,
                'samagra_id' => null,
                'bank_account_no' => null,
                'bank_name' => null,
                'ifsc_code' => null,
                'guardian_is' => $guardian_is,
                'father_name' => $this->clean_value($row['Nama Ayah']),
                'father_phone' => $father_phone ?: '',
                'father_occupation' => $this->clean_value($row['Pekerjaan Ayah']),
                'mother_name' => $this->clean_value($row['Nama Ibu']),
                'mother_phone' => $mother_phone ?: '',
                'mother_occupation' => $this->clean_value($row['Pekerjaan Ibu']),
                'guardian_name' => $guardian_name ?: '',
                'guardian_relation' => $guardian_relation,
                'guardian_phone' => $guardian_phone ?: '',
                'guardian_occupation' => $guardian_occupation ?: '',
                'guardian_address' => $guardian_address ?: '',
                'guardian_email' => $guardian_email ?: '',
                'father_pic' => '',
                'mother_pic' => '',
                'guardian_pic' => '',
                'is_active' => $is_active,
                'previous_school' => $this->clean_value($row['Nama Asal Sekolah']),
                'height' => $this->clean_value($row['Tinggi Badan']) ?: '0',
                'weight' => $this->clean_value($this->get_row_value($row, 'Berat Badan')) ?: '0',
                'measurement_date' => null,
                'dis_reason' => 0,
                'note' => null,
                'dis_note' => '',
                'app_key' => null,
                'parent_app_key' => null,
                'disable_at' => null,
                'nik' => $this->normalize_code($row['NIK']),
                'nisn' => $this->normalize_code($row['NISN']),
                'kk_no' => $this->normalize_code($row['Kartu Keluarga']),
                'birth_place' => $this->clean_value($row['Tempat Lahir']),
            );

            $existing = $this->db->select('id')->from('students')->where('admission_no', $nis)->get()->row_array();
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('students', $data);
                $student_id = (int) $existing['id'];
                $stats['updated']++;
            } else {
                $this->db->insert('students', $data);
                $student_id = (int) $this->db->insert_id();
                $stats['inserted']++;
            }

            $this->upsert_student_session($student_id, $class_id, $section_id, $is_active);
            $this->upsert_student_user($student_id, $nis, $email, $password, $is_active);

            $this->insert_custom_values('student', $student_id, $custom_columns, $row, false);
        }

        return $stats;
    }

    private function upsert_student_session($student_id, $class_id, $section_id, $is_active)
    {
        $existing = $this->db->select('id')->from('student_session')
            ->where('student_id', $student_id)
            ->get()->row_array();

        $data = array(
            'session_id' => $this->session_id,
            'student_id' => $student_id,
            'class_id' => $class_id,
            'section_id' => $section_id,
            'hostel_room_id' => null,
            'vehroute_id' => null,
            'route_pickup_point_id' => null,
            'transport_fees' => 0,
            'fees_discount' => 0,
            'is_leave' => 0,
            'is_active' => ($is_active === 'yes') ? 'yes' : 'no',
            'is_alumni' => 0,
            'default_login' => 0,
            'updated_at' => null,
        );

        if ($existing) {
            $this->db->where('id', $existing['id'])->update('student_session', $data);
        } else {
            $this->db->insert('student_session', $data);
        }
    }

    private function upsert_student_user($student_id, $nis, $email, $password, $is_active)
    {
        $username = $email ?: $nis;
        $existing = $this->db->select('id')->from('users')
            ->where('role', 'student')
            ->where('user_id', $student_id)
            ->get()->row_array();

        $data = array(
            'user_id' => $student_id,
            'username' => $username,
            'password' => $password,
            'childs' => '',
            'role' => 'student',
            'lang_id' => $this->lang_id,
            'currency_id' => 0,
            'verification_code' => '',
            'is_active' => ($is_active === 'yes') ? 'yes' : 'no',
            'updated_at' => null,
        );

        if ($existing) {
            $this->db->where('id', $existing['id'])->update('users', $data);
        } else {
            $this->db->insert('users', $data);
        }
    }

    private function import_ppdb($ppdb_rows, $custom_columns)
    {
        $stats = array('inserted' => 0, 'skipped' => 0);
        $counter = 1;

        foreach ($ppdb_rows as $row) {
            $reference = $this->clean_value($row['NOMOR FORMULIR']);
            if (!$reference) {
                $reference = 'OA-' . $counter;
            }

            $guardian_name = $this->clean_value($row['NAMA WALI']);
            $guardian_phone = $this->normalize_phone($row['NO HP.2']);
            $guardian_occupation = $this->clean_value($row['PEKERJAAN.2']);
            $guardian_is = $guardian_name ? 'other' : 'father';
            $guardian_relation = $guardian_name ? 'Wali' : 'Father';

            if (!$guardian_name) {
                $guardian_name = $this->clean_value($row['NAMA AYAH']);
                $guardian_phone = $this->normalize_phone($row['NO HP']);
                $guardian_occupation = $this->clean_value($row['PEKERJAAN']);
            }

            $data = array(
                'admission_no' => null,
                'roll_no' => null,
                'reference_no' => $reference,
                'admission_date' => null,
                'firstname' => $this->clean_value($row['NAMA PESERTA']) ?: '',
                'middlename' => $this->clean_value($row['NAMA PANGGILAN']) ?: '',
                'lastname' => null,
                'rte' => 'No',
                'image' => null,
                'mobileno' => $this->normalize_phone($row['NO. HP']) ?: '',
                'email' => $this->clean_value($row['EMAIL']),
                'state' => $this->clean_value($row['PROPINSI']),
                'city' => $this->clean_value($row['KOTA']),
                'pincode' => $this->clean_value($row['KODE POS']),
                'religion' => $this->clean_value($row['AGAMA']),
                'cast' => '',
                'dob' => $this->parse_date($row['TANGGAL LAHIR']),
                'gender' => $this->normalize_gender($row['JENIS KELAMIN']),
                'current_address' => $this->clean_value($row['ALAMAT SEKARANG']),
                'permanent_address' => null,
                'category_id' => null,
                'class_section_id' => null,
                'route_id' => 0,
                'school_house_id' => null,
                'blood_group' => '-',
                'vehroute_id' => 0,
                'hostel_room_id' => null,
                'adhar_no' => null,
                'samagra_id' => null,
                'bank_account_no' => null,
                'bank_name' => null,
                'ifsc_code' => null,
                'guardian_is' => $guardian_is,
                'father_name' => $this->clean_value($row['NAMA AYAH']),
                'father_phone' => $this->normalize_phone($row['NO HP']) ?: '',
                'father_occupation' => $this->clean_value($row['PEKERJAAN']),
                'mother_name' => $this->clean_value($row['NAMA IBU']),
                'mother_phone' => $this->normalize_phone($row['NO HP.1']) ?: '',
                'mother_occupation' => $this->clean_value($row['PEKERJAAN.1']),
                'guardian_name' => $guardian_name ?: '',
                'guardian_relation' => $guardian_relation,
                'guardian_phone' => $guardian_phone ?: '',
                'guardian_occupation' => $guardian_occupation ?: '',
                'guardian_address' => null,
                'guardian_email' => '',
                'father_pic' => '',
                'mother_pic' => '',
                'guardian_pic' => '',
                'is_enroll' => 0,
                'previous_school' => $this->clean_value($row['NAMA SEKOLAH ASAL']),
                'height' => '0',
                'weight' => '0',
                'note' => $this->clean_value($row['KETERANGAN']) ?: '',
                'form_status' => 1,
                'paid_status' => 0,
                'measurement_date' => null,
                'app_key' => null,
                'document' => null,
                'submit_date' => date('Y-m-d'),
                'disable_at' => null,
                'nik' => $this->normalize_code($row['NIK']),
                'nisn' => $this->normalize_code($row['NISN']),
                'kk_no' => null,
                'birth_place' => $this->clean_value($row['TEMPAT LAHIR']),
            );

            $existing = $this->db->select('id')->from('online_admissions')
                ->where('reference_no', $reference)->get()->row_array();
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('online_admissions', $data);
                $online_id = (int) $existing['id'];
            } else {
                $this->db->insert('online_admissions', $data);
                $online_id = (int) $this->db->insert_id();
            }

            $this->insert_custom_values('online_admission', $online_id, $custom_columns, $row, true);

            $stats['inserted']++;
            $counter++;
        }

        return $stats;
    }

    private function insert_custom_values($belong_to, $belong_id, $columns, $row, $use_online_table)
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $value = $this->clean_value($row[$column]);
            if ($value === null) {
                continue;
            }
            $field_id = $this->ensure_custom_field($column, $belong_to);

            $data = array(
                'belong_table_id' => $belong_id,
                'custom_field_id' => $field_id,
                'field_value' => $value,
            );

            if ($use_online_table) {
                $this->db->insert('online_admission_custom_field_value', $data);
            } else {
                $this->db->insert('custom_field_values', $data);
            }
        }
    }

    private function parse_class_section($value)
    {
        $value = $this->clean_value($value);
        if ($value === null) {
            return array('class_id' => null, 'section_id' => null);
        }
        $parts = explode('.', $value);
        $class_name = trim($parts[0]);
        $section_name = isset($parts[1]) ? trim($parts[1]) : null;

        $class_id = $class_name ? $this->get_or_create_class($class_name) : null;
        $section_id = $section_name ? $this->get_or_create_section($section_name) : null;
        if ($class_id && $section_id) {
            $this->get_or_create_class_section($class_id, $section_id);
        }

        return array('class_id' => $class_id, 'section_id' => $section_id);
    }

    private function get_row_value($row, $key)
    {
        return array_key_exists($key, $row) ? $row[$key] : null;
    }

    private function clean_value($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || strtolower($value) === 'nan' || strtolower($value) === 'null') {
            return null;
        }
        return $value;
    }

    private function normalize_code($value)
    {
        $value = $this->clean_value($value);
        if ($value === null) {
            return null;
        }
        if (stripos($value, 'e') !== false) {
            $expanded = $this->expand_scientific_notation($value);
            if ($expanded !== null) {
                $value = $expanded;
            }
        }
        if (preg_match('/^\\d+\\.0$/', $value)) {
            $value = substr($value, 0, -2);
        }
        return trim($value);
    }

    private function expand_scientific_notation($value)
    {
        $value = strtoupper(trim($value));
        if (!preg_match('/^([0-9]+)(?:\\.([0-9]+))?E([+-]?\\d+)$/', $value, $matches)) {
            return null;
        }

        $int = $matches[1];
        $frac = isset($matches[2]) ? $matches[2] : '';
        $exp = (int) $matches[3];

        if ($exp < 0) {
            return null;
        }

        $digits = $int . $frac;
        $zeros = $exp - strlen($frac);
        if ($zeros >= 0) {
            return $digits . str_repeat('0', $zeros);
        }

        return substr($digits, 0, strlen($digits) + $zeros);
    }

    private function normalize_gender($value)
    {
        $value = $this->clean_value($value);
        if ($value === null) {
            return '';
        }
        $value_upper = strtoupper($value);
        if ($value_upper === 'L' || $value_upper === 'LAKI-LAKI') {
            return 'Male';
        }
        if ($value_upper === 'P' || $value_upper === 'PEREMPUAN') {
            return 'Female';
        }
        return $value;
    }

    private function normalize_phone($value)
    {
        $value = $this->clean_value($value);
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\\D+/', '', $value);
        return $digits !== '' ? $digits : null;
    }

    private function parse_date($value)
    {
        $value = $this->clean_value($value);
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 20000) {
                $timestamp = ((int) $numeric - 25569) * 86400;
                return gmdate('Y-m-d', $timestamp);
            }
        }

        $formats = array('Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'Y/m/d');
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }
}
