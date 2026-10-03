<?php
/**
 * LSU Portal — Excel Database Engine (ExcelDB)
 * 
 * Provides Excel spreadsheet file storage (.csv with UTF-8 BOM) for high
 * performance, atomic persistence, and direct compatibility with Microsoft Excel.
 * Configured for Tando Allahyar District (SELD, Sindh).
 */

if (!defined('DATA_PATH')) {
    define('DATA_PATH', dirname(__DIR__) . '/data');
}

class ExcelDB {
    private static string $dataDir = DATA_PATH;

    /**
     * Define default schemas and initial seed datasets for Tando Allahyar district.
     */
    private static array $schemas = [
        'settings' => [
            'headers' => ['key', 'value', 'updated_at'],
            'seeds' => [
                ['district', 'Tando Allahyar District', '2026-09-19 22:00:00'],
                ['app_name', 'District RSU Portal', '2026-09-19 22:00:00'],
                ['department', 'School Education & Literacy Department', '2026-09-19 22:00:00'],
                ['govt', 'Government of Sindh', '2026-09-19 22:00:00'],
                ['academic_year', '2026-2027', '2026-09-19 22:00:00'],
                ['attendance_target', '95', '2026-09-19 22:00:00'],
                ['high_risk_threshold', '60', '2026-09-19 22:00:00'],
                ['contact_email', 'deo.tandoallahyar@seld.gos.pk', '2026-09-19 22:00:00'],
                ['contact_phone', '+92 22 3892401', '2026-09-19 22:00:00'],
            ]
        ],
        'schools' => [
            'headers' => ['id', 'semis_code', 'school_name', 'head_master', 'cnic', 'phone', 'address', 'level', 'gender', 'taluka', 'enrollment', 'enrollment_boys', 'enrollment_girls', 'attendance_pct', 'status', 'status_badge', 'classrooms', 'teachers', 'non_teaching', 'facility_electricity', 'facility_water', 'facility_toilets', 'facility_boundary_wall', 'facility_internet', 'building_structure', 'drainage_sewerage', 'flood_prone', 'furniture_condition'],
            'seeds' => [
                ['1', '403010001', 'Government Primary School Model City, Tando Allahyar', 'Muhammad Ishaq Memon', '41302-1849201-3', '+92 300 1234567', 'Station Road, City Area, Tando Allahyar', 'Primary', 'Co-education', 'Tando Allahyar', '342', '180', '162', '93%', 'Good', 'badge-good', '6', '8', '2', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G', 'Good Condition', 'Functional Drainage', 'No', 'Adequate'],
                ['2', '403010002', 'Government Girls Elementary School B, Jhando Mari', 'Nusrat Parveen Laghari', '41302-7654321-2', '+92 301 2345678', 'Main Bazar, Jhando Mari', 'Middle', 'Girls', 'Jhando Mari', '218', '0', '218', '84%', 'Needs Attention', 'badge-attention', '5', '6', '2', 'Grid Only', 'Handpump / Tap', 'Needs Repair', 'Partial / Damaged', 'Unavailable / None', 'Needs Repair', 'Functional Drainage', 'No', 'Adequate'],
                ['3', '403010003', 'Government High School Main, Chambar', 'Ghulam Sarwar Jarwar', '41302-9876543-1', '+92 302 3456789', 'Station Road, Chambar', 'Secondary', 'Boys', 'Chambar', '567', '567', '0', '96%', 'Active', 'badge-active', '12', '18', '4', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G', 'Good Condition', 'Functional Drainage', 'No', 'Adequate'],
                ['4', '403010004', 'Government Boys Primary School D, Nasarpur', 'Abdul Hameed Soomro', '41302-3456789-5', '+92 303 4567890', 'Mohallah Qazi, Nasarpur', 'Primary', 'Boys', 'Nasarpur', '189', '189', '0', '72%', 'Not Reporting', 'badge-not-rep', '4', '4', '1', 'Unavailable / None', 'Handpump / Tap', 'Needs Repair', 'Unavailable / None', 'Unavailable / None', 'Dangerous / Unsafe', 'Broken / None', 'No', 'Shortage'],
                ['5', '403010005', 'Government Girls Secondary School E, Tando Allahyar', 'Farzana Kousar', '41302-5432167-4', '+92 304 5678901', 'Mir Colony, Tando Allahyar', 'Secondary', 'Girls', 'Tando Allahyar', '412', '0', '412', '91%', 'Good', 'badge-good', '10', '14', '3', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G', 'Good Condition', 'Functional Drainage', 'No', 'Adequate'],
                ['6', '403010006', 'Government Comprehensive Model School F, Tando Allahyar', 'Dr. Rasheed Ahmed Jamali', '41302-6543218-7', '+92 305 6789012', 'Hyderabad Road, Tando Allahyar', 'Higher Secondary', 'Co-education', 'Tando Allahyar', '628', '330', '298', '94%', 'Active', 'badge-active', '16', '24', '5', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G', 'Good Condition', 'Functional Drainage', 'No', 'Adequate'],
                ['7', '403010007', 'Government Primary School G, Pyaro Lund', 'Khadim Hussain Lund', '41302-2345678-9', '+92 306 7890123', 'Village Pyaro Lund, Jhando Mari', 'Primary', 'Co-education', 'Jhando Mari', '155', '85', '70', '88%', 'Good', 'badge-good', '3', '4', '1', 'Solar Only', 'Handpump / Tap', 'Functional Blocks', 'Secured & Complete', 'Partial / Mobile Data', 'Good Condition', 'Partial Drainage', 'Yes', 'Adequate'],
                ['8', '403010008', 'Government Boys High School Missan, Chambar', 'Ali Nawaz Rind', '41302-8765432-1', '+92 307 8901234', 'Missan Stop, Chambar', 'Secondary', 'Boys', 'Chambar', '480', '480', '0', '92%', 'Active', 'badge-active', '9', '12', '3', 'Grid Only', 'Water Supply Line', 'Functional Blocks', 'Secured & Complete', 'Partial / Mobile Data', 'Good Condition', 'Functional Drainage', 'No', 'Adequate'],
                ['9', '403010009', 'Government Girls High School Ceramic Town, Nasarpur', 'Shazia Bano Nizamani', '41302-4567890-8', '+92 308 9012345', 'Ceramic Center Road, Nasarpur', 'Secondary', 'Girls', 'Nasarpur', '310', '0', '310', '89%', 'Good', 'badge-good', '8', '11', '2', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G', 'Good Condition', 'Functional Drainage', 'No', 'Adequate'],
                ['10', '403010010', 'Government Primary School Station Road, Tando Allahyar', 'Mirza Bashir Baig', '41302-1357924-3', '+92 309 0123456', 'Railway Colony, Tando Allahyar', 'Primary', 'Co-education', 'Tando Allahyar', '275', '145', '130', '86%', 'Needs Attention', 'badge-attention', '5', '7', '2', 'Grid Only', 'Handpump / Tap', 'Needs Repair', 'Under Construction', 'Unavailable / None', 'Needs Repair', 'Partial Drainage', 'No', 'Shortage'],
            ]
        ],
        'school_risks' => [
            'headers' => ['id', 'semis_code', 'school_name', 'taluka', 'risk_category', 'severity', 'details', 'reported_date', 'last_inspected', 'status', 'notes'],
            'seeds' => [
                ['1', '403010004', 'Government Boys Primary School D, Nasarpur', 'Nasarpur', 'Dangerous Building Structure', 'Critical', 'Main classroom block has visible structural cracks; roof at risk of collapse during rains', '2026-09-01', '2026-09-15', 'In Progress', 'Engineers from Works Dept. visited; repair order issued'],
                ['2', '403010004', 'Government Boys Primary School D, Nasarpur', 'Nasarpur', 'No Boundary Wall', 'High', 'School has no boundary wall; children exposed to main road traffic', '2026-09-01', '2026-09-15', 'Pending', ''],
                ['3', '403010010', 'Government Primary School Station Road, Tando Allahyar', 'Tando Allahyar', 'Damaged / Incomplete Wall', 'High', 'Boundary wall under construction since 2024; front section missing', '2026-09-05', '2026-09-20', 'In Progress', 'Contractor engaged; estimated completion Oct 2026'],
                ['4', '403010002', 'Government Girls Elementary School B, Jhando Mari', 'Jhando Mari', 'Non-functional Toilets', 'High', 'Girls toilet block damaged and unusable; affecting enrollment and retention', '2026-09-08', '2026-09-22', 'Pending', ''],
                ['5', '403010004', 'Government Boys Primary School D, Nasarpur', 'Nasarpur', 'No Electricity', 'Medium', 'No grid or solar connection; no fans or lighting in classrooms', '2026-09-01', '2026-09-15', 'Pending', 'Solar panel proposal submitted to DEO'],
                ['6', '403010010', 'Government Primary School Station Road, Tando Allahyar', 'Tando Allahyar', 'No Drinking Water', 'Medium', 'Handpump non-functional since June 2026; students bring water from home', '2026-09-05', '2026-09-20', 'Pending', ''],
                ['7', '403010007', 'Government Primary School G, Pyaro Lund', 'Jhando Mari', 'Flood / Disaster Prone Area', 'Critical', 'School flooded 3 times in last 2 years; located in low-lying flood-prone zone', '2026-09-10', '2026-09-25', 'Escalated', 'Referred to PDMA for disaster-resilient reconstruction plan']
            ]
        ],
        'users' => [
            'headers' => ['id', 'username', 'password_hash', 'password_plain', 'full_name', 'role', 'role_title', 'district', 'school_semis', 'cnic', 'status', 'avatar'],
            'seeds' => [
                ['1', 'admin', '$2y$12$HNLhVquFLCVCMiZivNkOP.Feq35F5sR4CCIqGKG7PSKGk9XGjaCqm', 'admin', 'District RSU Coordinator', 'admin', 'District Coordinator / Administrator', 'Tando Allahyar District', '', '', 'active', ''],
                ['2', 'deo_tay', '$2y$12$HNLhVquFLCVCMiZivNkOP.Feq35F5sR4CCIqGKG7PSKGk9XGjaCqm', 'admin', 'District Education Officer Tando Allahyar', 'admin', 'District Education Officer (DEO)', 'Tando Allahyar District', '', '', 'active', ''],
                ['3', '41302-1849201-3', '$2y$10$Q7YmYgH70gE38lOqCgZlEuQW3q2NvyFeqm5q2e0fHkljUfR5n14hG', '1122', 'Muhammad Ishaq Memon (Senior HM)', 'school', 'Head Master', 'Tando Allahyar District', '403010001', '41302-1849201-3', 'active', ''],
                ['4', '41302-9876543-1', '$2y$10$Q7YmYgH70gE38lOqCgZlEuQW3q2NvyFeqm5q2e0fHkljUfR5n14hG', '1122', 'Ghulam Sarwar Jarwar', 'school', 'Principal', 'Tando Allahyar District', '403010003', '41302-9876543-1', 'active', '']
            ]
        ],
        'talukas' => [
            'headers' => ['id', 'taluka_name', 'district', 'created_at'],
            'seeds' => [
                ['1', 'Tando Allahyar', 'Tando Allahyar District', '2026-09-19 22:00:00'],
                ['2', 'Jhando Mari', 'Tando Allahyar District', '2026-09-19 22:00:00'],
                ['3', 'Chambar', 'Tando Allahyar District', '2026-09-19 22:00:00'],
                ['4', 'Nasarpur', 'Tando Allahyar District', '2026-09-19 22:00:00']
            ]
        ],
        'complaints' => [
            'headers' => ['id', 'ticket_no', 'semis_code', 'school_name', 'taluka', 'category', 'priority', 'subject', 'description', 'status', 'created_at', 'updated_at', 'unread_admin', 'unread_school'],
            'seeds' => [
                ['1', 'GRM-2026-0101', '403010001', 'Government Primary School Model City, Tando Allahyar', 'Tando Allahyar', 'SMC Funds & Grants', 'High', 'Delay in SMC 1st Quarter Non-Salary Budget Disbursement', 'The School Management Committee (SMC) first quarter grant has not yet been credited to the designated school bank account. Urgent release needed for white-washing, stationery, and utility bills.', 'Under Review', '2026-09-24 10:15:00', '2026-09-26 14:30:00', '0', '0'],
                ['2', 'GRM-2026-0102', '403010002', 'Government Girls Elementary School B, Jhando Mari', 'Jhando Mari', 'Teacher Shortage & Absenteeism', 'Urgent', 'Severe shortage of Science & English PST/JEST teachers for Grade 6-8', 'Out of 6 sanctioned posts, 2 teachers retired last month. We urgently need 2 JEST (Science/Math) teachers to cover syllabus before midterm exams.', 'Pending', '2026-09-28 09:40:00', '2026-09-28 09:40:00', '1', '0'],
                ['3', 'GRM-2026-0103', '403010004', 'Government Boys Primary School D, Nasarpur', 'Nasarpur', 'Textbooks & Free Supplies', 'Normal', 'Shortage of Sindhi and Math textbooks for Grade 4 and 5', 'Free textbook distribution arrived short by 45 sets of Grade 4 and 30 sets of Grade 5 Sindhi & Math. Students are sharing textbooks.', 'Resolved', '2026-09-15 11:00:00', '2026-09-20 16:15:00', '0', '0'],
                ['4', 'GRM-2026-0104', '403010010', 'Government Primary School Station Road, Tando Allahyar', 'Tando Allahyar', 'Electricity & Water Outage', 'High', 'Transformer malfunction and electric wiring burn in main block', 'Electric pole transformer tripped causing a burnout of classroom main switch. Classes are running in dark and ceiling fans are inoperative.', 'In Progress', '2026-09-29 08:30:00', '2026-09-29 12:00:00', '0', '1'],
            ]
        ],
        'complaint_replies' => [
            'headers' => ['id', 'ticket_no', 'sender_role', 'sender_name', 'message', 'created_at'],
            'seeds' => [
                ['1', 'GRM-2026-0101', 'school', 'Muhammad Ishaq Memon (HM)', 'The School Management Committee (SMC) first quarter grant has not yet been credited to the designated school bank account. Urgent release needed for white-washing, stationery, and utility bills.', '2026-09-24 10:15:00'],
                ['2', 'GRM-2026-0101', 'admin', 'District RSU Coordinator', 'Your grievance has been verified with the District Accounts Office. SMC sanction list #TAY-SMC-2026/04 has been signed by DEO. Funds will reflect in school account within 3 to 4 business days.', '2026-09-26 14:30:00'],
                ['3', 'GRM-2026-0102', 'school', 'Nusrat Parveen Laghari (HM)', 'Out of 6 sanctioned posts, 2 teachers retired last month. We urgently need 2 JEST (Science/Math) teachers to cover syllabus before midterm exams.', '2026-09-28 09:40:00'],
                ['4', 'GRM-2026-0103', 'school', 'Abdul Hameed Soomro (HM)', 'Free textbook distribution arrived short by 45 sets of Grade 4 and 30 sets of Grade 5 Sindhi & Math. Students are sharing textbooks.', '2026-09-15 11:00:00'],
                ['5', 'GRM-2026-0103', 'admin', 'District RSU Coordinator', 'Additional consignment of 80 textbook sets received from Sindh Textbook Board warehouse Jamshoro. Kindly collect your quota from TEVO office Nasarpur on Monday.', '2026-09-18 10:00:00'],
                ['6', 'GRM-2026-0103', 'school', 'Abdul Hameed Soomro (HM)', 'Received and distributed to all students. Thank you RSU team for swift redressal.', '2026-09-20 16:15:00'],
                ['7', 'GRM-2026-0104', 'school', 'Mirza Bashir Baig (HM)', 'Electric pole transformer tripped causing a burnout of classroom main switch. Classes are running in dark and ceiling fans are inoperative.', '2026-09-29 08:30:00'],
                ['8', 'GRM-2026-0104', 'admin', 'District RSU Coordinator', 'HESCO Sub-divisional officer has been officially requested via letter #RSU/EL/2026/89. Work team scheduled for inspection today.', '2026-09-29 12:00:00'],
            ]
        ],
        'school_staff' => [
            'headers' => ['id', 'semis_code', 'personal_no', 'full_name', 'cnic', 'gender', 'staff_type', 'designation', 'bps_scale', 'qualification_academic', 'qualification_professional', 'contact_phone', 'appointment_date', 'status', 'created_at', 'updated_at'],
            'seeds' => [
                ['1', '403010001', '10482910', 'Muhammad Ishaq Memon', '41302-1849201-3', 'Male', 'Teaching', 'Head Master', 'BPS-17', 'MA (English)', 'M.Ed / B.Ed (Hons)', '+92 300 1234567', '2012-03-15', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['2', '403010001', '10482911', 'Ghulam Qadir Soomro', '41302-2345671-1', 'Male', 'Teaching', 'PST (Primary School Teacher)', 'BPS-14', 'BA (General)', 'B.Ed (1.5 Years)', '+92 300 2345678', '2016-08-20', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['3', '403010001', '10482912', 'Zubaida Begum Kazi', '41302-3456782-2', 'Female', 'Teaching', 'ECT (Early Childhood Teacher)', 'BPS-14', 'BS (Education)', 'B.Ed (Hons 4-Years)', '+92 300 3456789', '2020-01-10', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['4', '403010001', '10482913', 'Abdul Majeed Kumbhar', '41302-4567893-3', 'Male', 'Teaching', 'JEST (Junior Elementary)', 'BPS-14', 'B.Sc (Math / Physics)', 'B.Ed (2.5 Years)', '+92 300 4567890', '2022-04-12', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['5', '403010001', '10482914', 'Imran Ali Khaskheli', '41302-5678904-4', 'Male', 'Non-Teaching', 'Junior Clerk', 'BPS-11', 'Intermediate (Commerce)', 'IT Diploma / None', '+92 300 5678901', '2018-06-01', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['6', '403010001', '10482915', 'Khamiso Khan Brohi', '41302-6789015-5', 'Male', 'Non-Teaching', 'Naib Qasid / Peon', 'BPS-02', 'Matriculation', 'None', '+92 300 6789012', '2015-11-25', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['7', '403010002', '10482920', 'Nusrat Parveen Laghari', '41302-7654321-2', 'Female', 'Teaching', 'Head Mistress', 'BPS-17', 'MA (Sindhi)', 'M.Ed / B.Ed', '+92 301 2345678', '2014-02-18', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['8', '403010002', '10482921', 'Shabana Naz Memon', '41302-8765432-3', 'Female', 'Teaching', 'JEST (Science / Math)', 'BPS-14', 'M.Sc (Botany)', 'B.Ed (1.5 Years)', '+92 301 3456789', '2021-09-01', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['9', '403010003', '10482930', 'Ghulam Sarwar Jarwar', '41302-9876543-1', 'Male', 'Teaching', 'Principal / SS', 'BPS-18', 'M.Sc (Chemistry)', 'M.Ed', '+92 302 3456789', '2008-10-10', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['10', '403010003', '10482931', 'Rashid Minhas Solangi', '41302-1234568-7', 'Male', 'Teaching', 'HST (High School Teacher)', 'BPS-16', 'MA (English Lit)', 'B.Ed (Hons)', '+92 302 4567890', '2017-03-05', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
                ['11', '403010003', '10482932', 'Muhammad Hanif Rind', '41302-2345679-8', 'Male', 'Non-Teaching', 'Lab Assistant', 'BPS-07', 'F.Sc (Pre-Medical)', 'None', '+92 302 5678901', '2019-07-20', 'Active', '2026-09-19 22:00:00', '2026-09-19 22:00:00'],
            ]
        ]
    ];

    /**
     * Initializes directory and seeds default CSV tables if missing.
     */
    public static function init(): void {
        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0755, true);
        }

        foreach (self::$schemas as $tableName => $schema) {
            $filePath = self::getFilePath($tableName);
            if (!file_exists($filePath)) {
                self::seedTable($tableName);
            }
        }
    }

    /**
     * Get absolute path for table CSV.
     */
    public static function getFilePath(string $table): string {
        return self::$dataDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $table) . '.csv';
    }

    /**
     * Seeds initial records to table CSV with UTF-8 BOM.
     */
    public static function seedTable(string $table): bool {
        if (!isset(self::$schemas[$table])) {
            return false;
        }

        $schema = self::$schemas[$table];
        $headers = $schema['headers'];
        $rows = [];

        foreach ($schema['seeds'] as $seed) {
            $row = [];
            foreach ($headers as $i => $col) {
                $row[$col] = $seed[$i] ?? '';
            }
            $rows[] = $row;
        }

        return self::writeTable($table, $rows, $headers);
    }

    /**
     * Reads all rows from a table as associative arrays.
     */
    public static function all(string $table): array {
        self::init();
        $filePath = self::getFilePath($table);

        if (!file_exists($filePath)) {
            if (isset(self::$schemas[$table])) {
                self::seedTable($table);
            } else {
                return [];
            }
        }

        $fp = fopen($filePath, 'r');
        if (!$fp) {
            return [];
        }

        @flock($fp, LOCK_SH);

        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $headers = fgetcsv($fp);
        if (!$headers) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return [];
        }

        $cleanHeaders = array_map(function($h) {
            return trim((string)$h);
        }, $headers);

        $results = [];
        while (($data = fgetcsv($fp)) !== false) {
            if (count($data) === 1 && $data[0] === null) {
                continue;
            }
            $row = [];
            foreach ($cleanHeaders as $index => $col) {
                $row[$col] = $data[$index] ?? '';
            }
            $results[] = $row;
        }

        @flock($fp, LOCK_UN);
        fclose($fp);

        return $results;
    }

    /**
     * Writes an entire array of rows back to the table CSV.
     */
    public static function writeTable(string $table, array $rows, array $customHeaders = []): bool {
        $filePath = self::getFilePath($table);

        if (empty($customHeaders)) {
            if (!empty($rows)) {
                $customHeaders = array_keys(reset($rows));
            } elseif (isset(self::$schemas[$table])) {
                $customHeaders = self::$schemas[$table]['headers'];
            } else {
                return false;
            }
        }

        $fp = fopen($filePath, 'w');
        if (!$fp) {
            return false;
        }

        @flock($fp, LOCK_EX);

        // UTF-8 BOM so Microsoft Excel opens it cleanly
        fwrite($fp, "\xEF\xBB\xBF");

        // Header row
        fputcsv($fp, $customHeaders);

        // Data rows
        foreach ($rows as $row) {
            $line = [];
            foreach ($customHeaders as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($fp, $line);
        }

        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    /**
     * Find a single record by field value.
     */
    public static function find(string $table, string $field, string $value): ?array {
        $rows = self::all($table);
        foreach ($rows as $row) {
            if (isset($row[$field]) && (string)$row[$field] === (string)$value) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Insert a new record into table.
     */
    public static function insert(string $table, array $record): string {
        $rows = self::all($table);

        if (!isset($record['id']) || empty($record['id'])) {
            $maxId = 0;
            foreach ($rows as $r) {
                if (isset($r['id']) && is_numeric($r['id']) && (int)$r['id'] > $maxId) {
                    $maxId = (int)$r['id'];
                }
            }
            $record['id'] = (string)($maxId + 1);
        }

        $rows[] = $record;
        self::writeTable($table, $rows);
        return (string)$record['id'];
    }

    /**
     * Update an existing record by key.
     */
    public static function update(string $table, string $keyField, string $keyValue, array $newData): bool {
        $rows = self::all($table);
        $updated = false;

        foreach ($rows as $i => $row) {
            if (isset($row[$keyField]) && (string)$row[$keyField] === (string)$keyValue) {
                $rows[$i] = array_merge($row, $newData);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            return self::writeTable($table, $rows);
        }
        return false;
    }

    /**
     * Delete record by key.
     */
    public static function delete(string $table, string $keyField, string $keyValue): bool {
        $rows = self::all($table);
        $initialCount = count($rows);

        $filtered = array_filter($rows, function($r) use ($keyField, $keyValue) {
            return !(isset($r[$keyField]) && (string)$r[$keyField] === (string)$keyValue);
        });

        if (count($filtered) !== $initialCount) {
            return self::writeTable($table, array_values($filtered));
        }
        return false;
    }

    /**
     * Stream CSV download directly to client for Excel.
     */
    public static function exportCsv(string $table): void {
        self::init();
        $filePath = self::getFilePath($table);

        if (!file_exists($filePath)) {
            if (isset(self::$schemas[$table])) {
                self::seedTable($table);
            } else {
                http_response_code(404);
                echo "Table not found";
                exit;
            }
        }

        $dist = self::getSetting('district', 'TandoAllahyar');
        $cleanDist = preg_replace('/[^a-zA-Z0-9]/', '', $dist);
        $filename = "{$cleanDist}_{$table}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }

    /**
     * Import user-uploaded CSV into table.
     */
    public static function importCsv(string $table, string $tmpFilePath, string $mode = 'replace'): array {
        if (!file_exists($tmpFilePath)) {
            return ['success' => false, 'message' => 'Uploaded file does not exist.'];
        }

        $fp = fopen($tmpFilePath, 'r');
        if (!$fp) {
            return ['success' => false, 'message' => 'Unable to read uploaded file.'];
        }

        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $headers = fgetcsv($fp);
        if (!$headers) {
            fclose($fp);
            return ['success' => false, 'message' => 'Empty or invalid CSV file.'];
        }

        $cleanHeaders = array_map(function($h) {
            return trim((string)$h);
        }, $headers);

        $importedRows = [];
        while (($data = fgetcsv($fp)) !== false) {
            if (count($data) === 1 && $data[0] === null) {
                continue;
            }
            $row = [];
            foreach ($cleanHeaders as $i => $col) {
                $row[$col] = $data[$i] ?? '';
            }
            $importedRows[] = $row;
        }
        fclose($fp);

        if (empty($importedRows)) {
            return ['success' => false, 'message' => 'File contains no data rows.'];
        }

        if ($mode === 'append') {
            $existing = self::all($table);
            $combined = array_merge($existing, $importedRows);
            self::writeTable($table, $combined);
            $count = count($importedRows);
            return ['success' => true, 'message' => "Successfully appended {$count} records to {$table}."];
        } else {
            self::writeTable($table, $importedRows, $cleanHeaders);
            $count = count($importedRows);
            return ['success' => true, 'message' => "Successfully replaced {$table} with {$count} records."];
        }
    }

    /**
     * Settings Helpers: Get all settings as key => value array
     */
    public static function getSettings(): array {
        $rows = self::all('settings');
        $map = [];
        foreach ($rows as $r) {
            if (!empty($r['key'])) {
                $map[$r['key']] = $r['value'] ?? '';
            }
        }
        return $map;
    }

    /**
     * Get single setting value by key.
     */
    public static function getSetting(string $key, $default = ''): string {
        $settings = self::getSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Update or insert a setting.
     */
    public static function updateSetting(string $key, string $value): bool {
        $rows = self::all('settings');
        $found = false;
        foreach ($rows as $i => $r) {
            if (isset($r['key']) && $r['key'] === $key) {
                $rows[$i]['value'] = $value;
                $rows[$i]['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        if (!$found) {
            $rows[] = [
                'key' => $key,
                'value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        return self::writeTable('settings', $rows, ['key', 'value', 'updated_at']);
    }

    /**
     * Update multiple settings in bulk.
     */
    public static function updateSettings(array $keyValues): bool {
        $rows = self::all('settings');
        $map = [];
        foreach ($rows as $r) {
            if (!empty($r['key'])) {
                $map[$r['key']] = $r;
            }
        }

        foreach ($keyValues as $k => $v) {
            $map[$k] = [
                'key' => $k,
                'value' => (string)$v,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        return self::writeTable('settings', array_values($map), ['key', 'value', 'updated_at']);
    }

    /**
     * Returns stats for all managed Excel tables.
     */
    public static function getTableStats(): array {
        self::init();
        $stats = [];

        foreach (array_keys(self::$schemas) as $table) {
            $filePath = self::getFilePath($table);
            $exists = file_exists($filePath);
            $rows = $exists ? self::all($table) : [];
            $size = $exists ? filesize($filePath) : 0;
            $mtime = $exists ? filemtime($filePath) : 0;

            $stats[$table] = [
                'table' => $table,
                'label' => ucwords(str_replace('_', ' ', $table)),
                'exists' => $exists,
                'rows' => count($rows),
                'size_kb' => round($size / 1024, 2),
                'updated_at' => $mtime ? date('Y-m-d H:i:s', $mtime) : 'Never',
                'file_path' => $filePath,
                'headers' => self::$schemas[$table]['headers'] ?? []
            ];
        }


        return $stats;
    }

    /**
     * Get list of talukas (returns array of taluka names or array of full records).
     */
    public static function getTalukas(bool $asNamesOnly = true, ?string $district = null): array {
        self::init();
        $rows = self::all('talukas');
        if ($district !== null && $district !== '') {
            $rows = array_filter($rows, fn($r) => strcasecmp($r['district'] ?? '', $district) === 0);
        }
        if ($asNamesOnly) {
            $names = [];
            foreach ($rows as $r) {
                $name = trim($r['taluka_name'] ?? '');
                if ($name !== '' && !in_array($name, $names)) {
                    $names[] = $name;
                }
            }
            return $names;
        }
        return array_values($rows);
    }

    /**
     * Add a new taluka to database.
     */
    public static function addTaluka(string $name, ?string $district = null): bool {
        self::init();
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $district = $district ?: (self::getSetting('district') ?: 'Tando Allahyar District');
        
        $rows = self::all('talukas');
        // Check duplicate
        foreach ($rows as $r) {
            if (strcasecmp(trim($r['taluka_name'] ?? ''), $name) === 0) {
                return false; // already exists
            }
        }
        
        $newId = 1;
        foreach ($rows as $r) {
            if (isset($r['id']) && is_numeric($r['id']) && (int)$r['id'] >= $newId) {
                $newId = (int)$r['id'] + 1;
            }
        }

        $rows[] = [
            'id' => (string)$newId,
            'taluka_name' => $name,
            'district' => $district,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return self::writeTable('talukas', $rows, ['id', 'taluka_name', 'district', 'created_at']);
    }

    /**
     * Delete a taluka by id or name.
     */
    public static function deleteTaluka(string $idOrName): bool {
        self::init();
        $idOrName = trim($idOrName);
        if ($idOrName === '') {
            return false;
        }

        $rows = self::all('talukas');
        $initialCount = count($rows);
        $filtered = [];

        foreach ($rows as $r) {
            if ($r['id'] === $idOrName || strcasecmp(trim($r['taluka_name'] ?? ''), $idOrName) === 0) {
                continue; // delete match
            }
            $filtered[] = $r;
        }

        if (count($filtered) === $initialCount) {
            return false;
        }

        return self::writeTable('talukas', $filtered, ['id', 'taluka_name', 'district', 'created_at']);
    }

    /**
     * Normalize CNIC by stripping non-numeric characters.
     */
    public static function normalizeCnic(string $cnic): string {
        return preg_replace('/[^0-9]/', '', $cnic);
    }

    /**
     * Format raw 13 digits CNIC into 00000-0000000-0 format.
     */
    public static function formatCnic(string $cnic): string {
        $clean = self::normalizeCnic($cnic);
        if (strlen($clean) === 13) {
            return substr($clean, 0, 5) . '-' . substr($clean, 5, 7) . '-' . substr($clean, 12, 1);
        }
        return $cnic;
    }

    /**
     * Find school by CNIC (supports formatted or unformatted CNIC).
     */
    public static function getSchoolByCnic(string $cnic): ?array {
        $cleanCnic = self::normalizeCnic($cnic);
        if (empty($cleanCnic)) {
            return null;
        }

        $schools = self::all('schools');
        foreach ($schools as $s) {
            $schoolCnic = self::normalizeCnic($s['cnic'] ?? '');
            if (!empty($schoolCnic) && $schoolCnic === $cleanCnic) {
                return $s;
            }
        }
        return null;
    }

    /**
     * Find school by SEMIS code.
     */
    public static function getSchoolBySemis(string $semis): ?array {
        $semis = trim($semis);
        if (empty($semis)) {
            return null;
        }
        return self::find('schools', 'semis_code', $semis);
    }

    /**
     * Sync Head Master user accounts from schools.csv to users.csv.
     * Ensures every school with a CNIC has a corresponding active user account.
     */
    public static function syncSchoolUsers(): void {
        $schools = self::all('schools');
        $users = self::all('users');
        $updated = false;

        $userMapBySemis = [];
        $userMapByCnic = [];
        $maxId = 0;

        foreach ($users as $idx => $u) {
            if (isset($u['id']) && is_numeric($u['id']) && (int)$u['id'] > $maxId) {
                $maxId = (int)$u['id'];
            }
            if (!empty($u['school_semis'])) {
                $userMapBySemis[$u['school_semis']] = $idx;
            }
            $cleanU = self::normalizeCnic($u['cnic'] ?? $u['username'] ?? '');
            if (!empty($cleanU)) {
                $userMapByCnic[$cleanU] = $idx;
            }
        }

        $defaultHash = password_hash('1122', PASSWORD_DEFAULT);

        foreach ($schools as $s) {
            $semis = trim($s['semis_code'] ?? '');
            $rawCnic = trim($s['cnic'] ?? '');
            $cleanCnic = self::normalizeCnic($rawCnic);
            $hmName = trim($s['head_master'] ?? 'Head Master');

            if (empty($cleanCnic) && empty($semis)) {
                continue;
            }

            $userFoundIdx = null;
            if (!empty($cleanCnic) && isset($userMapByCnic[$cleanCnic])) {
                $userFoundIdx = $userMapByCnic[$cleanCnic];
            } elseif (!empty($semis) && isset($userMapBySemis[$semis])) {
                $userFoundIdx = $userMapBySemis[$semis];
            }

            if ($userFoundIdx !== null) {
                // Ensure plain password and CNIC are preserved/populated
                $userRow = $users[$userFoundIdx];
                $modified = false;
                if (empty($userRow['password_plain'])) {
                    $users[$userFoundIdx]['password_plain'] = '1122';
                    $modified = true;
                }
                if (empty($userRow['cnic']) && !empty($rawCnic)) {
                    $users[$userFoundIdx]['cnic'] = $rawCnic;
                    $modified = true;
                }
                if (empty($userRow['school_semis']) && !empty($semis)) {
                    $users[$userFoundIdx]['school_semis'] = $semis;
                    $modified = true;
                }
                if ($modified) {
                    $updated = true;
                }
            } else {
                // Insert new Head Master user
                $maxId++;
                $newUsername = !empty($rawCnic) ? $rawCnic : $semis;
                $users[] = [
                    'id'             => (string)$maxId,
                    'username'       => $newUsername,
                    'password_hash'  => $defaultHash,
                    'password_plain' => '1122',
                    'full_name'      => $hmName,
                    'role'           => 'school',
                    'role_title'     => 'Head Master / Principal',
                    'district'       => $s['taluka'] ?? 'Tando Allahyar District',
                    'school_semis'   => $semis,
                    'cnic'           => $rawCnic,
                    'status'         => 'active',
                    'avatar'         => $s['logo'] ?? ''
                ];
                $updated = true;
            }
        }

        if ($updated) {
            self::writeTable('users', $users);
        }
    }

    /**
     * Get Head Master credentials (Username/CNIC, Plain Password, Hash, Name, SEMIS) for Admin display.
     */
    public static function getHeadMasterCredentials(string $semisOrCnic): array {
        self::syncSchoolUsers();
        $semisOrCnic = trim($semisOrCnic);
        $clean = self::normalizeCnic($semisOrCnic);

        $users = self::all('users');
        foreach ($users as $u) {
            if ($u['role'] === 'school') {
                $userCnicClean = self::normalizeCnic($u['cnic'] ?? $u['username'] ?? '');
                if ($u['school_semis'] === $semisOrCnic || (!empty($clean) && $userCnicClean === $clean)) {
                    return [
                        'user_id'        => $u['id'] ?? '',
                        'username'       => $u['username'] ?? $semisOrCnic,
                        'cnic'           => !empty($u['cnic']) ? $u['cnic'] : (!empty($clean) ? self::formatCnic($clean) : 'N/A'),
                        'full_name'      => $u['full_name'] ?? 'Head Master',
                        'school_semis'   => $u['school_semis'] ?? $semisOrCnic,
                        'password_plain' => !empty($u['password_plain']) ? $u['password_plain'] : '1122',
                        'status'         => $u['status'] ?? 'active'
                    ];
                }
            }
        }

        // Fallback default
        return [
            'user_id'        => '',
            'username'       => $semisOrCnic,
            'cnic'           => !empty($clean) ? self::formatCnic($clean) : $semisOrCnic,
            'full_name'      => 'Head Master',
            'school_semis'   => $semisOrCnic,
            'password_plain' => '1122',
            'status'         => 'active'
        ];
    }

    /**
     * Update Head Master password for school (updates both plain and bcrypt hash).
     */
    public static function updateHeadMasterPassword(string $semisOrCnic, string $newPassword): bool {
        self::syncSchoolUsers();
        $newPassword = trim($newPassword);
        if (empty($newPassword)) {
            return false;
        }

        $clean = self::normalizeCnic($semisOrCnic);
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $users = self::all('users');
        $updated = false;

        foreach ($users as $idx => $u) {
            if ($u['role'] === 'school') {
                $userCnicClean = self::normalizeCnic($u['cnic'] ?? $u['username'] ?? '');
                if ($u['school_semis'] === $semisOrCnic || (!empty($clean) && $userCnicClean === $clean)) {
                    $users[$idx]['password_plain'] = $newPassword;
                    $users[$idx]['password_hash'] = $hash;
                    $updated = true;
                    break;
                }
            }
        }

        if ($updated) {
            return self::writeTable('users', $users);
        }

        // If not found, create user
        $school = self::getSchoolBySemis($semisOrCnic) ?? self::getSchoolByCnic($semisOrCnic);
        $maxId = 0;
        foreach ($users as $u) {
            if (isset($u['id']) && is_numeric($u['id']) && (int)$u['id'] > $maxId) {
                $maxId = (int)$u['id'];
            }
        }

        $users[] = [
            'id'             => (string)($maxId + 1),
            'username'       => $school['cnic'] ?? $semisOrCnic,
            'password_hash'  => $hash,
            'password_plain' => $newPassword,
            'full_name'      => $school['head_master'] ?? 'Head Master',
            'role'           => 'school',
            'role_title'     => 'Head Master / Principal',
            'district'       => $school['taluka'] ?? 'Tando Allahyar District',
            'school_semis'   => $school['semis_code'] ?? $semisOrCnic,
            'cnic'           => $school['cnic'] ?? $semisOrCnic,
            'status'         => 'active',
            'avatar'         => $school['logo'] ?? ''
        ];

        return self::writeTable('users', $users);
    }

    /**
     * Get count of unread complaints or replies based on role.
     */
    public static function getUnreadComplaintsCount(string $role = 'admin', string $semisCode = ''): int {
        $complaints = self::all('complaints');
        $count = 0;
        foreach ($complaints as $c) {
            if ($role === 'admin') {
                if (($c['unread_admin'] ?? '0') === '1' || strtolower($c['status'] ?? '') === 'pending') {
                    $count++;
                }
            } elseif ($role === 'school') {
                if (!empty($semisCode) && ($c['semis_code'] ?? '') === $semisCode) {
                    if (($c['unread_school'] ?? '0') === '1') {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Generate unique tracking ticket number e.g. GRM-2026-0105
     */
    public static function generateTicketNo(): string {
        $complaints = self::all('complaints');
        $maxNum = 100;
        foreach ($complaints as $c) {
            if (preg_match('/GRM-\d{4}-(\d+)/', $c['ticket_no'] ?? '', $m)) {
                $n = (int)$m[1];
                if ($n > $maxNum) {
                    $maxNum = $n;
                }
            }
        }
        $next = $maxNum + 1;
        return 'GRM-' . date('Y') . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get all replies for a ticket sorted chronologically.
     */
    public static function getRepliesForTicket(string $ticketNo): array {
        $allReplies = self::all('complaint_replies');
        $replies = array_filter($allReplies, function($r) use ($ticketNo) {
            return ($r['ticket_no'] ?? '') === $ticketNo;
        });
        usort($replies, function($a, $b) {
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });
        return array_values($replies);
    }

    /**
     * Add a reply message to a complaint ticket and update ticket unread status.
     */
    public static function addReply(string $ticketNo, string $senderRole, string $senderName, string $message): bool {
        $ticket = self::find('complaints', 'ticket_no', $ticketNo);
        if (!$ticket) {
            return false;
        }

        $replies = self::all('complaint_replies');
        $maxId = 0;
        foreach ($replies as $r) {
            if (isset($r['id']) && is_numeric($r['id']) && (int)$r['id'] > $maxId) {
                $maxId = (int)$r['id'];
            }
        }

        $newReply = [
            'id'          => (string)($maxId + 1),
            'ticket_no'   => $ticketNo,
            'sender_role' => $senderRole,
            'sender_name' => $senderName,
            'message'     => $message,
            'created_at'  => date('Y-m-d H:i:s')
        ];
        $replies[] = $newReply;
        self::writeTable('complaint_replies', $replies);

        // Update parent complaint ticket metadata
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if ($senderRole === 'admin') {
            $updateData['unread_school'] = '1';
            $updateData['unread_admin'] = '0';
            // If status was pending, advance to Under Review / In Progress
            if (strtolower($ticket['status'] ?? '') === 'pending') {
                $updateData['status'] = 'Under Review';
            }
        } else {
            $updateData['unread_admin'] = '1';
            $updateData['unread_school'] = '0';
        }

        self::update('complaints', 'ticket_no', $ticketNo, $updateData);
        return true;
    }

    /**
     * Auto-flag a school's infrastructure risks based on its current profile/facilities data.
     *
     * Called after any facility/infrastructure save from admin or school portal.
     * Rules map specific facility values to standard SELD risk categories.
     * Existing unresolved risk records for the same school+category are NOT duplicated.
     * Only adds NEW risk entries for newly-detected issues.
     * Admin is expected to mark items 'Resolved' once fixed.
     *
     * @param string $semisCode   SEMIS code of the school to evaluate.
     * @return int Number of new risk records inserted.
     */
    public static function autoFlagSchoolRisks(string $semisCode): int {
        $school = self::find('schools', 'semis_code', $semisCode);
        if (!$school) return 0;

        $schoolName = $school['school_name'] ?? 'Unknown School';
        $taluka     = $school['taluka']      ?? '';

        // ── Risk Detection Rules ────────────────────────────────────────────────
        // Each entry: [risk_category, severity, details, trigger_fn]
        $rules = [
            [
                'category' => 'Dangerous Building Structure',
                'severity' => 'Critical',
                'details'  => 'Building structure reported as Dangerous or Unsafe by Head Master/Admin.',
                'trigger'  => fn() => in_array($school['building_structure'] ?? '', ['Dangerous / Unsafe', 'Condemned / Closed']),
            ],
            [
                'category' => 'Damaged Classrooms / Roof',
                'severity' => 'High',
                'details'  => 'Building structure reported as needing repair — classrooms or roof may be in disrepair.',
                'trigger'  => fn() => ($school['building_structure'] ?? '') === 'Needs Repair',
            ],
            [
                'category' => 'No Boundary Wall',
                'severity' => 'High',
                'details'  => 'School boundary wall is completely absent — students exposed to road/outside hazards.',
                'trigger'  => fn() => str_contains(strtolower($school['facility_boundary_wall'] ?? ''), 'unavailable') || str_contains(strtolower($school['facility_boundary_wall'] ?? ''), 'none'),
            ],
            [
                'category' => 'Damaged / Incomplete Wall',
                'severity' => 'Medium',
                'details'  => 'Boundary wall is damaged, partially built, or under construction.',
                'trigger'  => fn() => str_contains(strtolower($school['facility_boundary_wall'] ?? ''), 'partial') || str_contains(strtolower($school['facility_boundary_wall'] ?? ''), 'damaged') || str_contains(strtolower($school['facility_boundary_wall'] ?? ''), 'construction'),
            ],
            [
                'category' => 'No Drinking Water',
                'severity' => 'High',
                'details'  => 'No clean drinking water facility available at school.',
                'trigger'  => fn() => str_contains(strtolower($school['facility_water'] ?? ''), 'unavailable') || str_contains(strtolower($school['facility_water'] ?? ''), 'none'),
            ],
            [
                'category' => 'Non-functional Toilets',
                'severity' => 'High',
                'details'  => 'Toilets are absent or non-functional — affects student well-being and enrollment.',
                'trigger'  => fn() => str_contains(strtolower($school['facility_toilets'] ?? ''), 'unavailable') || str_contains(strtolower($school['facility_toilets'] ?? ''), 'none'),
            ],
            [
                'category' => 'No Electricity',
                'severity' => 'High',
                'details'  => 'No electricity supply of any kind (no grid, no solar).',
                'trigger'  => fn() => str_contains(strtolower($school['facility_electricity'] ?? ''), 'unavailable') || str_contains(strtolower($school['facility_electricity'] ?? ''), 'none'),
            ],
            [
                'category' => 'No Proper Drainage / Sewerage',
                'severity' => 'Medium',
                'details'  => 'School has broken or no drainage/sewerage system.',
                'trigger'  => fn() => str_contains(strtolower($school['drainage_sewerage'] ?? ''), 'broken') || str_contains(strtolower($school['drainage_sewerage'] ?? ''), 'none'),
            ],
            [
                'category' => 'Flood / Disaster Prone Area',
                'severity' => 'Critical',
                'details'  => 'School is located in a flood-prone or disaster-prone zone as reported by Head Master.',
                'trigger'  => fn() => strtolower(trim($school['flood_prone'] ?? '')) === 'yes',
            ],
            [
                'category' => 'No Furniture / Equipment',
                'severity' => 'Medium',
                'details'  => 'Acute shortage of desks, chairs, and teaching equipment reported.',
                'trigger'  => fn() => strtolower(trim($school['furniture_condition'] ?? '')) === 'none' || strtolower(trim($school['furniture_condition'] ?? '')) === 'none available',
            ],
        ];

        // ── Load existing unresolved risks for this school ─────────────────────
        $existingRisks = self::all('school_risks');
        $unresolvedCategories = [];
        foreach ($existingRisks as $r) {
            if (($r['semis_code'] ?? '') === $semisCode && strtolower($r['status'] ?? '') !== 'resolved') {
                $unresolvedCategories[] = $r['risk_category'] ?? '';
            }
        }

        // ── Insert new risk records for triggered rules not already tracked ─────
        $added = 0;
        foreach ($rules as $rule) {
            if (($rule['trigger'])() && !in_array($rule['category'], $unresolvedCategories)) {
                self::insert('school_risks', [
                    'semis_code'     => $semisCode,
                    'school_name'    => $schoolName,
                    'taluka'         => $taluka,
                    'risk_category'  => $rule['category'],
                    'severity'       => $rule['severity'],
                    'details'        => $rule['details'],
                    'reported_date'  => date('Y-m-d'),
                    'last_inspected' => date('Y-m-d'),
                    'status'         => 'Pending',
                    'notes'          => 'Auto-flagged from school infrastructure profile data.',
                ]);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Get all staff members registered for a specific school SEMIS code.
     */
    public static function getStaffBySemis(string $semisCode): array {
        self::init();
        $semisCode = trim($semisCode);
        if (empty($semisCode)) {
            return [];
        }
        $allStaff = self::all('school_staff');
        $schoolStaff = [];
        foreach ($allStaff as $s) {
            if (($s['semis_code'] ?? '') === $semisCode) {
                $schoolStaff[] = $s;
            }
        }
        return $schoolStaff;
    }

    /**
     * Recalculate teaching & non-teaching counts from school_staff.csv
     * and update schools.csv automatically.
     */
    public static function syncSchoolStaffCounts(string $semisCode): bool {
        self::init();
        $semisCode = trim($semisCode);
        if (empty($semisCode)) {
            return false;
        }

        $staff = self::getStaffBySemis($semisCode);
        $teachingCount = 0;
        $nonTeachingCount = 0;

        foreach ($staff as $m) {
            $type = strtolower(trim($m['staff_type'] ?? ''));
            if ($type === 'teaching' || str_contains($type, 'teach')) {
                $teachingCount++;
            } else {
                $nonTeachingCount++;
            }
        }

        return self::update('schools', 'semis_code', $semisCode, [
            'teachers'     => (string)$teachingCount,
            'non_teaching' => (string)$nonTeachingCount,
        ]);
    }

    /**
     * Calculates data completion percentage & missing fields for a school profile.
     * Evaluates identity, leadership, enrollment breakdown, facilities, infrastructure & staff registration.
     */
    public static function calculateSchoolProfileCompletion(string $semisCode): array {
        self::init();
        $school = self::find('schools', 'semis_code', $semisCode);
        if (!$school) {
            return [
                'percentage'       => 0,
                'total_fields'     => 0,
                'completed_fields' => 0,
                'missing_fields'   => [],
                'status_label'     => 'Unknown School',
            ];
        }

        $staff = self::getStaffBySemis($semisCode);
        $staffCount = count($staff);
        $declaredTeachers = (int)($school['teachers'] ?? 0);

        $checklist = [
            // Basic Identity & Location
            [
                'key'         => 'school_name',
                'label'       => 'Official School Name',
                'category'    => 'Institutional Identity',
                'importance'  => 'Critical',
                'description' => 'Official full name as registered in SELD directory',
                'valid'       => !empty(trim($school['school_name'] ?? '')),
            ],
            [
                'key'         => 'taluka',
                'label'       => 'Taluka Sub-division',
                'category'    => 'Institutional Identity',
                'importance'  => 'Critical',
                'description' => 'Taluka jurisdiction assignment',
                'valid'       => !empty(trim($school['taluka'] ?? '')),
            ],
            [
                'key'         => 'address',
                'label'       => 'Physical Location / Address',
                'category'    => 'Institutional Identity',
                'importance'  => 'High',
                'description' => 'Complete street/village postal location',
                'valid'       => !empty(trim($school['address'] ?? '')),
            ],
            [
                'key'         => 'phone',
                'label'       => 'Official Contact Phone',
                'category'    => 'Institutional Identity',
                'importance'  => 'High',
                'description' => 'Working mobile/landline number for school communication',
                'valid'       => !empty(trim($school['phone'] ?? '')),
            ],

            // Leadership
            [
                'key'         => 'head_master',
                'label'       => 'Head Master / Mistress Name',
                'category'    => 'Leadership',
                'importance'  => 'Critical',
                'description' => 'Full name of appointed institutional head',
                'valid'       => !empty(trim($school['head_master'] ?? '')),
            ],
            [
                'key'         => 'cnic',
                'label'       => 'Head Master CNIC Number',
                'category'    => 'Leadership',
                'importance'  => 'Critical',
                'description' => '13-digit valid CNIC (tied to portal login ID)',
                'valid'       => strlen(self::normalizeCnic($school['cnic'] ?? '')) === 13,
            ],

            // Enrollment Breakdown
            [
                'key'         => 'enrollment_breakdown',
                'label'       => 'Gender-wise Enrollment Breakdown',
                'category'    => 'Student Demographics',
                'importance'  => 'High',
                'description' => 'Distinct Boys & Girls student counts submitted',
                'valid'       => (isset($school['enrollment_boys']) && isset($school['enrollment_girls']) && ((int)$school['enrollment_boys'] > 0 || (int)$school['enrollment_girls'] > 0 || (int)($school['enrollment'] ?? 0) > 0)),
            ],

            // Core Utilities & Physical Assets
            [
                'key'         => 'facility_boundary_wall',
                'label'       => 'Boundary Wall Condition',
                'category'    => 'Core Facilities',
                'importance'  => 'High',
                'description' => 'Perimeter wall status verified',
                'valid'       => !empty(trim($school['facility_boundary_wall'] ?? '')) && !in_array(strtolower(trim($school['facility_boundary_wall'])), ['none', 'unavailable']),
            ],
            [
                'key'         => 'facility_electricity',
                'label'       => 'Electricity / Power Provision',
                'category'    => 'Core Facilities',
                'importance'  => 'High',
                'description' => 'Grid or Solar power availability recorded',
                'valid'       => !empty(trim($school['facility_electricity'] ?? '')) && !in_array(strtolower(trim($school['facility_electricity'])), ['none', 'unavailable']),
            ],
            [
                'key'         => 'facility_water',
                'label'       => 'Clean Drinking Water Source',
                'category'    => 'Core Facilities',
                'importance'  => 'High',
                'description' => 'Functional drinking water source recorded',
                'valid'       => !empty(trim($school['facility_water'] ?? '')) && !in_array(strtolower(trim($school['facility_water'])), ['none', 'unavailable']),
            ],
            [
                'key'         => 'facility_toilets',
                'label'       => 'Sanitation / Toilet Blocks',
                'category'    => 'Core Facilities',
                'importance'  => 'High',
                'description' => 'Functional student sanitation blocks',
                'valid'       => !empty(trim($school['facility_toilets'] ?? '')) && !in_array(strtolower(trim($school['facility_toilets'])), ['none', 'unavailable']),
            ],
            [
                'key'         => 'facility_internet',
                'label'       => 'Digital Connectivity / Internet',
                'category'    => 'Core Facilities',
                'importance'  => 'Medium',
                'description' => 'Broadband/4G/Mobile network status',
                'valid'       => !empty(trim($school['facility_internet'] ?? '')) && !in_array(strtolower(trim($school['facility_internet'])), ['none', 'unavailable']),
            ],

            // Extended Infrastructure Assessment
            [
                'key'         => 'building_structure',
                'label'       => 'Building Structure Safety Assessment',
                'category'    => 'Safety & Risk Assessment',
                'importance'  => 'Critical',
                'description' => 'Classroom structural integrity recorded',
                'valid'       => !empty(trim($school['building_structure'] ?? '')) && strtolower(trim($school['building_structure'])) !== 'unknown',
            ],
            [
                'key'         => 'drainage_sewerage',
                'label'       => 'Drainage & Sewerage System',
                'category'    => 'Safety & Risk Assessment',
                'importance'  => 'Medium',
                'description' => 'Rainwater & wastewater disposal status',
                'valid'       => !empty(trim($school['drainage_sewerage'] ?? '')),
            ],
            [
                'key'         => 'flood_prone',
                'label'       => 'Flood / Disaster Vulnerability Status',
                'category'    => 'Safety & Risk Assessment',
                'importance'  => 'High',
                'description' => 'Low-lying flood vulnerability declared',
                'valid'       => !empty(trim($school['flood_prone'] ?? '')),
            ],
            [
                'key'         => 'furniture_condition',
                'label'       => 'Student Furniture & Desks Adequacy',
                'category'    => 'Safety & Risk Assessment',
                'importance'  => 'Medium',
                'description' => 'Desks & benches sufficiency status',
                'valid'       => !empty(trim($school['furniture_condition'] ?? '')),
            ],

            // Staff Registration
            [
                'key'         => 'staff_roster',
                'label'       => 'Individual Staff Members Registered',
                'category'    => 'Staff HR Data',
                'importance'  => 'Critical',
                'description' => 'Teaching & non-teaching staff entries with CNIC and Personal No in Staff Directory',
                'valid'       => $staffCount > 0,
            ],
        ];

        $total = count($checklist);
        $completed = 0;
        $missing = [];

        foreach ($checklist as $item) {
            if ($item['valid']) {
                $completed++;
            } else {
                $missing[] = $item;
            }
        }

        $percentage = round(($completed / $total) * 100);

        $statusLabel = 'Complete';
        $statusColor = 'success';
        if ($percentage < 60) {
            $statusLabel = 'Critical Deficit';
            $statusColor = 'danger';
        } elseif ($percentage < 85) {
            $statusLabel = 'Incomplete';
            $statusColor = 'warning';
        } elseif ($percentage < 100) {
            $statusLabel = 'Near Complete';
            $statusColor = 'primary';
        }

        return [
            'percentage'       => $percentage,
            'total_fields'     => $total,
            'completed_fields' => $completed,
            'missing_count'    => count($missing),
            'missing_fields'   => $missing,
            'status_label'     => $statusLabel,
            'status_color'     => $statusColor,
            'staff_count'      => $staffCount,
            'declared_teachers'=> $declaredTeachers,
        ];
    }
}


