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
            'headers' => ['id', 'semis_code', 'school_name', 'head_master', 'cnic', 'phone', 'address', 'level', 'gender', 'taluka', 'enrollment', 'attendance_pct', 'status', 'status_badge', 'classrooms', 'teachers', 'non_teaching', 'facility_electricity', 'facility_water', 'facility_toilets', 'facility_boundary_wall', 'facility_internet'],
            'seeds' => [
                ['1', '403010001', 'Government Primary School Model City, Tando Allahyar', 'Muhammad Ishaq Memon', '41302-1849201-3', '+92 300 1234567', 'Station Road, City Area, Tando Allahyar', 'Primary', 'Co-education', 'Tando Allahyar', '342', '93%', 'Good', 'badge-good', '6', '8', '2', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G'],
                ['2', '403010002', 'Government Girls Elementary School B, Jhando Mari', 'Nusrat Parveen Laghari', '41302-7654321-2', '+92 301 2345678', 'Main Bazar, Jhando Mari', 'Middle', 'Girls', 'Jhando Mari', '218', '84%', 'Needs Attention', 'badge-attention', '5', '6', '2', 'Grid Only', 'Handpump / Tap', 'Needs Repair', 'Partial / Damaged', 'Unavailable / None'],
                ['3', '403010003', 'Government High School Main, Chambar', 'Ghulam Sarwar Jarwar', '41302-9876543-1', '+92 302 3456789', 'Station Road, Chambar', 'Secondary', 'Boys', 'Chambar', '567', '96%', 'Active', 'badge-active', '12', '18', '4', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G'],
                ['4', '403010004', 'Government Boys Primary School D, Nasarpur', 'Abdul Hameed Soomro', '41302-3456789-5', '+92 303 4567890', 'Mohallah Qazi, Nasarpur', 'Primary', 'Boys', 'Nasarpur', '189', '72%', 'Not Reporting', 'badge-not-rep', '4', '4', '1', 'Unavailable / None', 'Handpump / Tap', 'Needs Repair', 'Partial / Damaged', 'Unavailable / None'],
                ['5', '403010005', 'Government Girls Secondary School E, Tando Allahyar', 'Farzana Kousar', '41302-5432167-4', '+92 304 5678901', 'Mir Colony, Tando Allahyar', 'Secondary', 'Girls', 'Tando Allahyar', '412', '91%', 'Good', 'badge-good', '10', '14', '3', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G'],
                ['6', '403010006', 'Government Comprehensive Model School F, Tando Allahyar', 'Dr. Rasheed Ahmed Jamali', '41302-6543218-7', '+92 305 6789012', 'Hyderabad Road, Tando Allahyar', 'Higher Secondary', 'Co-education', 'Tando Allahyar', '628', '94%', 'Active', 'badge-active', '16', '24', '5', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G'],
                ['7', '403010007', 'Government Primary School G, Pyaro Lund', 'Khadim Hussain Lund', '41302-2345678-9', '+92 306 7890123', 'Village Pyaro Lund, Jhando Mari', 'Primary', 'Co-education', 'Jhando Mari', '155', '88%', 'Good', 'badge-good', '3', '4', '1', 'Solar Only', 'Handpump / Tap', 'Functional Blocks', 'Secured & Complete', 'Partial / Mobile Data'],
                ['8', '403010008', 'Government Boys High School Missan, Chambar', 'Ali Nawaz Rind', '41302-8765432-1', '+92 307 8901234', 'Missan Stop, Chambar', 'Secondary', 'Boys', 'Chambar', '480', '92%', 'Active', 'badge-active', '9', '12', '3', 'Grid Only', 'Water Supply Line', 'Functional Blocks', 'Secured & Complete', 'Partial / Mobile Data'],
                ['9', '403010009', 'Government Girls High School Ceramic Town, Nasarpur', 'Shazia Bano Nizamani', '41302-4567890-8', '+92 308 9012345', 'Ceramic Center Road, Nasarpur', 'Secondary', 'Girls', 'Nasarpur', '310', '89%', 'Good', 'badge-good', '8', '11', '2', 'Solar + Grid', 'Filtered Plant', 'Functional Blocks', 'Secured & Complete', 'Broadband / 4G'],
                ['10', '403010010', 'Government Primary School Station Road, Tando Allahyar', 'Mirza Bashir Baig', '41302-1357924-3', '+92 309 0123456', 'Railway Colony, Tando Allahyar', 'Primary', 'Co-education', 'Tando Allahyar', '275', '86%', 'Needs Attention', 'badge-attention', '5', '7', '2', 'Grid Only', 'Handpump / Tap', 'Needs Repair', 'Under Construction', 'Unavailable / None'],
            ]
        ],
        'students' => [
            'headers' => ['id', 'student_code', 'full_name', 'gender', 'school_name', 'grade', 'attendance_pct', 'risk_status', 'status_badge', 'text_class'],
            'seeds' => [
                ['1', 'STU-1001', 'Ali Hassan', 'Male', 'Government Primary School Model City, Tando Allahyar', 'Grade 4', '94%', 'Normal', 'badge-normal', 'text-success'],
                ['2', 'STU-1002', 'Fatima Noor', 'Female', 'Government Girls Elementary School B, Jhando Mari', 'Grade 6', '76%', 'Monitor', 'badge-monitor', 'text-warning'],
                ['3', 'STU-1003', 'Muhammad Zain', 'Male', 'Government High School Main, Chambar', 'Grade 9', '88%', 'Normal', 'badge-normal', 'text-success'],
                ['4', 'STU-1004', 'Sana Bibi', 'Female', 'Government Boys Primary School D, Nasarpur', 'Grade 3', '54%', 'At Risk', 'badge-risk', 'text-danger'],
                ['5', 'STU-1005', 'Imran Khan', 'Male', 'Government Girls Secondary School E, Tando Allahyar', 'Grade 8', '91%', 'Normal', 'badge-normal', 'text-success'],
                ['6', 'STU-1006', 'Hina Rehman', 'Female', 'Government Primary School G, Pyaro Lund', 'Grade 5', '69%', 'Monitor', 'badge-monitor', 'text-warning'],
                ['7', 'STU-1007', 'Asif Memon', 'Male', 'Government Primary School Model City, Tando Allahyar', 'Grade 2', '48%', 'At Risk', 'badge-risk', 'text-danger'],
                ['8', 'STU-1008', 'Bilal Ahmed', 'Male', 'Government High School Main, Chambar', 'Grade 10', '95%', 'Normal', 'badge-normal', 'text-success'],
                ['9', 'STU-1009', 'Zainab Solangi', 'Female', 'Government Girls Secondary School E, Tando Allahyar', 'Grade 7', '58%', 'At Risk', 'badge-risk', 'text-danger'],
                ['10', 'STU-1010', 'Kamran Abbasi', 'Male', 'Government Boys High School Missan, Chambar', 'Grade 8', '74%', 'Monitor', 'badge-monitor', 'text-warning'],
                ['11', 'STU-1011', 'Ayesha Chandio', 'Female', 'Government Girls High School Ceramic Town, Nasarpur', 'Grade 6', '92%', 'Normal', 'badge-normal', 'text-success'],
                ['12', 'STU-1012', 'Tariq Baloch', 'Male', 'Government Primary School Station Road, Tando Allahyar', 'Grade 5', '51%', 'At Risk', 'badge-risk', 'text-danger']
            ]
        ],
        'attendance' => [
            'headers' => ['id', 'student_code', 'full_name', 'grade', 'gender', 'status', 'status_badge', 'monthly_pct', 'date', 'school_name'],
            'seeds' => [
                ['1', 'STU-1001', 'Ali Hassan', 'Grade 4', 'Male', 'Present', 'badge-present', '94%', '2026-09-19', 'Government Primary School Model City, Tando Allahyar'],
                ['2', 'STU-1002', 'Fatima Noor', 'Grade 6', 'Female', 'Absent', 'badge-absent', '76%', '2026-09-19', 'Government Girls Elementary School B, Jhando Mari'],
                ['3', 'STU-1003', 'Muhammad Zain', 'Grade 9', 'Male', 'Present', 'badge-present', '88%', '2026-09-19', 'Government High School Main, Chambar'],
                ['4', 'STU-1004', 'Sana Bibi', 'Grade 3', 'Female', 'Absent', 'badge-absent', '54%', '2026-09-19', 'Government Boys Primary School D, Nasarpur'],
                ['5', 'STU-1005', 'Imran Khan', 'Grade 8', 'Male', 'Present', 'badge-present', '91%', '2026-09-19', 'Government Girls Secondary School E, Tando Allahyar'],
                ['6', 'STU-1006', 'Hina Rehman', 'Grade 5', 'Female', 'Leave', 'badge-leave', '69%', '2026-09-19', 'Government Primary School G, Pyaro Lund'],
                ['7', 'STU-1007', 'Asif Memon', 'Grade 2', 'Male', 'Absent', 'badge-absent', '48%', '2026-09-19', 'Government Primary School Model City, Tando Allahyar'],
                ['8', 'STU-1008', 'Bilal Ahmed', 'Grade 10', 'Male', 'Present', 'badge-present', '95%', '2026-09-19', 'Government High School Main, Chambar'],
                ['9', 'STU-1009', 'Zainab Solangi', 'Grade 7', 'Female', 'Absent', 'badge-absent', '58%', '2026-09-19', 'Government Girls Secondary School E, Tando Allahyar'],
                ['10', 'STU-1010', 'Kamran Abbasi', 'Grade 8', 'Male', 'Present', 'badge-present', '74%', '2026-09-19', 'Government Boys High School Missan, Chambar'],
                ['11', 'STU-1011', 'Ayesha Chandio', 'Grade 6', 'Female', 'Present', 'badge-present', '92%', '2026-09-19', 'Government Girls High School Ceramic Town, Nasarpur'],
                ['12', 'STU-1012', 'Tariq Baloch', 'Grade 5', 'Male', 'Absent', 'badge-absent', '51%', '2026-09-19', 'Government Primary School Station Road, Tando Allahyar']
            ]
        ],
        'at_risk' => [
            'headers' => ['id', 'student_code', 'full_name', 'school_name', 'grade', 'taluka', 'attendance_pct', 'risk_level', 'last_followup', 'status', 'action_needed'],
            'seeds' => [
                ['1', 'STU-1004', 'Sana Bibi', 'GBPS D, Nasarpur', 'Grade 3', 'Nasarpur', '54%', 'High', 'Never', 'Unresolved', 'Parental Contact Required'],
                ['2', 'STU-1007', 'Asif Memon', 'GPS Model City, Tando Allahyar', 'Grade 2', 'Tando Allahyar', '48%', 'High', '2026-09-10', 'In Progress', 'Home Visit Scheduled'],
                ['3', 'STU-1009', 'Zainab Solangi', 'GGSS E, Tando Allahyar', 'Grade 7', 'Tando Allahyar', '58%', 'High', '2026-09-12', 'Follow-up Due', 'Financial/Stipend Assessment'],
                ['4', 'STU-1012', 'Tariq Baloch', 'GPS Station Road, Tando Allahyar', 'Grade 5', 'Tando Allahyar', '51%', 'High', 'Never', 'Unresolved', 'Health / Sickness Check'],
                ['5', 'STU-1002', 'Fatima Noor', 'GGES B, Jhando Mari', 'Grade 6', 'Jhando Mari', '76%', 'Low', '2026-09-15', 'Monitored', 'Class Teacher Feedback'],
                ['6', 'STU-1006', 'Hina Rehman', 'GPS G, Pyaro Lund', 'Grade 5', 'Jhando Mari', '69%', 'Medium', '2026-09-14', 'In Progress', 'Attendance Warning Issued'],
                ['7', 'STU-1010', 'Kamran Abbasi', 'GBHS Missan, Chambar', 'Grade 8', 'Chambar', '74%', 'Medium', 'Never', 'Pending', 'Teacher Guidance Session']
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

        flock($fp, LOCK_SH);

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

        flock($fp, LOCK_UN);
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

        flock($fp, LOCK_EX);

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
        flock($fp, LOCK_UN);
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
}

