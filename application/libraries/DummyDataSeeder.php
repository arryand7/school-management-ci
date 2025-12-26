<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight dummy data generator so Smart School can be explored instantly.
 */
class DummyDataSeeder
{
    private $CI;
    private $sessionId;
    private $registryEnsured = false;
    private $stats = [];

    private $classMap = [];
    private $sectionMap = [];
    private $classSectionMap = [];
    private $subjectMap = [];
    private $subjectGroupId = null;
    private $subjectGroupSubjectMap = [];
    private $subjectGroupClassSectionMap = [];
    private $subjectTimetableIds = [];
    private $studentSessions = [];
    private $staffIds = [];

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->load->library(['enc_lib']);
        $this->CI->load->model(['setting_model']);

        $currentSession = (int) $this->CI->setting_model->getCurrentSession();
        $this->sessionId = $currentSession > 0 ? $currentSession : 19;
    }

    /**
     * @param array $options
     * @return array
     */
    public function seed(array $options = [])
    {
        $this->ensureRegistryTable();

        if (!empty($options['fresh'])) {
            $this->purgeRegistryRows();
        }

        $this->sessionId = $this->ensureActiveSession($this->sessionId);

        $this->CI->db->trans_start();
        try {
            $this->seedCategoriesAndHouses();
            $this->seedClassesAndSections();
            $this->seedSubjects();
            $this->seedStaff();
            $this->seedStudents();
            $this->seedParentAccounts();
            $this->seedClassTeachers();
            $this->seedSubjectGroups();
            $this->seedTimetable();
            $this->seedLessonPlan();
            $this->seedHostel();
            $this->seedTransport();
            $this->seedFees();
            $this->seedAttendance();
            $this->seedStudentSubjectAttendance();
            $this->seedHomework();
            $this->seedHomeworkEvaluation();
            $this->seedExams();
            $this->seedExamGroups();
            $this->seedOnlineExams();
            $this->seedLibrary();
            $this->seedInventory();
            $this->seedIncomeExpenses();
            $this->seedStaffAttendance();
            $this->seedEvents();
            $this->seedStudentTimeline();
            $this->seedStaffTimeline();
            $this->seedNoticeBoard();
        } catch (Throwable $e) {
            $this->CI->db->trans_rollback();
            throw $e;
        }

        $this->CI->db->trans_complete();
        if ($this->CI->db->trans_status() === false) {
            throw new RuntimeException('Dummy data seeding failed.');
        }

        return $this->stats;
    }

    private function seedCategoriesAndHouses()
    {
        if ($this->tableExists('categories')) {
            foreach (['Reguler', 'Beasiswa', 'KIP'] as $name) {
                $this->insertRow('categories', ['category' => $name], [
                    'category' => $name,
                    'is_active' => 'yes',
                ]);
            }
        }

        if ($this->tableExists('school_houses')) {
            foreach (['Garuda', 'Rajawali', 'Banteng'] as $house) {
                $this->insertRow('school_houses', ['house_name' => $house], [
                    'house_name' => $house,
                    'description' => 'Dummy house',
                    'is_active' => 'yes',
                ]);
            }
        }
    }

    private function seedClassesAndSections()
    {
        if (!$this->tableExists('classes') || !$this->tableExists('sections')) {
            return;
        }

        $structure = [
            'X IPA' => ['A', 'B'],
            'XI IPA' => ['A'],
        ];

        foreach ($structure as $className => $sections) {
            $classId = $this->insertRow('classes', ['class' => $className], [
                'class' => $className,
                'is_active' => 'yes',
            ]);
            $this->classMap[$className] = $classId;

            foreach ($sections as $sectionName) {
                $sectionId = $this->insertRow('sections', ['section' => $sectionName], [
                    'section' => $sectionName,
                    'is_active' => 'yes',
                ]);
                $this->sectionMap[$sectionName] = $sectionId;

                $classSectionId = null;
                if ($this->tableExists('class_sections')) {
                    $existing = $this->CI->db
                        ->where('class_id', $classId)
                        ->where('section_id', $sectionId)
                        ->get('class_sections')
                        ->row_array();

                    if ($existing) {
                        $classSectionId = (int) $existing['id'];
                    } else {
                        $this->CI->db->insert('class_sections', [
                            'class_id' => $classId,
                            'section_id' => $sectionId,
                            'is_active' => 'yes',
                        ]);
                        $classSectionId = (int) $this->CI->db->insert_id();
                        $this->registerInsert('class_sections', $classSectionId);
                    }
                }

                if ($classSectionId) {
                    $this->classSectionMap[$classId . ':' . $sectionId] = $classSectionId;
                }
            }
        }
    }

    private function seedSubjects()
    {
        if (!$this->tableExists('subjects')) {
            return;
        }

        $subjects = [
            ['Matematika', 'MATH'],
            ['Fisika', 'PHYS'],
            ['Bahasa Indonesia', 'BIND'],
            ['Bahasa Inggris', 'BING'],
        ];

        foreach ($subjects as $subject) {
            [$name, $code] = $subject;
            $this->subjectMap[$name] = $this->insertRow('subjects', ['name' => $name], [
                'name' => $name,
                'code' => $code,
                'type' => 'Theory',
                'is_active' => 'yes',
            ]);
        }
    }

    private function seedStaff()
    {
        if (!$this->tableExists('staff')) {
            return;
        }

        $defs = [
            ['name' => 'Andi Wijaya', 'email' => 'andi.wijaya@smknusantara.test', 'gender' => 'Male', 'role' => 2],
            ['name' => 'Sari Pratiwi', 'email' => 'sari.pratiwi@smknusantara.test', 'gender' => 'Female', 'role' => 2],
            ['name' => 'Ratna Hartono', 'email' => 'ratna.hartono@smknusantara.test', 'gender' => 'Female', 'role' => 3],
        ];

        $password = $this->CI->enc_lib->passHashEnc('StaffPass!23');

        foreach ($defs as $index => $staff) {
            $existing = $this->CI->db->where('email', $staff['email'])->get('staff')->row_array();
            if ($existing) {
                $staffId = (int) $existing['id'];
            } else {
                $this->CI->db->insert('staff', [
                    'employee_id' => 'EMP' . (100 + $index),
                    'lang_id' => 27,
                    'currency_id' => 0,
                    'department' => null,
                    'designation' => null,
                    'qualification' => 'S1 Pendidikan',
                    'work_exp' => '5 Tahun',
                    'name' => $staff['name'],
                    'surname' => '',
                    'father_name' => '',
                    'mother_name' => '',
                    'contact_no' => '08' . rand(111111111, 999999999),
                    'emergency_contact_no' => '',
                    'email' => $staff['email'],
                    'dob' => '1985-01-01',
                    'marital_status' => 'Married',
                    'date_of_joining' => '2020-07-01',
                    'date_of_leaving' => null,
                    'local_address' => 'Jl. Merdeka Bandung',
                    'permanent_address' => 'Jl. Merdeka Bandung',
                    'note' => 'Dummy data',
                    'image' => '',
                    'password' => $password,
                    'gender' => $staff['gender'],
                    'account_title' => '',
                    'bank_account_no' => '',
                    'bank_name' => '',
                    'ifsc_code' => '',
                    'bank_branch' => '',
                    'payscale' => '',
                    'basic_salary' => 0,
                    'epf_no' => '',
                    'contract_type' => 'permanent',
                    'shift' => 'pagi',
                    'location' => 'Kampus Utama',
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
                    'is_active' => 1,
                    'verification_code' => '',
                ]);
                $staffId = (int) $this->CI->db->insert_id();
                $this->registerInsert('staff', $staffId);
            }

            $this->staffIds[] = $staffId;

            if ($this->tableExists('staff_roles')) {
                $roleRow = $this->CI->db->where('staff_id', $staffId)->get('staff_roles')->row_array();
                if (!$roleRow) {
                    $this->CI->db->insert('staff_roles', [
                        'staff_id' => $staffId,
                        'role_id' => $staff['role'],
                        'is_active' => 1,
                    ]);
                    $this->registerInsert('staff_roles', (int) $this->CI->db->insert_id());
                }
            }
        }
    }

    private function seedStudents()
    {
        if (!$this->tableExists('students')) {
            return;
        }

        $students = [
            ['adm' => 'ADM23001', 'first' => 'Budi', 'last' => 'Santoso', 'gender' => 'Male', 'class' => 'X IPA', 'section' => 'A', 'category' => 'Reguler'],
            ['adm' => 'ADM23002', 'first' => 'Siti', 'last' => 'Rahmawati', 'gender' => 'Female', 'class' => 'X IPA', 'section' => 'B', 'category' => 'Beasiswa'],
            ['adm' => 'ADM23003', 'first' => 'Made', 'last' => 'Wirawan', 'gender' => 'Male', 'class' => 'XI IPA', 'section' => 'A', 'category' => 'Reguler'],
            ['adm' => 'ADM23004', 'first' => 'Putri', 'last' => 'Lestari', 'gender' => 'Female', 'class' => 'XI IPA', 'section' => 'A', 'category' => 'KIP'],
        ];

        foreach ($students as $i => $student) {
            $classId = $this->classMap[$student['class']] ?? null;
            $sectionId = $this->sectionMap[$student['section']] ?? null;
            if (!$classId || !$sectionId) {
                continue;
            }

            $existing = $this->CI->db->where('admission_no', $student['adm'])->get('students')->row_array();
            if ($existing) {
                $studentId = (int) $existing['id'];
            } else {
                $categoryId = null;
                if ($this->tableExists('categories')) {
                    $categoryId = $this->CI->db->select('id')->where('category', $student['category'])->get('categories')->row()->id ?? null;
                }

                $guardianPhone = '08' . rand(111111111, 999999999);
                $guardianEmail = strtolower($student['first'] . '.' . $student['last']) . '@parent.test';

                $this->CI->db->insert('students', [
                    'parent_id' => 0,
                    'admission_no' => $student['adm'],
                    'roll_no' => 'R' . (100 + $i),
                    'admission_date' => '2023-07-10',
                    'firstname' => $student['first'],
                    'middlename' => '',
                    'lastname' => $student['last'],
                    'rte' => 'No',
                    'image' => '',
                    'mobileno' => '08' . rand(111111111, 999999999),
                    'email' => strtolower($student['first'] . '.' . $student['last']) . '@student.test',
                    'state' => 'Jawa Barat',
                    'city' => 'Bandung',
                    'pincode' => '40123',
                    'religion' => 'Islam',
                    'cast' => '',
                    'dob' => '2008-05-01',
                    'gender' => $student['gender'],
                    'current_address' => 'Jl. Mawar No.' . ($i + 1),
                    'permanent_address' => 'Jl. Mawar No.' . ($i + 1),
                    'category_id' => $categoryId,
                    'school_house_id' => null,
                    'blood_group' => 'O+',
                    'hostel_room_id' => null,
                    'adhar_no' => '',
                    'samagra_id' => '',
                    'bank_account_no' => '',
                    'bank_name' => '',
                    'ifsc_code' => '',
                    'guardian_is' => 'father',
                    'father_name' => 'Bapak ' . $student['last'],
                    'father_phone' => $guardianPhone,
                    'father_occupation' => 'Karyawan',
                    'mother_name' => 'Ibu ' . $student['last'],
                    'mother_phone' => '',
                    'mother_occupation' => 'Ibu Rumah Tangga',
                    'guardian_name' => 'Bapak ' . $student['last'],
                    'guardian_relation' => 'Father',
                    'guardian_phone' => $guardianPhone,
                    'guardian_occupation' => 'Karyawan',
                    'guardian_address' => 'Jl. Mawar No.' . ($i + 1),
                    'guardian_email' => $guardianEmail,
                    'father_pic' => '',
                    'mother_pic' => '',
                    'guardian_pic' => '',
                    'is_active' => 'yes',
                    'previous_school' => '',
                    'height' => '160',
                    'weight' => '50',
                    'measurement_date' => '2023-07-10',
                    'dis_reason' => 0,
                    'note' => '',
                    'dis_note' => '',
                    'app_key' => '',
                    'parent_app_key' => '',
                    'disable_at' => null,
                ]);
                $studentId = (int) $this->CI->db->insert_id();
                $this->registerInsert('students', $studentId);
            }

            if ($this->tableExists('users')) {
                $user = $this->CI->db
                    ->where('user_id', $studentId)
                    ->where('role', 'student')
                    ->get('users')
                    ->row_array();
                if (!$user) {
                    $this->CI->db->insert('users', [
                        'user_id' => $studentId,
                        'username' => 'STD' . substr($student['adm'], -4),
                        'password' => 'Siswa123!',
                        'childs' => '',
                        'role' => 'student',
                        'lang_id' => 27,
                        'currency_id' => 0,
                        'verification_code' => '',
                        'is_active' => 'yes',
                    ]);
                    $this->registerInsert('users', (int) $this->CI->db->insert_id());
                }
            }

            if ($this->tableExists('student_session')) {
                $session = $this->CI->db
                    ->where('student_id', $studentId)
                    ->where('session_id', $this->sessionId)
                    ->get('student_session')
                    ->row_array();

                if ($session) {
                    $studentSessionId = (int) $session['id'];
                } else {
                    $this->CI->db->insert('student_session', [
                        'session_id' => $this->sessionId,
                        'student_id' => $studentId,
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'is_leave' => 0,
                        'is_active' => 'yes',
                        'is_alumni' => 0,
                        'default_login' => 0,
                    ]);
                    $studentSessionId = (int) $this->CI->db->insert_id();
                    $this->registerInsert('student_session', $studentSessionId);
                }

                $this->studentSessions[$studentId] = [
                    'id' => $studentSessionId,
                    'class_id' => $classId,
                    'section_id' => $sectionId,
                ];
            }
        }

        $this->stats['students'] = count($this->studentSessions);
    }

    private function seedFees()
    {
        if (!$this->tableExists('fee_groups') || !$this->tableExists('feetype') || !$this->tableExists('student_fees_master')) {
            return;
        }

        $groupId = $this->insertRow('fee_groups', ['name' => 'Pembayaran Rutin'], [
            'name' => 'Pembayaran Rutin',
            'description' => 'SPP bulanan',
            'is_system' => 0,
            'is_active' => 'yes',
        ]);

        $sessionGroup = $this->CI->db
            ->where('fee_groups_id', $groupId)
            ->where('session_id', $this->sessionId)
            ->get('fee_session_groups')
            ->row_array();
        if ($sessionGroup) {
            $sessionGroupId = (int) $sessionGroup['id'];
        } else {
            $this->CI->db->insert('fee_session_groups', [
                'fee_groups_id' => $groupId,
                'session_id' => $this->sessionId,
                'is_active' => 'yes',
            ]);
            $sessionGroupId = (int) $this->CI->db->insert_id();
            $this->registerInsert('fee_session_groups', $sessionGroupId);
        }

        $feetypeId = $this->insertRow('feetype', ['type' => 'SPP Bulanan'], [
            'type' => 'SPP Bulanan',
            'code' => 'SPP-2023',
            'is_active' => 'yes',
        ]);

        $feeGroupType = $this->CI->db
            ->where('fee_session_group_id', $sessionGroupId)
            ->where('feetype_id', $feetypeId)
            ->get('fee_groups_feetype')
            ->row_array();
        if ($feeGroupType) {
            $feeGroupTypeId = (int) $feeGroupType['id'];
        } else {
            $this->CI->db->insert('fee_groups_feetype', [
                'fee_session_group_id' => $sessionGroupId,
                'fee_groups_id' => $groupId,
                'feetype_id' => $feetypeId,
                'session_id' => $this->sessionId,
                'amount' => 450000,
                'fine_type' => 'none',
                'fine_percentage' => 0,
                'fine_amount' => 0,
                'due_date' => '2023-07-20',
                'is_active' => 'yes',
            ]);
            $feeGroupTypeId = (int) $this->CI->db->insert_id();
            $this->registerInsert('fee_groups_feetype', $feeGroupTypeId);
        }

        foreach ($this->studentSessions as $studentSession) {
            $master = $this->CI->db
                ->where('student_session_id', $studentSession['id'])
                ->where('fee_session_group_id', $sessionGroupId)
                ->get('student_fees_master')
                ->row_array();

            if ($master) {
                $masterId = (int) $master['id'];
            } else {
                $this->CI->db->insert('student_fees_master', [
                    'student_session_id' => $studentSession['id'],
                    'fee_session_group_id' => $sessionGroupId,
                    'is_system' => 0,
                    'amount' => 0,
                    'is_active' => 'yes',
                ]);
                $masterId = (int) $this->CI->db->insert_id();
                $this->registerInsert('student_fees_master', $masterId);
            }

            if ($this->tableExists('student_fees_deposite')) {
                $deposit = $this->CI->db
                    ->where('student_fees_master_id', $masterId)
                    ->where('fee_groups_feetype_id', $feeGroupTypeId)
                    ->get('student_fees_deposite')
                    ->row_array();

                if (!$deposit) {
                    $amountDetail = json_encode([
                        '1' => [
                            'amount' => 200000,
                            'amount_discount' => 0,
                            'amount_fine' => 0,
                            'date' => date('Y-m-d'),
                            'collected_by' => 'Seeder',
                            'payment_mode' => 'cash',
                            'description' => 'Pembayaran awal',
                            'inv_no' => 1,
                        ],
                    ]);

                    $this->CI->db->insert('student_fees_deposite', [
                        'student_fees_master_id' => $masterId,
                        'fee_groups_feetype_id' => $feeGroupTypeId,
                        'amount_detail' => $amountDetail,
                        'is_active' => 'yes',
                    ]);
                    $this->registerInsert('student_fees_deposite', (int) $this->CI->db->insert_id());
                }
            }
        }
    }

    private function seedAttendance()
    {
        if (!$this->tableExists('student_attendences')) {
            return;
        }

        $date = date('Y-m-d', strtotime('-1 day'));
        foreach ($this->studentSessions as $studentSession) {
            $exists = $this->CI->db
                ->where('student_session_id', $studentSession['id'])
                ->where('date', $date)
                ->get('student_attendences')
                ->row_array();

            if (!$exists) {
                $this->CI->db->insert('student_attendences', [
                    'student_session_id' => $studentSession['id'],
                    'date' => $date,
                    'attendence_type_id' => 1,
                    'remark' => 'Hadir',
                    'is_active' => 'yes',
                ]);
                $this->registerInsert('student_attendences', (int) $this->CI->db->insert_id());
            }
        }
    }

    private function seedHomework()
    {
        if (!$this->tableExists('homework')) {
            return;
        }

        $staffId = $this->staffIds[0] ?? 0;
        $subjectGroupSubjectId = $this->getFirstSubjectGroupSubjectId();
        $subjectId = $this->getFirstSubjectId();
        foreach ($this->studentSessions as $studentSession) {
            $classId = $studentSession['class_id'] ?? null;
            $sectionId = $studentSession['section_id'] ?? null;
            if (!$classId || !$sectionId) {
                continue;
            }

            $exists = $this->CI->db
                ->where('class_id', $classId)
                ->where('section_id', $sectionId)
                ->get('homework')
                ->row_array();

            if (!$exists) {
                $this->CI->db->insert('homework', [
                    'class_id' => $classId,
                    'section_id' => $sectionId,
                    'session_id' => $this->sessionId,
                    'staff_id' => $staffId,
                    'subject_group_subject_id' => $subjectGroupSubjectId ?: null,
                    'subject_id' => $subjectId ?: null,
                    'homework_date' => date('Y-m-d'),
                    'submit_date' => date('Y-m-d', strtotime('+3 days')),
                    'marks' => 100,
                    'description' => 'Kerjakan latihan matematika.',
                    'create_date' => date('Y-m-d'),
                    'created_by' => $staffId,
                ]);
                $this->registerInsert('homework', (int) $this->CI->db->insert_id());
            }
        }
    }

    private function seedExams()
    {
        if (!$this->tableExists('exams') || !$this->tableExists('exam_schedules')) {
            return;
        }

        $examId = $this->insertRow('exams', ['name' => 'UTS Dummy'], [
            'name' => 'UTS Dummy',
            'sesion_id' => $this->sessionId,
            'note' => 'Contoh ujian',
            'is_active' => 'yes',
        ]);

        $schedule = $this->CI->db
            ->where('exam_id', $examId)
            ->get('exam_schedules')
            ->row_array();

        if (!$schedule) {
            $this->CI->db->insert('exam_schedules', [
                'session_id' => $this->sessionId,
                'exam_id' => $examId,
                'teacher_subject_id' => null,
                'date_of_exam' => date('Y-m-d', strtotime('+7 days')),
                'start_to' => '08:00',
                'end_from' => '10:00',
                'room_no' => 'AULA',
                'full_marks' => 100,
                'passing_marks' => 60,
                'is_active' => 'yes',
            ]);
            $this->registerInsert('exam_schedules', (int) $this->CI->db->insert_id());
        }
    }

    private function seedLibrary()
    {
        if (!$this->tableExists('books') || !$this->tableExists('libarary_members') || !$this->tableExists('book_issues')) {
            return;
        }

        $bookId = $this->insertRow('books', ['book_no' => 'BK-001'], [
            'book_title' => 'Dasar Matematika',
            'book_no' => 'BK-001',
            'isbn_no' => '9781234567890',
            'rack_no' => 'A1',
            'qty' => 5,
            'perunitcost' => 50000,
            'postdate' => date('Y-m-d'),
            'description' => 'Buku dummy',
            'available' => 'yes',
            'is_active' => 'yes',
        ]);

        $studentId = array_key_first($this->studentSessions);
        if (!$studentId) {
            return;
        }

        $member = $this->CI->db
            ->where('member_id', $studentId)
            ->where('member_type', 'student')
            ->get('libarary_members')
            ->row_array();

        if ($member) {
            $memberId = (int) $member['id'];
        } else {
            $this->CI->db->insert('libarary_members', [
                'library_card_no' => 'CARD' . $studentId,
                'member_type' => 'student',
                'member_id' => $studentId,
                'is_active' => 'yes',
            ]);
            $memberId = (int) $this->CI->db->insert_id();
            $this->registerInsert('libarary_members', $memberId);
        }

        $issue = $this->CI->db
            ->where('book_id', $bookId)
            ->where('member_id', $memberId)
            ->get('book_issues')
            ->row_array();

        if (!$issue) {
            $this->CI->db->insert('book_issues', [
                'book_id' => $bookId,
                'member_id' => $memberId,
                'issue_date' => date('Y-m-d'),
                'duereturn_date' => date('Y-m-d', strtotime('+7 days')),
                'is_returned' => 0,
                'is_active' => 'yes',
            ]);
            $this->registerInsert('book_issues', (int) $this->CI->db->insert_id());
        }
    }

    private function seedNoticeBoard()
    {
        if (!$this->tableExists('send_notification')) {
            return;
        }

        $exists = $this->CI->db
            ->where('title', 'Selamat datang di Smart School')
            ->get('send_notification')
            ->row_array();

        if (!$exists) {
            $this->CI->db->insert('send_notification', [
                'title' => 'Selamat datang di Smart School',
                'publish_date' => date('Y-m-d'),
                'date' => date('Y-m-d'),
                'message' => 'Ini adalah contoh pengumuman otomatis.',
                'visible_student' => 'yes',
                'visible_staff' => 'yes',
                'visible_parent' => 'yes',
                'is_active' => 'yes',
            ]);
            $this->registerInsert('send_notification', (int) $this->CI->db->insert_id());
        }
    }

    private function seedParentAccounts()
    {
        if (!$this->tableExists('users') || !$this->tableExists('students')) {
            return;
        }

        foreach ($this->studentSessions as $studentId => $studentSession) {
            $student = $this->CI->db
                ->select('id, admission_no, guardian_phone, guardian_email, father_name, parent_id')
                ->where('id', $studentId)
                ->get('students')
                ->row_array();

            if (!$student) {
                continue;
            }

            $parentId = (int) ($student['parent_id'] ?? 0);
            if ($parentId > 0) {
                $parent = $this->CI->db->select('id, childs')->where('id', $parentId)->get('users')->row_array();
                if ($parent) {
                    $childs = $this->appendChilds($parent['childs'] ?? '', $studentId);
                    if ($childs !== ($parent['childs'] ?? '')) {
                        $this->CI->db->where('id', $parentId)->update('users', ['childs' => $childs]);
                    }
                }
                continue;
            }

            $suffix = substr((string) $student['admission_no'], -4);
            $username = 'PRNT' . ($suffix !== '' ? $suffix : $studentId);
            $existing = $this->CI->db
                ->where('username', $username)
                ->where('role', 'parent')
                ->get('users')
                ->row_array();

            if ($existing) {
                $parentId = (int) $existing['id'];
            } else {
                $this->CI->db->insert('users', [
                    'user_id' => $studentId,
                    'username' => $username,
                    'password' => 'Parent123!',
                    'childs' => (string) $studentId,
                    'role' => 'parent',
                    'lang_id' => 27,
                    'currency_id' => 0,
                    'verification_code' => '',
                    'is_active' => 'yes',
                ]);
                $parentId = (int) $this->CI->db->insert_id();
                $this->registerInsert('users', $parentId);
            }

            $this->CI->db->where('id', $studentId)->update('students', ['parent_id' => $parentId]);
        }
    }

    private function seedClassTeachers()
    {
        if (!$this->tableExists('class_teacher') || empty($this->staffIds)) {
            return;
        }

        $staffCount = count($this->staffIds);
        $index = 0;

        foreach ($this->classSectionMap as $key => $classSectionId) {
            [$classId, $sectionId] = $this->splitClassSectionKey($key);
            if (!$classId || !$sectionId) {
                continue;
            }

            $existing = $this->CI->db
                ->where('session_id', $this->sessionId)
                ->where('class_id', $classId)
                ->where('section_id', $sectionId)
                ->get('class_teacher')
                ->row_array();

            if ($existing) {
                $index++;
                continue;
            }

            $staffId = $this->staffIds[$index % $staffCount];
            $this->CI->db->insert('class_teacher', [
                'session_id' => $this->sessionId,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'staff_id' => $staffId,
            ]);
            $this->registerInsert('class_teacher', (int) $this->CI->db->insert_id());
            $index++;
        }
    }

    private function seedSubjectGroups()
    {
        if (
            !$this->tableExists('subject_groups')
            || !$this->tableExists('subject_group_class_sections')
            || !$this->tableExists('subject_group_subjects')
        ) {
            return;
        }

        $this->subjectGroupId = $this->insertRow('subject_groups', [
            'name' => 'IPA Dasar',
            'session_id' => $this->sessionId,
        ], [
            'name' => 'IPA Dasar',
            'description' => 'Kelompok mapel dasar',
            'session_id' => $this->sessionId,
        ]);

        if (!$this->subjectGroupId) {
            return;
        }

        foreach ($this->classSectionMap as $key => $classSectionId) {
            $existing = $this->CI->db
                ->where('subject_group_id', $this->subjectGroupId)
                ->where('class_section_id', $classSectionId)
                ->where('session_id', $this->sessionId)
                ->get('subject_group_class_sections')
                ->row_array();

            if ($existing) {
                $this->subjectGroupClassSectionMap[$classSectionId] = (int) $existing['id'];
                continue;
            }

            $this->CI->db->insert('subject_group_class_sections', [
                'subject_group_id' => $this->subjectGroupId,
                'class_section_id' => $classSectionId,
                'session_id' => $this->sessionId,
                'description' => 'Kelompok mapel default',
                'is_active' => 1,
            ]);
            $id = (int) $this->CI->db->insert_id();
            $this->registerInsert('subject_group_class_sections', $id);
            $this->subjectGroupClassSectionMap[$classSectionId] = $id;
        }

        foreach ($this->subjectMap as $subjectName => $subjectId) {
            $existing = $this->CI->db
                ->where('subject_group_id', $this->subjectGroupId)
                ->where('session_id', $this->sessionId)
                ->where('subject_id', $subjectId)
                ->get('subject_group_subjects')
                ->row_array();

            if ($existing) {
                $this->subjectGroupSubjectMap[$subjectId] = (int) $existing['id'];
                continue;
            }

            $this->CI->db->insert('subject_group_subjects', [
                'subject_group_id' => $this->subjectGroupId,
                'session_id' => $this->sessionId,
                'subject_id' => $subjectId,
            ]);
            $id = (int) $this->CI->db->insert_id();
            $this->registerInsert('subject_group_subjects', $id);
            $this->subjectGroupSubjectMap[$subjectId] = $id;
        }
    }

    private function seedTimetable()
    {
        if (
            !$this->tableExists('subject_timetable')
            || !$this->subjectGroupId
            || empty($this->subjectGroupSubjectMap)
            || empty($this->classSectionMap)
        ) {
            return;
        }

        $staffId = $this->staffIds[0] ?? null;
        if (!$staffId) {
            return;
        }

        $subjectGroupSubjectIds = array_values($this->subjectGroupSubjectMap);
        $subjectGroupSubjectIds = array_slice($subjectGroupSubjectIds, 0, 2);

        foreach ($this->classSectionMap as $key => $classSectionId) {
            [$classId, $sectionId] = $this->splitClassSectionKey($key);
            if (!$classId || !$sectionId) {
                continue;
            }

            foreach ($subjectGroupSubjectIds as $index => $subjectGroupSubjectId) {
                $day = $index === 0 ? 'Monday' : 'Tuesday';
                $start = $index === 0 ? '08:00' : '09:00';
                $end = $index === 0 ? '09:00' : '10:00';

                $existing = $this->CI->db
                    ->where('class_id', $classId)
                    ->where('section_id', $sectionId)
                    ->where('subject_group_subject_id', $subjectGroupSubjectId)
                    ->get('subject_timetable')
                    ->row_array();

                if ($existing) {
                    $this->subjectTimetableIds[] = (int) $existing['id'];
                    continue;
                }

                $this->CI->db->insert('subject_timetable', [
                    'session_id' => $this->sessionId,
                    'class_id' => $classId,
                    'section_id' => $sectionId,
                    'subject_group_id' => $this->subjectGroupId,
                    'subject_group_subject_id' => $subjectGroupSubjectId,
                    'staff_id' => $staffId,
                    'day' => $day,
                    'time_from' => $start,
                    'time_to' => $end,
                    'start_time' => $start . ':00',
                    'end_time' => $end . ':00',
                    'room_no' => 'R-1',
                ]);
                $id = (int) $this->CI->db->insert_id();
                $this->registerInsert('subject_timetable', $id);
                $this->subjectTimetableIds[] = $id;
            }
        }
    }

    private function seedLessonPlan()
    {
        if (
            !$this->tableExists('lesson')
            || !$this->tableExists('topic')
            || !$this->tableExists('subject_syllabus')
        ) {
            return;
        }

        $subjectGroupSubjectId = $this->getFirstSubjectGroupSubjectId();
        $subjectGroupClassSectionsId = $this->getFirstSubjectGroupClassSectionId();
        if (!$subjectGroupSubjectId || !$subjectGroupClassSectionsId) {
            return;
        }

        $lessonName = 'Limit dan Turunan';
        $lesson = $this->CI->db
            ->where('subject_group_subject_id', $subjectGroupSubjectId)
            ->where('subject_group_class_sections_id', $subjectGroupClassSectionsId)
            ->where('name', $lessonName)
            ->get('lesson')
            ->row_array();

        if ($lesson) {
            $lessonId = (int) $lesson['id'];
        } else {
            $this->CI->db->insert('lesson', [
                'session_id' => $this->sessionId,
                'subject_group_subject_id' => $subjectGroupSubjectId,
                'subject_group_class_sections_id' => $subjectGroupClassSectionsId,
                'name' => $lessonName,
            ]);
            $lessonId = (int) $this->CI->db->insert_id();
            $this->registerInsert('lesson', $lessonId);
        }

        $topicName = 'Pengantar Limit';
        $topic = $this->CI->db
            ->where('lesson_id', $lessonId)
            ->where('name', $topicName)
            ->get('topic')
            ->row_array();

        if ($topic) {
            $topicId = (int) $topic['id'];
        } else {
            $this->CI->db->insert('topic', [
                'session_id' => $this->sessionId,
                'lesson_id' => $lessonId,
                'name' => $topicName,
                'status' => 1,
                'complete_date' => date('Y-m-d', strtotime('+30 days')),
            ]);
            $topicId = (int) $this->CI->db->insert_id();
            $this->registerInsert('topic', $topicId);
        }

        $staffId = $this->staffIds[0] ?? 0;
        $syllabus = $this->CI->db
            ->where('topic_id', $topicId)
            ->where('session_id', $this->sessionId)
            ->get('subject_syllabus')
            ->row_array();

        if (!$syllabus) {
            $this->CI->db->insert('subject_syllabus', [
                'topic_id' => $topicId,
                'session_id' => $this->sessionId,
                'created_by' => $staffId,
                'created_for' => $staffId,
                'date' => date('Y-m-d'),
                'time_from' => '08:00',
                'time_to' => '09:00',
                'presentation' => 'Pengenalan konsep limit.',
                'attachment' => '',
                'lacture_youtube_url' => 'https://example.com',
                'lacture_video' => '',
                'sub_topic' => 'Definisi limit',
                'teaching_method' => 'Diskusi',
                'general_objectives' => 'Memahami konsep limit.',
                'previous_knowledge' => 'Aljabar dasar.',
                'comprehensive_questions' => 'Hitung limit sederhana.',
                'status' => 1,
            ]);
            $this->registerInsert('subject_syllabus', (int) $this->CI->db->insert_id());
        }
    }

    private function seedStudentSubjectAttendance()
    {
        if (!$this->tableExists('student_subject_attendances') || empty($this->studentSessions)) {
            return;
        }

        $subjectTimetableId = $this->getFirstSubjectTimetableId();
        if (!$subjectTimetableId && $this->tableExists('subject_timetable')) {
            $subjectTimetable = $this->CI->db->select('id')->limit(1)->get('subject_timetable')->row_array();
            $subjectTimetableId = $subjectTimetable['id'] ?? null;
        }

        if (!$subjectTimetableId) {
            return;
        }

        $date = date('Y-m-d', strtotime('-1 day'));
        foreach ($this->studentSessions as $studentSession) {
            $exists = $this->CI->db
                ->where('student_session_id', $studentSession['id'])
                ->where('subject_timetable_id', $subjectTimetableId)
                ->where('date', $date)
                ->get('student_subject_attendances')
                ->row_array();

            if (!$exists) {
                $this->CI->db->insert('student_subject_attendances', [
                    'student_session_id' => $studentSession['id'],
                    'subject_timetable_id' => $subjectTimetableId,
                    'attendence_type_id' => 1,
                    'date' => $date,
                    'remark' => 'Hadir',
                ]);
                $this->registerInsert('student_subject_attendances', (int) $this->CI->db->insert_id());
            }
        }
    }

    private function seedHomeworkEvaluation()
    {
        if (!$this->tableExists('homework')) {
            return;
        }

        $hasEvaluation = $this->tableExists('homework_evaluation');
        $hasSubmission = $this->tableExists('submit_assignment');
        if (!$hasEvaluation && !$hasSubmission) {
            return;
        }

        foreach ($this->studentSessions as $studentId => $studentSession) {
            $classId = $studentSession['class_id'] ?? null;
            $sectionId = $studentSession['section_id'] ?? null;
            if (!$classId || !$sectionId) {
                continue;
            }

            $homework = $this->CI->db
                ->where('class_id', $classId)
                ->where('section_id', $sectionId)
                ->order_by('id', 'DESC')
                ->get('homework')
                ->row_array();

            if (!$homework) {
                continue;
            }

            $homeworkId = (int) $homework['id'];

            if ($hasSubmission) {
                $submit = $this->CI->db
                    ->where('homework_id', $homeworkId)
                    ->where('student_id', $studentId)
                    ->get('submit_assignment')
                    ->row_array();

                if (!$submit) {
                    $this->CI->db->insert('submit_assignment', [
                        'homework_id' => $homeworkId,
                        'student_id' => $studentId,
                        'message' => 'Tugas sudah dikumpulkan.',
                        'docs' => 'dummy.txt',
                        'file_name' => 'dummy.txt',
                    ]);
                    $this->registerInsert('submit_assignment', (int) $this->CI->db->insert_id());
                }
            }

            if ($hasEvaluation) {
                $evaluation = $this->CI->db
                    ->where('homework_id', $homeworkId)
                    ->where('student_id', $studentId)
                    ->get('homework_evaluation')
                    ->row_array();

                if (!$evaluation) {
                    $this->CI->db->insert('homework_evaluation', [
                        'homework_id' => $homeworkId,
                        'student_id' => $studentId,
                        'student_session_id' => $studentSession['id'],
                        'marks' => 90,
                        'note' => 'Bagus.',
                        'date' => date('Y-m-d'),
                        'status' => 'checked',
                    ]);
                    $this->registerInsert('homework_evaluation', (int) $this->CI->db->insert_id());
                }
            }
        }
    }

    private function seedExamGroups()
    {
        if (
            !$this->tableExists('exam_groups')
            || !$this->tableExists('exam_group_class_batch_exams')
            || !$this->tableExists('exam_group_exam_connections')
            || !$this->tableExists('exam_group_class_batch_exam_subjects')
            || !$this->tableExists('exam_group_students')
            || !$this->tableExists('exam_group_class_batch_exam_students')
            || !$this->tableExists('exam_group_exam_results')
        ) {
            return;
        }

        $groupId = $this->insertRow('exam_groups', ['name' => 'Ujian Semester 1'], [
            'name' => 'Ujian Semester 1',
            'exam_type' => 'basic_system',
            'description' => 'Contoh ujian semester',
            'is_active' => 1,
        ]);

        if (!$groupId) {
            return;
        }

        $exam = $this->CI->db
            ->where('exam_group_id', $groupId)
            ->where('session_id', $this->sessionId)
            ->where('exam', 'UTS Semester 1')
            ->get('exam_group_class_batch_exams')
            ->row_array();

        if ($exam) {
            $examId = (int) $exam['id'];
        } else {
            $this->CI->db->insert('exam_group_class_batch_exams', [
                'exam' => 'UTS Semester 1',
                'passing_percentage' => 60,
                'session_id' => $this->sessionId,
                'date_from' => date('Y-m-d', strtotime('+10 days')),
                'date_to' => date('Y-m-d', strtotime('+15 days')),
                'exam_group_id' => $groupId,
                'use_exam_roll_no' => 1,
                'is_publish' => 1,
                'is_rank_generated' => 0,
                'description' => 'Ujian tengah semester',
                'is_active' => 1,
            ]);
            $examId = (int) $this->CI->db->insert_id();
            $this->registerInsert('exam_group_class_batch_exams', $examId);
        }

        $connection = $this->CI->db
            ->where('exam_group_id', $groupId)
            ->where('exam_group_class_batch_exams_id', $examId)
            ->get('exam_group_exam_connections')
            ->row_array();

        if (!$connection) {
            $this->CI->db->insert('exam_group_exam_connections', [
                'exam_group_id' => $groupId,
                'exam_group_class_batch_exams_id' => $examId,
                'exam_weightage' => 100,
                'is_active' => 1,
            ]);
            $this->registerInsert('exam_group_exam_connections', (int) $this->CI->db->insert_id());
        }

        $subjectIds = array_values($this->subjectMap);
        $subjectIds = array_slice($subjectIds, 0, 2);
        $examSubjectIds = [];

        foreach ($subjectIds as $index => $subjectId) {
            $examSubject = $this->CI->db
                ->where('exam_group_class_batch_exams_id', $examId)
                ->where('subject_id', $subjectId)
                ->get('exam_group_class_batch_exam_subjects')
                ->row_array();

            if ($examSubject) {
                $examSubjectId = (int) $examSubject['id'];
            } else {
                $startTime = $index === 0 ? '08:00' : '10:00';
                $this->CI->db->insert('exam_group_class_batch_exam_subjects', [
                    'exam_group_class_batch_exams_id' => $examId,
                    'subject_id' => $subjectId,
                    'date_from' => date('Y-m-d', strtotime('+10 days')),
                    'time_from' => $startTime,
                    'duration' => '90',
                    'room_no' => 'AULA',
                    'max_marks' => 100,
                    'min_marks' => 60,
                    'credit_hours' => 2,
                    'date_to' => date('Y-m-d H:i:s', strtotime('+10 days')),
                    'is_active' => 1,
                ]);
                $examSubjectId = (int) $this->CI->db->insert_id();
                $this->registerInsert('exam_group_class_batch_exam_subjects', $examSubjectId);
            }

            $examSubjectIds[] = $examSubjectId;
        }

        if (empty($examSubjectIds)) {
            return;
        }

        $examGroupStudentIds = [];
        $examStudentIds = [];
        $rollNo = 1;

        foreach ($this->studentSessions as $studentId => $studentSession) {
            $groupStudent = $this->CI->db
                ->where('exam_group_id', $groupId)
                ->where('student_id', $studentId)
                ->get('exam_group_students')
                ->row_array();

            if ($groupStudent) {
                $examGroupStudentId = (int) $groupStudent['id'];
            } else {
                $this->CI->db->insert('exam_group_students', [
                    'exam_group_id' => $groupId,
                    'student_id' => $studentId,
                    'student_session_id' => $studentSession['id'],
                    'is_active' => 1,
                ]);
                $examGroupStudentId = (int) $this->CI->db->insert_id();
                $this->registerInsert('exam_group_students', $examGroupStudentId);
            }
            $examGroupStudentIds[$studentId] = $examGroupStudentId;

            $examStudent = $this->CI->db
                ->where('exam_group_class_batch_exam_id', $examId)
                ->where('student_id', $studentId)
                ->get('exam_group_class_batch_exam_students')
                ->row_array();

            if ($examStudent) {
                $examStudentId = (int) $examStudent['id'];
            } else {
                $this->CI->db->insert('exam_group_class_batch_exam_students', [
                    'exam_group_class_batch_exam_id' => $examId,
                    'student_id' => $studentId,
                    'student_session_id' => $studentSession['id'],
                    'roll_no' => $rollNo,
                    'teacher_remark' => 'Nilai dummy',
                    'rank' => 0,
                    'is_active' => 1,
                ]);
                $examStudentId = (int) $this->CI->db->insert_id();
                $this->registerInsert('exam_group_class_batch_exam_students', $examStudentId);
            }

            $examStudentIds[$studentId] = $examStudentId;
            $rollNo++;
        }

        foreach ($examStudentIds as $studentId => $examStudentId) {
            $examGroupStudentId = $examGroupStudentIds[$studentId] ?? null;
            foreach ($examSubjectIds as $examSubjectId) {
                $result = $this->CI->db
                    ->where('exam_group_class_batch_exam_student_id', $examStudentId)
                    ->where('exam_group_class_batch_exam_subject_id', $examSubjectId)
                    ->get('exam_group_exam_results')
                    ->row_array();

                if (!$result) {
                    $this->CI->db->insert('exam_group_exam_results', [
                        'exam_group_class_batch_exam_student_id' => $examStudentId,
                        'exam_group_class_batch_exam_subject_id' => $examSubjectId,
                        'exam_group_student_id' => $examGroupStudentId,
                        'attendence' => 'present',
                        'get_marks' => 80,
                        'note' => 'Nilai dummy',
                        'is_active' => 1,
                    ]);
                    $this->registerInsert('exam_group_exam_results', (int) $this->CI->db->insert_id());
                }
            }
        }
    }

    private function seedOnlineExams()
    {
        if (
            !$this->tableExists('onlineexam')
            || !$this->tableExists('onlineexam_questions')
            || !$this->tableExists('onlineexam_students')
            || !$this->tableExists('onlineexam_student_results')
            || !$this->tableExists('onlineexam_attempts')
            || !$this->tableExists('questions')
        ) {
            return;
        }

        $subjectId = $this->getFirstSubjectId();
        if (!$subjectId) {
            return;
        }

        $classId = null;
        $sectionId = null;
        foreach ($this->studentSessions as $studentSession) {
            $classId = $studentSession['class_id'] ?? null;
            $sectionId = $studentSession['section_id'] ?? null;
            break;
        }

        if (!$classId || !$sectionId) {
            return;
        }

        $classSectionId = $this->classSectionMap[$classId . ':' . $sectionId] ?? null;
        $staffId = $this->staffIds[0] ?? 0;

        $question = $this->CI->db
            ->where('question', 'Berapa 2 + 2 ?')
            ->where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->get('questions')
            ->row_array();

        if ($question) {
            $questionId = (int) $question['id'];
        } else {
            $this->CI->db->insert('questions', [
                'staff_id' => $staffId,
                'subject_id' => $subjectId,
                'question_type' => 'singlechoice',
                'level' => 'easy',
                'class_id' => $classId,
                'section_id' => $sectionId,
                'class_section_id' => $classSectionId,
                'question' => 'Berapa 2 + 2 ?',
                'opt_a' => '3',
                'opt_b' => '4',
                'opt_c' => '5',
                'opt_d' => '',
                'opt_e' => '',
                'correct' => 'opt_b',
                'descriptive_word_limit' => 0,
            ]);
            $questionId = (int) $this->CI->db->insert_id();
            $this->registerInsert('questions', $questionId);
        }

        $exam = $this->CI->db
            ->where('exam', 'Quiz Matematika')
            ->where('session_id', $this->sessionId)
            ->get('onlineexam')
            ->row_array();

        if ($exam) {
            $onlineExamId = (int) $exam['id'];
        } else {
            $this->CI->db->insert('onlineexam', [
                'session_id' => $this->sessionId,
                'exam' => 'Quiz Matematika',
                'attempt' => 1,
                'exam_from' => date('Y-m-d H:i:s'),
                'exam_to' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'is_quiz' => 1,
                'auto_publish_date' => date('Y-m-d H:i:s', strtotime('+8 days')),
                'time_from' => '08:00:00',
                'time_to' => '08:30:00',
                'duration' => '00:30:00',
                'passing_percentage' => 60,
                'description' => 'Quiz singkat matematika.',
                'publish_result' => 1,
                'answer_word_count' => -1,
                'is_active' => '1',
                'is_marks_display' => 1,
                'is_neg_marking' => 0,
                'is_random_question' => 0,
                'is_rank_generated' => 1,
                'publish_exam_notification' => 0,
                'publish_result_notification' => 0,
            ]);
            $onlineExamId = (int) $this->CI->db->insert_id();
            $this->registerInsert('onlineexam', $onlineExamId);
        }

        $examQuestion = $this->CI->db
            ->where('onlineexam_id', $onlineExamId)
            ->where('question_id', $questionId)
            ->get('onlineexam_questions')
            ->row_array();

        if ($examQuestion) {
            $examQuestionId = (int) $examQuestion['id'];
        } else {
            $this->CI->db->insert('onlineexam_questions', [
                'question_id' => $questionId,
                'onlineexam_id' => $onlineExamId,
                'session_id' => $this->sessionId,
                'marks' => 10,
                'neg_marks' => 0,
                'is_active' => '1',
            ]);
            $examQuestionId = (int) $this->CI->db->insert_id();
            $this->registerInsert('onlineexam_questions', $examQuestionId);
        }

        $attempted = false;
        foreach ($this->studentSessions as $studentSession) {
            $examStudent = $this->CI->db
                ->where('onlineexam_id', $onlineExamId)
                ->where('student_session_id', $studentSession['id'])
                ->get('onlineexam_students')
                ->row_array();

            if ($examStudent) {
                $onlineExamStudentId = (int) $examStudent['id'];
            } else {
                $this->CI->db->insert('onlineexam_students', [
                    'onlineexam_id' => $onlineExamId,
                    'student_session_id' => $studentSession['id'],
                    'is_attempted' => $attempted ? 0 : 1,
                    'rank' => $attempted ? 0 : 1,
                    'quiz_attempted' => $attempted ? 0 : 1,
                ]);
                $onlineExamStudentId = (int) $this->CI->db->insert_id();
                $this->registerInsert('onlineexam_students', $onlineExamStudentId);
            }

            if (!$attempted) {
                $attempt = $this->CI->db
                    ->where('onlineexam_student_id', $onlineExamStudentId)
                    ->get('onlineexam_attempts')
                    ->row_array();

                if (!$attempt) {
                    $this->CI->db->insert('onlineexam_attempts', [
                        'onlineexam_student_id' => $onlineExamStudentId,
                    ]);
                    $this->registerInsert('onlineexam_attempts', (int) $this->CI->db->insert_id());
                }

                $result = $this->CI->db
                    ->where('onlineexam_student_id', $onlineExamStudentId)
                    ->where('onlineexam_question_id', $examQuestionId)
                    ->get('onlineexam_student_results')
                    ->row_array();

                if (!$result) {
                    $this->CI->db->insert('onlineexam_student_results', [
                        'onlineexam_student_id' => $onlineExamStudentId,
                        'onlineexam_question_id' => $examQuestionId,
                        'select_option' => 'opt_b',
                        'marks' => 10,
                        'remark' => 'Jawaban benar.',
                        'attachment_name' => '',
                        'attachment_upload_name' => null,
                    ]);
                    $this->registerInsert('onlineexam_student_results', (int) $this->CI->db->insert_id());
                }

                $attempted = true;
            }
        }
    }

    private function seedTransport()
    {
        if (
            !$this->tableExists('transport_route')
            || !$this->tableExists('vehicles')
            || !$this->tableExists('vehicle_routes')
            || !$this->tableExists('pickup_point')
            || !$this->tableExists('route_pickup_point')
            || !$this->tableExists('transport_feemaster')
            || !$this->tableExists('student_transport_fees')
        ) {
            return;
        }

        $routeId = $this->insertRow('transport_route', ['route_title' => 'Rute Utara'], [
            'route_title' => 'Rute Utara',
            'no_of_vehicle' => 1,
            'note' => 'Rute dummy',
            'is_active' => 'yes',
        ]);

        $vehicleId = $this->insertRow('vehicles', ['vehicle_no' => 'D 1234 AB'], [
            'vehicle_no' => 'D 1234 AB',
            'vehicle_model' => 'Microbus',
            'vehicle_photo' => 'default_vehicle.jpg',
            'manufacture_year' => '2020',
            'registration_number' => 'REG-1234',
            'chasis_number' => 'CHS-0001',
            'max_seating_capacity' => '30',
            'driver_name' => 'Pak Joko',
            'driver_licence' => 'SIM B1',
            'driver_contact' => '08123456789',
            'note' => 'Kendaraan dummy',
        ]);

        $vehicleRoute = $this->CI->db
            ->where('route_id', $routeId)
            ->where('vehicle_id', $vehicleId)
            ->get('vehicle_routes')
            ->row_array();

        if ($vehicleRoute) {
            $vehicleRouteId = (int) $vehicleRoute['id'];
        } else {
            $this->CI->db->insert('vehicle_routes', [
                'route_id' => $routeId,
                'vehicle_id' => $vehicleId,
            ]);
            $vehicleRouteId = (int) $this->CI->db->insert_id();
            $this->registerInsert('vehicle_routes', $vehicleRouteId);
        }

        $pickupPointId = $this->insertRow('pickup_point', ['name' => 'Gerbang Utama'], [
            'name' => 'Gerbang Utama',
            'latitude' => '-6.9147',
            'longitude' => '107.6098',
        ]);

        $routePickup = $this->CI->db
            ->where('transport_route_id', $routeId)
            ->where('pickup_point_id', $pickupPointId)
            ->get('route_pickup_point')
            ->row_array();

        if ($routePickup) {
            $routePickupId = (int) $routePickup['id'];
            $routeFee = (float) ($routePickup['fees'] ?? 0);
        } else {
            $routeFee = 150000;
            $this->CI->db->insert('route_pickup_point', [
                'transport_route_id' => $routeId,
                'pickup_point_id' => $pickupPointId,
                'fees' => $routeFee,
                'destination_distance' => 5.0,
                'pickup_time' => '07:00:00',
                'order_number' => 1,
            ]);
            $routePickupId = (int) $this->CI->db->insert_id();
            $this->registerInsert('route_pickup_point', $routePickupId);
        }

        $feeMasterId = $this->insertRow('transport_feemaster', [
            'session_id' => $this->sessionId,
            'month' => 'Juli',
        ], [
            'session_id' => $this->sessionId,
            'month' => 'Juli',
            'due_date' => date('Y-m-d', strtotime('+20 days')),
            'fine_amount' => 0,
            'fine_type' => 'none',
            'fine_percentage' => 0,
        ]);

        foreach ($this->studentSessions as $studentSession) {
            $transportFee = $this->CI->db
                ->where('student_session_id', $studentSession['id'])
                ->where('route_pickup_point_id', $routePickupId)
                ->where('transport_feemaster_id', $feeMasterId)
                ->get('student_transport_fees')
                ->row_array();

            if (!$transportFee) {
                $this->CI->db->insert('student_transport_fees', [
                    'transport_feemaster_id' => $feeMasterId,
                    'student_session_id' => $studentSession['id'],
                    'route_pickup_point_id' => $routePickupId,
                    'generated_by' => 0,
                ]);
                $this->registerInsert('student_transport_fees', (int) $this->CI->db->insert_id());
            }

            $this->CI->db->where('id', $studentSession['id'])->update('student_session', [
                'route_pickup_point_id' => $routePickupId,
                'vehroute_id' => $vehicleRouteId,
                'transport_fees' => $routeFee,
            ]);
        }
    }

    private function seedHostel()
    {
        if (
            !$this->tableExists('hostel')
            || !$this->tableExists('room_types')
            || !$this->tableExists('hostel_rooms')
        ) {
            return;
        }

        $roomTypeId = $this->insertRow('room_types', ['room_type' => 'Standard'], [
            'room_type' => 'Standard',
            'description' => 'Kamar standar',
        ]);

        $hostelId = $this->insertRow('hostel', ['hostel_name' => 'Asrama Putra'], [
            'hostel_name' => 'Asrama Putra',
            'type' => 'Boys',
            'address' => 'Jl. Asrama 1',
            'intake' => 50,
            'description' => 'Asrama dummy',
            'is_active' => 'yes',
        ]);

        $room = $this->CI->db
            ->where('hostel_id', $hostelId)
            ->where('room_no', '101')
            ->get('hostel_rooms')
            ->row_array();

        if ($room) {
            $roomId = (int) $room['id'];
        } else {
            $this->CI->db->insert('hostel_rooms', [
                'hostel_id' => $hostelId,
                'room_type_id' => $roomTypeId,
                'room_no' => '101',
                'no_of_bed' => 4,
                'cost_per_bed' => 300000,
                'title' => 'Kamar 101',
                'description' => 'Kamar standar',
            ]);
            $roomId = (int) $this->CI->db->insert_id();
            $this->registerInsert('hostel_rooms', $roomId);
        }

        $studentId = array_key_first($this->studentSessions);
        if ($studentId) {
            $this->CI->db->where('id', $studentId)->update('students', ['hostel_room_id' => $roomId]);
            $studentSession = $this->studentSessions[$studentId] ?? null;
            if ($studentSession && $this->tableExists('student_session')) {
                $this->CI->db->where('id', $studentSession['id'])->update('student_session', [
                    'hostel_room_id' => $roomId,
                ]);
            }
        }
    }

    private function seedInventory()
    {
        if (
            !$this->tableExists('item_category')
            || !$this->tableExists('item_store')
            || !$this->tableExists('item_supplier')
            || !$this->tableExists('item')
            || !$this->tableExists('item_stock')
        ) {
            return;
        }

        $categoryId = $this->insertRow('item_category', ['item_category' => 'ATK'], [
            'item_category' => 'ATK',
            'description' => 'Alat tulis kantor',
            'is_active' => 'yes',
        ]);

        $storeId = $this->insertRow('item_store', ['item_store' => 'Gudang Utama'], [
            'item_store' => 'Gudang Utama',
            'code' => 'GDG',
            'description' => 'Gudang sekolah',
        ]);

        $supplierId = $this->insertRow('item_supplier', ['item_supplier' => 'CV Sumber Ilmu'], [
            'item_supplier' => 'CV Sumber Ilmu',
            'phone' => '022123456',
            'email' => 'supplier@dummy.test',
            'address' => 'Jl. Pustaka 10',
            'contact_person_name' => 'Dedi',
            'contact_person_phone' => '08123456780',
            'contact_person_email' => 'dedi@dummy.test',
            'description' => 'Supplier alat tulis',
        ]);

        $item = $this->CI->db
            ->where('name', 'Buku Tulis')
            ->get('item')
            ->row_array();

        if ($item) {
            $itemId = (int) $item['id'];
        } else {
            $this->CI->db->insert('item', [
                'item_category_id' => $categoryId,
                'item_store_id' => $storeId,
                'item_supplier_id' => $supplierId,
                'name' => 'Buku Tulis',
                'unit' => 'pcs',
                'item_photo' => '',
                'description' => 'Buku tulis siswa',
                'quantity' => 100,
                'date' => date('Y-m-d'),
            ]);
            $itemId = (int) $this->CI->db->insert_id();
            $this->registerInsert('item', $itemId);
        }

        $stock = $this->CI->db
            ->where('item_id', $itemId)
            ->where('date', date('Y-m-d'))
            ->get('item_stock')
            ->row_array();

        if (!$stock) {
            $this->CI->db->insert('item_stock', [
                'item_id' => $itemId,
                'supplier_id' => $supplierId,
                'store_id' => $storeId,
                'symbol' => '+',
                'quantity' => 100,
                'purchase_price' => 3000,
                'date' => date('Y-m-d'),
                'attachment' => '',
                'description' => 'Stok awal',
                'is_active' => 'yes',
            ]);
            $this->registerInsert('item_stock', (int) $this->CI->db->insert_id());
        }

        if ($this->tableExists('item_issue')) {
            $studentId = array_key_first($this->studentSessions);
            $staffId = $this->staffIds[0] ?? 0;
            if ($studentId) {
                $issue = $this->CI->db
                    ->where('item_id', $itemId)
                    ->where('issue_to', $studentId)
                    ->get('item_issue')
                    ->row_array();

                if (!$issue) {
                    $this->CI->db->insert('item_issue', [
                        'issue_type' => 'student',
                        'issue_to' => $studentId,
                        'issue_by' => $staffId,
                        'issue_date' => date('Y-m-d'),
                        'return_date' => date('Y-m-d', strtotime('+7 days')),
                        'item_category_id' => $categoryId,
                        'item_id' => $itemId,
                        'quantity' => 1,
                        'note' => 'Peminjaman buku tulis',
                        'is_returned' => 0,
                        'is_active' => 'yes',
                    ]);
                    $this->registerInsert('item_issue', (int) $this->CI->db->insert_id());
                }
            }
        }
    }

    private function seedIncomeExpenses()
    {
        if (
            !$this->tableExists('income_head')
            || !$this->tableExists('income')
            || !$this->tableExists('expense_head')
            || !$this->tableExists('expenses')
        ) {
            return;
        }

        $incomeHeadId = $this->insertRow('income_head', ['income_category' => 'Donasi'], [
            'income_category' => 'Donasi',
            'description' => 'Pemasukan dummy',
            'is_active' => 'yes',
            'is_deleted' => 'no',
        ]);

        $expenseHeadId = $this->insertRow('expense_head', ['exp_category' => 'Operasional'], [
            'exp_category' => 'Operasional',
            'description' => 'Pengeluaran dummy',
            'is_active' => 'yes',
            'is_deleted' => 'no',
        ]);

        $income = $this->CI->db
            ->where('income_head_id', $incomeHeadId)
            ->where('invoice_no', 'INC-001')
            ->get('income')
            ->row_array();

        if (!$income) {
            $this->CI->db->insert('income', [
                'income_head_id' => $incomeHeadId,
                'name' => 'Donasi Alumni',
                'invoice_no' => 'INC-001',
                'date' => date('Y-m-d'),
                'amount' => 1500000,
                'note' => 'Pemasukan contoh',
                'is_active' => 'yes',
                'documents' => '',
                'is_deleted' => 'no',
            ]);
            $this->registerInsert('income', (int) $this->CI->db->insert_id());
        }

        $expense = $this->CI->db
            ->where('exp_head_id', $expenseHeadId)
            ->where('invoice_no', 'EXP-001')
            ->get('expenses')
            ->row_array();

        if (!$expense) {
            $this->CI->db->insert('expenses', [
                'exp_head_id' => $expenseHeadId,
                'name' => 'Pembelian ATK',
                'invoice_no' => 'EXP-001',
                'date' => date('Y-m-d'),
                'amount' => 500000,
                'documents' => '',
                'note' => 'Pengeluaran contoh',
                'is_active' => 'yes',
                'is_deleted' => 'no',
            ]);
            $this->registerInsert('expenses', (int) $this->CI->db->insert_id());
        }
    }

    private function seedStaffAttendance()
    {
        if (!$this->tableExists('staff_attendance') || !$this->tableExists('staff_attendance_type')) {
            return;
        }

        $typeId = $this->insertRow('staff_attendance_type', ['type' => 'Present'], [
            'type' => 'Present',
            'key_value' => 'P',
            'is_active' => 'yes',
            'for_qr_attendance' => 1,
            'long_lang_name' => 'present',
            'long_name_style' => 'label label-success',
        ]);

        $date = date('Y-m-d', strtotime('-1 day'));
        foreach ($this->staffIds as $staffId) {
            $attendance = $this->CI->db
                ->where('staff_id', $staffId)
                ->where('date', $date)
                ->get('staff_attendance')
                ->row_array();

            if (!$attendance) {
                $this->CI->db->insert('staff_attendance', [
                    'date' => $date,
                    'staff_id' => $staffId,
                    'staff_attendance_type_id' => $typeId,
                    'biometric_attendence' => 0,
                    'qrcode_attendance' => 0,
                    'biometric_device_data' => '',
                    'user_agent' => 'Seeder',
                    'remark' => 'Hadir',
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->registerInsert('staff_attendance', (int) $this->CI->db->insert_id());
            }
        }
    }

    private function seedEvents()
    {
        if (!$this->tableExists('events')) {
            return;
        }

        $event = $this->CI->db
            ->where('event_title', 'Workshop Guru')
            ->get('events')
            ->row_array();

        if (!$event) {
            $this->CI->db->insert('events', [
                'event_title' => 'Workshop Guru',
                'event_description' => 'Pelatihan peningkatan kompetensi guru.',
                'start_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
                'end_date' => date('Y-m-d H:i:s', strtotime('+3 days +3 hours')),
                'event_type' => 'Academic',
                'event_color' => '#f4b400',
                'event_for' => 'all',
                'role_id' => null,
                'is_active' => 'yes',
            ]);
            $this->registerInsert('events', (int) $this->CI->db->insert_id());
        }
    }

    private function seedStudentTimeline()
    {
        if (!$this->tableExists('student_timeline')) {
            return;
        }

        $studentId = array_key_first($this->studentSessions);
        if (!$studentId) {
            return;
        }

        $timeline = $this->CI->db
            ->where('student_id', $studentId)
            ->where('title', 'Orientasi Sekolah')
            ->get('student_timeline')
            ->row_array();

        if (!$timeline) {
            $this->CI->db->insert('student_timeline', [
                'student_id' => $studentId,
                'title' => 'Orientasi Sekolah',
                'timeline_date' => date('Y-m-d', strtotime('-7 days')),
                'description' => 'Kegiatan orientasi siswa baru.',
                'document' => '',
                'status' => 'visible',
                'created_student_id' => $studentId,
                'date' => date('Y-m-d'),
            ]);
            $this->registerInsert('student_timeline', (int) $this->CI->db->insert_id());
        }
    }

    private function seedStaffTimeline()
    {
        if (!$this->tableExists('staff_timeline')) {
            return;
        }

        $staffId = $this->staffIds[0] ?? 0;
        if (!$staffId) {
            return;
        }

        $timeline = $this->CI->db
            ->where('staff_id', $staffId)
            ->where('title', 'Pelatihan Internal')
            ->get('staff_timeline')
            ->row_array();

        if (!$timeline) {
            $this->CI->db->insert('staff_timeline', [
                'staff_id' => $staffId,
                'title' => 'Pelatihan Internal',
                'timeline_date' => date('Y-m-d', strtotime('-14 days')),
                'description' => 'Pelatihan penggunaan LMS sekolah.',
                'document' => '',
                'status' => 'visible',
                'date' => date('Y-m-d'),
            ]);
            $this->registerInsert('staff_timeline', (int) $this->CI->db->insert_id());
        }
    }

    private function splitClassSectionKey($key)
    {
        $parts = explode(':', (string) $key);
        if (count($parts) !== 2) {
            return [null, null];
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    private function getFirstMapValue(array $map)
    {
        foreach ($map as $value) {
            return $value;
        }

        return null;
    }

    private function getFirstSubjectId()
    {
        return $this->getFirstMapValue($this->subjectMap);
    }

    private function getFirstSubjectGroupSubjectId()
    {
        return $this->getFirstMapValue($this->subjectGroupSubjectMap);
    }

    private function getFirstSubjectGroupClassSectionId()
    {
        return $this->getFirstMapValue($this->subjectGroupClassSectionMap);
    }

    private function getFirstSubjectTimetableId()
    {
        return $this->getFirstMapValue($this->subjectTimetableIds);
    }

    private function appendChilds($existing, $studentId)
    {
        $list = array_filter(array_map('trim', explode(',', (string) $existing)));
        $studentId = (string) $studentId;

        if (!in_array($studentId, $list, true)) {
            $list[] = $studentId;
        }

        return implode(',', $list);
    }

    private function insertRow($table, array $unique, array $data)
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        $existing = $this->CI->db->get_where($table, $unique)->row_array();
        if ($existing) {
            return (int) $existing['id'];
        }

        $this->CI->db->insert($table, $data);
        $id = (int) $this->CI->db->insert_id();
        $this->registerInsert($table, $id);
        return $id;
    }

    private function ensureRegistryTable()
    {
        if ($this->registryEnsured) {
            return;
        }

        $this->CI->db->query(
            'CREATE TABLE IF NOT EXISTS dummy_seed_registry (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                table_name VARCHAR(128) NOT NULL,
                row_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX(table_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
        );

        $this->registryEnsured = true;
    }

    private function registerInsert($table, $rowId)
    {
        if (!$rowId) {
            return;
        }

        $this->CI->db->insert('dummy_seed_registry', [
            'table_name' => $table,
            'row_id' => $rowId,
        ]);
    }

    private function purgeRegistryRows()
    {
        $rows = $this->CI->db
            ->order_by('id', 'DESC')
            ->get('dummy_seed_registry')
            ->result_array();

        foreach ($rows as $row) {
            if ($this->tableExists($row['table_name'])) {
                $this->CI->db->where('id', $row['row_id'])->delete($row['table_name']);
            }
        }

        $this->CI->db->truncate('dummy_seed_registry');
    }

    private function ensureActiveSession($preferredSession)
    {
        if ($this->tableExists('sessions')) {
            $this->CI->db->where('id', $preferredSession)->update('sessions', ['is_active' => 'yes']);
        }

        if ($this->tableExists('sch_settings')) {
            $this->CI->db->update('sch_settings', ['session_id' => $preferredSession]);
        }

        return $preferredSession;
    }

    private function tableExists($table)
    {
        return $this->CI->db->table_exists($table);
    }
}
