<?php
/**
 * ============================================================
 *  Eduroad - Academic Hierarchy (Colleges, Departments, Specializations)
 *  File: config/academic_hierarchy.php
 * ============================================================
 */

require_once __DIR__ . '/db.php';

/**
 * Ensures all hierarchy tables, columns, and initial seed data exist in the database.
 * Executes automatically when any hierarchy page or register page is loaded.
 */
function ensureHierarchyTables(?PDO $db = null): void
{
    // تم نقل جميع أوامر إنشاء الجداول والأعمدة إلى ملفات الترحيل الثابتة (migrations/)
    // لمنع تنفيذ أوامر DDL أثناء طلبات التصفح ولتحسين الأداء والأمان.
    return;
}

/**
 * Seeds initial rich hierarchy of colleges, departments, and specializations
 */
function seedHierarchyInitialData(PDO $db): void
{
    $data = [
        [
            'name' => 'كلية علوم الحاسب والمعلومات',
            'code' => 'CSIS',
            'icon' => '💻',
            'departments' => [
                [
                    'name' => 'علوم الحاسب',
                    'code' => 'CS',
                    'specializations' => [
                        'هندسة البرمجيات',
                        'الذكاء الاصطناعي وتعلم الآلة',
                        'علم البيانات والتحليلات المتقدمة',
                        'علوم الحاسب العامة',
                        'الأنظمة الموزعة والمدمجة'
                    ]
                ],
                [
                    'name' => 'نظم المعلومات',
                    'code' => 'IS',
                    'specializations' => [
                        'نظم المعلومات الإدارية (MIS)',
                        'تحليل وتصميم النظم والبيانات',
                        'حوكمة وإدارة تقنية المعلومات',
                        'تطوير الحلول الرقمية للأعمال'
                    ]
                ],
                [
                    'name' => 'تقنية المعلومات',
                    'code' => 'IT',
                    'specializations' => [
                        'الأمن السيبراني والتحري الرقمي',
                        'شبكات الحاسب وإدارة البنية التحتية',
                        'الحوسبة السحابية وهندسة الويب',
                        'إنترنت الأشياء والأنظمة الذكية'
                    ]
                ]
            ]
        ],
        [
            'name' => 'كلية الهندسة',
            'code' => 'ENG',
            'icon' => '⚙️',
            'departments' => [
                [
                    'name' => 'الهندسة الكهربائية',
                    'code' => 'EE',
                    'specializations' => [
                        'هندسة الاتصالات والإلكترونيات',
                        'هندسة القوى والطاقة المتجددة',
                        'هندسة التحكم والأنظمة الذكية'
                    ]
                ],
                [
                    'name' => 'الهندسة المدنية',
                    'code' => 'CE',
                    'specializations' => [
                        'الهندسة الإنشائية',
                        'هندسة الطرق والمرور والنقل',
                        'الهندسة البيئية والموارد المائية',
                        'إدارة مشاريع التشييد والإنشاءات'
                    ]
                ],
                [
                    'name' => 'الهندسة الميكانيكية',
                    'code' => 'ME',
                    'specializations' => [
                        'هندسة القوى والحراريات',
                        'التصميم والتصنيع الميكانيكي',
                        'الميكاترونكس وهندسة الروبوتات'
                    ]
                ],
                [
                    'name' => 'الهندسة المعمارية',
                    'code' => 'ARCH',
                    'specializations' => [
                        'التصميم المعماري الحديث',
                        'التخطيط العمراني والمدن الذكية',
                        'العمارة البيئية والمستدامة'
                    ]
                ]
            ]
        ],
        [
            'name' => 'كلية إدارة الأعمال',
            'code' => 'CBA',
            'icon' => '📈',
            'departments' => [
                [
                    'name' => 'إدارة الأعمال',
                    'code' => 'BUS',
                    'specializations' => [
                        'الإدارة العامة وإدارة العمليات',
                        'إدارة الموارد البشرية',
                        'ريادة الأعمال وإدارة المشاريع الصغيرة',
                        'إدارة سلاسل الإمداد واللوجستيات'
                    ]
                ],
                [
                    'name' => 'المحاسبة',
                    'code' => 'ACCT',
                    'specializations' => [
                        'المحاسبة المالية والتقارير الدولية',
                        'المراجعة والتدقيق المالي',
                        'المحاسبة الإدارية والتكاليف',
                        'المحاسبة الضريبية والزكاة'
                    ]
                ],
                [
                    'name' => 'العلوم المالية والمصرفية',
                    'code' => 'FIN',
                    'specializations' => [
                        'الإدارة المالية والاستثمار',
                        'الخدمات المصرفية والمالية الإسلامية',
                        'التأمين وإدارة المخاطر المالية'
                    ]
                ],
                [
                    'name' => 'التسويق',
                    'code' => 'MKT',
                    'specializations' => [
                        'التسويق الرقمي والتجارة الإلكترونية',
                        'إدارة العلامات التجارية والتواصل التسويقي',
                        'سلوك المستهلك وبحوث السوق'
                    ]
                ]
            ]
        ],
        [
            'name' => 'كلية العلوم الطبية والصحية',
            'code' => 'MED',
            'icon' => '🩺',
            'departments' => [
                [
                    'name' => 'الطب والجراحة',
                    'code' => 'MED_SURG',
                    'specializations' => [
                        'الطب الباطني العام',
                        'الجراحة العامة وجراحة اليوم الواحد',
                        'طب الأطفال وحديثي الولادة',
                        'طب الأسرة والمجتمع'
                    ]
                ],
                [
                    'name' => 'الصيدلة',
                    'code' => 'PHARM',
                    'specializations' => [
                        'الصيدلة الإكلينيكية والسريرية',
                        'علم الأدوية والسموم',
                        'الصيدلانيات وتصميم المركبات'
                    ]
                ],
                [
                    'name' => 'علوم التمريض',
                    'code' => 'NURS',
                    'specializations' => [
                        'تمريض العناية المركزة والطوارئ',
                        'تمريض الباطني والجراحي',
                        'تمريض الصحة النفسية'
                    ]
                ],
                [
                    'name' => 'المختبرات والتحاليل الطبية',
                    'code' => 'LAB',
                    'specializations' => [
                        'الكيمياء الحيوية السريرية',
                        'علم الأحياء الدقيقة الطبية',
                        'علم أمراض الدم ونقل الدم'
                    ]
                ]
            ]
        ],
        [
            'name' => 'كلية العلوم',
            'code' => 'SCI',
            'icon' => '🔬',
            'departments' => [
                [
                    'name' => 'الرياضيات والإحصاء',
                    'code' => 'MATH',
                    'specializations' => [
                        'الرياضيات البحتة',
                        'الرياضيات التطبيقية والنمذجة',
                        'الإحصاء وبحوث العمليات'
                    ]
                ],
                [
                    'name' => 'الفيزياء',
                    'code' => 'PHYS',
                    'specializations' => [
                        'الفيزياء النظرية والذرية',
                        'الفيزياء الطبية الحيوية',
                        'فيزياء المواد والنانوتكنولوجي'
                    ]
                ],
                [
                    'name' => 'الكيمياء',
                    'code' => 'CHEM',
                    'specializations' => [
                        'الكيمياء العضوية',
                        'الكيمياء التحليلية والأجهزة',
                        'الكيمياء الحيوية والصناعية'
                    ]
                ]
            ]
        ],
        [
            'name' => 'كلية الآداب والعلوم الإنسانية',
            'code' => 'ARTS',
            'icon' => '📚',
            'departments' => [
                [
                    'name' => 'اللغة العربية وآدابها',
                    'code' => 'ARAB',
                    'specializations' => [
                        'اللغويات والنحو والصرف',
                        'الأدب والنقد الأدبي الحديث',
                        'البلاغة والأسلوبية'
                    ]
                ],
                [
                    'name' => 'اللغات والترجمة',
                    'code' => 'TRANS',
                    'specializations' => [
                        'الترجمة الفورية والتحريرية (إنجليزي - عربي)',
                        'اللغويات التطبيقية وتعليم اللغات',
                        'الأدب المقارن والدراسات الثقافية'
                    ]
                ],
                [
                    'name' => 'الإعلام والاتصال',
                    'code' => 'COMM',
                    'specializations' => [
                        'الصحافة والإعلام الرقمي الجديد',
                        'العلاقات العامة والاتصال المؤسسي',
                        'الإذاعة والتلفزيون والإنتاج المرئي'
                    ]
                ]
            ]
        ]
    ];

    $db->beginTransaction();
    try {
        $cOrder = 1;
        $stmtCollege = $db->prepare("INSERT INTO `colleges` (`name`, `code`, `icon`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, 1)");
        $stmtDept = $db->prepare("INSERT INTO `departments` (`college_id`, `name`, `code`, `sort_order`, `is_active`) VALUES (?, ?, ?, ?, 1)");
        $stmtSpec = $db->prepare("INSERT INTO `specializations` (`department_id`, `name`, `sort_order`, `is_active`) VALUES (?, ?, ?, 1)");

        foreach ($data as $c) {
            $stmtCollege->execute([$c['name'], $c['code'], $c['icon'], $cOrder++]);
            $collegeId = (int) $db->lastInsertId();

            $dOrder = 1;
            foreach ($c['departments'] as $d) {
                $stmtDept->execute([$collegeId, $d['name'], $d['code'], $dOrder++]);
                $deptId = (int) $db->lastInsertId();

                $sOrder = 1;
                foreach ($d['specializations'] as $specName) {
                    $stmtSpec->execute([$deptId, $specName, $sOrder++]);
                }
            }
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error_log("seedHierarchyInitialData error: " . $e->getMessage());
    }
}

/**
 * Get all colleges
 */
function getAllColleges(bool $onlyActive = true): array
{
    ensureHierarchyTables();
    $sql = "SELECT c.*,
            (SELECT COUNT(*) FROM departments d WHERE d.college_id = c.id " . ($onlyActive ? "AND d.is_active = 1" : "") . ") AS departments_count,
            (SELECT COUNT(*) FROM specializations s JOIN departments d ON s.department_id = d.id WHERE d.college_id = c.id " . ($onlyActive ? "AND s.is_active = 1 AND d.is_active = 1" : "") . ") AS specs_count
            FROM colleges c " . ($onlyActive ? "WHERE c.is_active = 1 " : "") . "ORDER BY c.sort_order ASC, c.name ASC";
    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get departments by college
 */
function getDepartmentsByCollege(int $collegeId, bool $onlyActive = true): array
{
    ensureHierarchyTables();
    $sql = "SELECT d.*,
            (SELECT COUNT(*) FROM specializations s WHERE s.department_id = d.id " . ($onlyActive ? "AND s.is_active = 1" : "") . ") AS specs_count
            FROM departments d
            WHERE d.college_id = ? " . ($onlyActive ? "AND d.is_active = 1 " : "") . "ORDER BY d.sort_order ASC, d.name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$collegeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get specializations by department    
 */
function getSpecializationsByDepartment(int $departmentId, bool $onlyActive = true): array
{
    ensureHierarchyTables();
    $sql = "SELECT s.* FROM specializations s
            WHERE s.department_id = ? " . ($onlyActive ? "AND s.is_active = 1 " : "") . "ORDER BY s.sort_order ASC, s.name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$departmentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get complete hierarchy tree
 */
function getHierarchyFullTree(bool $onlyActive = true): array
{
    ensureHierarchyTables();
    $colleges = getAllColleges($onlyActive);
    foreach ($colleges as &$c) {
        $c['departments'] = getDepartmentsByCollege((int)$c['id'], $onlyActive);
        foreach ($c['departments'] as &$d) {
            $d['specializations'] = getSpecializationsByDepartment((int)$d['id'], $onlyActive);
        }
        unset($d);
    }
    unset($c);
    return $colleges;
}
