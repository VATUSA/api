<?php
/**
 * Interact with Moodle
 * @author Blake Nahin <b.nahin@vatusa.net>
 */

namespace App\Classes;

use App\User;
use Exception;
use Illuminate\Support\Facades\DB;
use MoodleRest;
use ReflectionClass;

class VATUSAMoodle extends MoodleRest
{
    /** @var int[] Role Mappings */
    protected $roleIds = [
        'TA'     => 1,
        'INS'    => 4,
        'STU'    => 5,
        'MTR'    => 9,
        'CBT'    => 10,
        'FACCBT' => 11
    ];

    /** @var int VATUSA Category Context */
    public const CATEGORY_CONTEXT_VATUSA = 43;
    public const CATEGORY_CONTEXT_VATUSA_EXAMS = 3020;

    /** @var int Exam Course Context */
    public const EXAM_CONTEXT_OBS = -1;
    public const EXAM_CONTEXT_S1 = 3024;
    public const EXAM_CONTEXT_S2 = 3044;
    public const EXAM_CONTEXT_S3 = 3046;
    public const EXAM_CONTEXT_C1 = 3048;

    /** @var int Category IDs */
    public const CATEGORY_ID_VATUSA = 2;
    public const CATEGORY_ID_OBS = 3;
    public const CATEGORY_ID_S1 = 4;
    public const CATEGORY_ID_S2 = 72;
    public const CATEGORY_ID_S3 = 6;
    public const CATEGORY_ID_C1 = 7;

    /** @var int Context Levels */
    public const CONTEXT_SYSTEM = 10;
    public const CONTEXT_USER = 30;
    public const CONTEXT_COURSECAT = 40;
    public const CONTEXT_COURSE = 50;
    public const CONTEXT_MODULE = 70;
    public const CONTEXT_BLOCK = 80;

    private $isTest;

    /** @var array|null Memoized result of getCategories() for the lifetime of this instance */
    private $categoriesCache = null;

    /** @var array Memoized results of getCoursesInCategory(), keyed by category id */
    private $coursesInCategoryCache = [];

    /** @var array|null Memoized cohort idnumber => id map for the lifetime of this instance */
    private $cohortIdMapCache = null;

    /** @var int Seconds to bound each Moodle HTTP request to */
    private const HTTP_TIMEOUT_SECONDS = 30;

    /**
     * VATUSAMoodle constructor.
     *
     * @param bool $isSSO
     *
     * @throws \Exception
     */
    public function __construct(bool $isSSO = false, bool $isTest = false)
    {
        $this->isTest = $isTest;
        if (!in_array(app()->environment(), ["livedev", "staging", "prod", "dev"]))
            return;

        if (!$isTest) {
            parent::__construct(config('services.moodle.url') . '/webservice/rest/server.php',
                $isSSO ? config('services.moodle.token_sso') : config('services.moodle.token'));
        } else {
            parent::__construct(config('services.moodle_test.url') . '/webservice/rest/server.php',
                $isSSO ? config('services.moodle_test.token_sso') : config('services.moodle_test.token'));
        }
    }

    /**
     * Set token type
     *
     * @param bool $isSSO
     */
    public function setSSO(bool $isSSO = true)
    {
        if ($this->isTest) {
            $this->setToken($isSSO ?
                config('services.moodle_test.token_sso') : config('services.moodle_test.token'));
        } else {
            $this->setToken($isSSO ?
                config('services.moodle.token_sso') : config('services.moodle.token'));
        }
    }

    /**
     * Make the request, with a bounded HTTP timeout.
     *
     * The vendored MoodleRest::request() uses file_get_contents()/stream contexts with no
     * explicit timeout, so it silently falls back to PHP's default_socket_timeout ini
     * (60s, not guaranteed). Scope an explicit timeout tightly around each individual call
     * rather than setting it once for the process, since default_socket_timeout is a
     * process-global ini setting also consulted by predis (CACHE_DRIVER=redis).
     *
     * @param string      $function
     * @param array|null  $parameters
     * @param string      $method
     *
     * @return mixed
     * @throws \Exception
     */
    public function request($function, $parameters = null, $method = self::METHOD_GET)
    {
        $previous = ini_set('default_socket_timeout', self::HTTP_TIMEOUT_SECONDS);
        try {
            return parent::request($function, $parameters, $method);
        } finally {
            ini_set('default_socket_timeout', $previous);
        }
    }

    /**
     * Get all Cohorts
     * @return mixed
     * @throws \Exception
     */
    public function getCohorts()
    {
        return $this->request("core_cohort_get_cohorts");
    }

    /**
     * Get members of all Cohorts.
     * @return array|mixed
     * @throws \Exception
     */
    public function getCohortMembers(): array
    {
        $members = [];
        foreach ($this->getCohorts() as $cohort) {
            $id = $cohort["id"];
            $members[] = $this->request("core_cohort_get_cohort_members", ["cohortids" => [0 => $id]])[0];
        }

        return $members;
    }

    /**
     * Get an array of all categories.
     * @return mixed
     * @throws \Exception
     */
    public function getCategories()
    {
        if ($this->categoriesCache === null) {
            $this->categoriesCache = $this->request("core_course_get_categories");
        }

        return $this->categoriesCache;
    }

    /**
     * Get single category
     *
     * @param int $id Category ID
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCategory(int $id): array
    {
        return $this->request("core_course_get_categories",
            ["criteria" => [0 => ["key" => "id", "value" => $id]]]);
    }

    /**
     * Get Category Context or ID
     *
     * @param string|null $short   IDNumber
     * @param bool        $context Short is Context
     * @param bool        $full    Return full array
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function getCategoryFromShort(?string $short, bool $context = false, bool $full = false)
    {
        if (is_null($short)) {
            return null;
        }

        foreach ($this->getCategories() as $category) {
            if ($category["idnumber"] === $short) {
                if ($full) {
                    return $context ? array_merge($category,
                        ["context" => $this->getContext($category["id"], "coursecat")]) : $category;
                }

                return $context ? $this->getContext($category["id"], "coursecat") : $category['id'];
            }
        }

        return null;
    }

    /**
     * Get All Subcategories of Parent
     *
     * @param int|null $parent        ID or Context
     * @param bool     $includeParent Include parent in return
     * @param bool     $context       Parent is Context
     * @param bool     $full          Return full array
     *
     * @return array
     * @throws \Exception
     */
    public function getAllSubcategories(
        ?int $parent,
        bool $includeParent = false,
        bool $context = false,
        bool $full = false
    ): array {
        if (is_null($parent)) {
            return [];
        }

        $categories = $this->request("core_course_get_categories",
            ["criteria" => [0 => ["key" => "parent", "value" => $parent]]]);
        if ($includeParent) {
            return $full ? array_merge($this->getCategory($parent), $categories) : array_merge([$parent],
                collect($categories)->pluck($context ? "context" : "id")->toArray());
        }

        return $full ? $categories :
            collect($categories)->pluck($context ? "context" : "id")->toArray();
    }

    /**
     * Create top-level category
     *
     * @param string $id
     * @param string $name
     *
     * @return mixed
     * @throws \Exception
     */
    public function createCategory(string $id, string $name)
    {
        return $this->request("core_course_create_categories", [
            'categories' => [
                0 => [
                    'name'     => $name,
                    'idnumber' => $id,
                ]
            ]
        ], self::METHOD_POST);
    }

    /**
     * Delete category.
     *
     * @param int $id
     *
     * @return mixed
     * @throws \Exception
     */
    public function deleteCategory(int $id)
    {
        return $this->request("core_course_delete_categories", [
            'categories' => [
                0 => [
                    'id'        => $id,
                    'recursive' => 1
                ]
            ]
        ], self::METHOD_POST);
    }

    /**
     * Get User Information
     *
     * @param string $cid
     *
     * @return mixed
     * @throws \Exception
     */
    public function getUser(string $cid)
    {
        return $this->request("core_user_get_users", ['criteria' => [0 => ['key' => 'idnumber', 'value' => $cid]]]);
    }

    /**
     * Check if user exists in Moodle database.
     *
     * @param int $cid
     *
     * @return bool|int
     * @throws \Exception
     */
    public function getUserId(int $cid)
    {
        $user = $this->getUser($cid)["users"][0] ?? [];
        if (empty($user)) {
            return false;
        }

        return $user["id"];
    }

    /**
     * Get CID from Moodle UID
     *
     * @param int $uid
     *
     * @return null|int
     * @throws \Exception
     */
    public function getCidFromUserId(int $uid): ?int
    {
        $user = DB::connection('moodle')->table('user')->where('id', $uid)->first();

        return $user->idnumber ?? null;
    }

    /**
     * Build a full CID -> Moodle user id map in a single query, to replace a
     * whole-table per-user HTTP existence check with an in-memory lookup.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllUserIdMap(): \Illuminate\Support\Collection
    {
        return DB::connection('moodle')->table('user')
            ->where('deleted', 0)
            ->whereNotNull('idnumber')
            ->where('idnumber', '!=', '')
            ->pluck('id', 'idnumber');
    }

    /**
     * Create user.
     *
     * @param \App\User $user
     *
     * @return false|mixed
     * @throws \Exception
     */
    public function createUser(User $user)
    {
        if (!$user) {
            return false;
        }

        return $this->request("core_user_create_users", [
            'users' => [
                0 => [
                    'createpassword' => 0,
                    'username'       => $user->cid,
                    'password'       => env('APP_KEY'),
                    'auth'           => 'manual',
                    'firstname'      => $user->fname,
                    'lastname'       => $user->lname,
                    'email'          => $user->email,
                    'maildisplay'    => 0,
                    'idnumber'       => $user->cid,
                    'mailformat'     => 1,
                ]
            ]
        ], self::METHOD_POST);
    }

    /**
     * Update user.
     *
     * @param User $user
     * @param int $id
     *
     * @return mixed
     * @throws Exception
     */
    public function updateUser(User $user, int $id)
    {
        if (!$user) {
            return false;
        }

        return $this->request("core_user_update_users", [
            'users' => [
                0 => [
                    'id'        => $id,
                    'firstname' => $user->fname,
                    'lastname'  => $user->lname,
                    'email'     => $user->email
                ]
            ]
        ], self::METHOD_POST);
    }

    /**
     * Bulk update Users
     *
     * @param array $items Each item: ['id' => int, 'fname' => string, 'lname' => string, 'email' => string]
     *
     * @return mixed
     * @throws Exception
     */
    public function updateUsersBulk(array $items)
    {
        return $this->request("core_user_update_users", [
            'users' => array_values(array_map(fn ($item) => [
                'id'        => $item['id'],
                'firstname' => $item['fname'],
                'lastname'  => $item['lname'],
                'email'     => $item['email']
            ], $items))
        ], self::METHOD_POST);
    }

    /**
     * Create Cohort
     *
     * @param string $id
     * @param string $name
     * @param string $type    Scope of Cohort
     * @param string $typeval Scope of Cohort - Identifier
     *
     * @return mixed
     * @throws \Exception
     */
    public function createCohort(string $id, string $name, string $type = 'system', string $typeval = '')
    {
        return $this->request("core_cohort_create_cohorts",
            [
                'cohorts' => [
                    0 => [
                        'categorytype' =>
                            [
                                'type'  => $type,
                                'value' => $typeval,
                            ],
                        'idnumber'     => $id,
                        'name'         => $name
                    ]
                ]
            ]);
    }

    /**
     * Assign user to Cohort.
     *
     * @param int    $uid     User ID
     * @param string $cnumber Cohort IDNumber
     *
     * @return mixed
     * @throws \Exception
     */
    public function assignCohort(int $uid, string $cnumber)
    {
        return $this->request("core_cohort_add_cohort_members", [
            "members" => [
                0 => [
                    "cohorttype" => [
                        'type'  => 'idnumber',
                        'value' => $cnumber
                    ],
                    "usertype"   => [
                        'type'  => 'id',
                        'value' => $uid
                    ]
                ]
            ]
        ]);
    }

    /**
     * Build a cohort idnumber => id map in a single query, memoized for this instance.
     * Lets the sync diff a user's desired cohorts (expressed as idnumbers) against their
     * current memberships (stored as cohort ids) without an HTTP round-trip per cohort.
     *
     * @return array<string,int>
     */
    public function getCohortIdMap(): array
    {
        if ($this->cohortIdMapCache === null) {
            $this->cohortIdMapCache = DB::connection('moodle')->table('cohort')
                ->whereNotNull('idnumber')
                ->where('idnumber', '!=', '')
                ->pluck('id', 'idnumber')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        return $this->cohortIdMapCache;
    }

    /**
     * Read current cohort memberships for a set of Moodle user ids in a single query,
     * so a bulk sync can diff desired vs. current state without a per-user round-trip.
     *
     * @param int[] $uids Moodle user ids
     *
     * @return array<int,int[]> userid => list of cohort ids they currently belong to
     */
    public function getCohortMembershipsForUsers(array $uids): array
    {
        if (empty($uids)) {
            return [];
        }

        $map = [];
        DB::connection('moodle')->table('cohort_members')
            ->whereIn('userid', $uids)
            ->get(['userid', 'cohortid'])
            ->each(function ($row) use (&$map) {
                $map[(int) $row->userid][] = (int) $row->cohortid;
            });

        return $map;
    }

    /**
     * Remove many cohort memberships in a single request. Routed through the Web Service
     * (not a direct DB delete) so Moodle's enrol_cohort sync unenrols the user from the
     * cohort's linked courses properly.
     *
     * @param array $items Each: ['uid' => int, 'cohortid' => int]
     *
     * @return mixed
     * @throws \Exception
     */
    public function removeCohortsBulk(array $items)
    {
        return $this->request("core_cohort_delete_cohort_members", [
            "members" => array_values(array_map(fn ($item) => [
                "cohortid" => $item['cohortid'],
                "userid"   => $item['uid']
            ], $items))
        ], self::METHOD_POST);
    }

    /**
     * Assign many users to Cohorts in a single request.
     *
     * @param array $items Each: ['uid' => int, 'cnumber' => string]
     *
     * @return mixed
     * @throws \Exception
     */
    public function assignCohortsBulk(array $items)
    {
        return $this->request("core_cohort_add_cohort_members", [
            "members" => array_values(array_map(fn ($item) => [
                "cohorttype" => [
                    'type'  => 'idnumber',
                    'value' => $item['cnumber']
                ],
                "usertype"   => [
                    'type'  => 'id',
                    'value' => $item['uid']
                ]
            ], $items))
        ], self::METHOD_POST);
    }

    /**
     * Unassign Cohort
     *
     * @param int $uid
     * @param int $cid
     *
     * @return mixed
     * @throws \Exception
     */
    public function removeCohort(int $uid, int $cid)
    {
        return $this->request("core_cohort_delete_cohort_members",
            ["members" => [0 => ["cohortid" => $cid, "userid" => $uid]]]);
    }

    /**
     * Remove user from all Cohorts.
     *
     * @param int $uid User ID
     */
    public function clearUserCohorts(int $uid)
    {
        DB::connection('moodle')->table('cohort_members')->where('userid', $uid)->delete();
    }

    /**
     * Assign Role to User in Context
     *
     * @param int      $uid     User ID
     * @param int|null $cid     Context ID
     * @param string   $role    Role String
     * @param string   $context Context Type
     *
     * @return mixed
     * @throws \Exception
     */
    public function assignRole(int $uid, ?int $cid, string $role, string $context)
    {
        return $this->request("core_role_assign_roles", [
            "assignments" => [
                0 => [
                    "roleid"       => $this->roleIds[$role],
                    "userid"       => $uid,
                    "contextid"    => $cid,
                    "contextlevel" => $context
                ]
            ]
        ]);
    }

    /**
     * Assign many Roles in a single request.
     *
     * @param array $items Each: ['uid' => int, 'cid' => int|null, 'role' => string, 'context' => string]
     *
     * @return mixed
     * @throws \Exception
     */
    public function assignRolesBulk(array $items)
    {
        return $this->request("core_role_assign_roles", [
            "assignments" => array_values(array_map(fn ($item) => [
                "roleid"       => $this->roleIds[$item['role']],
                "userid"       => $item['uid'],
                "contextid"    => $item['cid'],
                "contextlevel" => $item['context']
            ], $items))
        ], self::METHOD_POST);
    }

    /**
     *
     * Remove Role from User in Context
     *
     * @param int      $uid     User ID
     * @param int|null $cid     Context ID
     * @param string   $role    Role String
     * @param string   $context Context Type
     *
     * @return mixed
     * @throws \Exception
     */
    public function unassignRole(int $uid, ?int $cid, string $role, string $context)
    {
        return $this->request("core_role_unassign_roles", [
            "unassignments" => [
                0 => [
                    "roleid"       => $this->roleIds[$role],
                    "userid"       => $uid,
                    "contextid"    => $cid,
                    "contextlevel" => $context
                ]
            ]
        ]);
    }

    /**
     * Remove user from all Mentor roles.
     *
     * @param int      $cid
     * @param int|null $uid User ID
     *
     * @return int
     */
    public function unassignMentorRoles(int $cid, ?int $uid = null): int
    {
        try {
            $userid = $uid ?? $this->getUserId($cid);
            if (!$userid) {
                return 0;
            }
        } catch (Exception $e) {
            return 0;
        }

        return DB::connection('moodle')->table('role_assignments')->where('userid', $userid)->where('roleid',
            $this->roleIds['MTR'])->delete();
    }

    /**
     * Clear User's roles
     *
     * @param int        $uid User ID
     * @param bool       $isMtr
     * @param array|null $contexts
     *
     * @return void
     */
    public function clearUserRoles(int $uid, bool $isMtr = false, array $contexts = null)
    {
        if (is_array($contexts)) {
            foreach ($contexts as $context) {
                if ($isMtr) {
                    DB::connection('moodle')->table('role_assignments')->where('userid', $uid)->where('contextid',
                        $context)->where('roleId', '!=', $this->roleIds['MTR'])->delete();
                } else {
                    DB::connection('moodle')->table('role_assignments')->where('userid', $uid)->where('contextid',
                        $context)->delete();
                }
            }
        } else {
            $assignments = DB::connection('moodle')->table('role_assignments')->selectRaw(config('database.connections.moodle.prefix') . 'role_assignments.id')
                ->leftJoin('context', 'role_assignments.contextid', '=', 'context.id')
                ->where('role_assignments.userid', $uid)
                ->where(function ($query) {
                    $query->where('context.contextlevel', self::CONTEXT_COURSECAT);
                    $query->orWhere(function ($query) {
                        $query->where('context.contextlevel', self::CONTEXT_COURSE);
                        $query->where('context.path', 'LIKE',
                            '%' . self::CATEGORY_CONTEXT_VATUSA . '/' . self::CATEGORY_CONTEXT_VATUSA_EXAMS . '/%');
                        $query->where('role_assignments.roleid', '!=', $this->roleIds['STU']);
                    });
                })
                ->where('role_assignments.component', '!=', 'enrol_cohort')
                ->where('role_assignments.component', '!=', 'enrol_coursecompleted')
                ->get()->pluck('id');
            foreach ($assignments as $id) {
                DB::connection('moodle')->table('role_assignments')->where('id', $id)->delete();
            }
        }
    }

    /**
     * Get Context ID for an instance
     *
     * @param int    $id   Instance ID
     * @param string $type Instance Type
     *
     * @return mixed
     */
    public
    function getContext(
        int $id,
        string $type
    ) {
        $level = "CONTEXT_" . strtoupper($type);

        return DB::connection('moodle')->table('context')->where('instanceid', $id)->where('contextlevel',
            $this->getConstant($level))->pluck('id')->first();
    }

    /**
     * Get Courses
     *
     * @param int|null $catid
     *
     * @return mixed
     * @throws \Exception
     */
    public
    function getCoursesInCategory(
        int $catid = null
    ) {
        $key = $catid ?? 0;
        if (!array_key_exists($key, $this->coursesInCategoryCache)) {
            $params = $catid ? ["field" => "category", "value" => $catid] : [];
            $this->coursesInCategoryCache[$key] = $this->request("core_course_get_courses_by_field",
                $params)["courses"];
        }

        return $this->coursesInCategoryCache[$key];
    }

    public
    function getAcademyCategoryIds()
    {
        return $this->getAllSubcategories(self::CATEGORY_ID_VATUSA, true);
    }

    public
    function getConstants()
    {
        return (new ReflectionClass(self::class))->getConstants();
    }

    /**
     * Get specific class constant
     *
     * @param string $constant
     *
     * @return int|null
     */
    public
    function getConstant(
        string $constant
    ): ?int {
        return $this->getConstants()[$constant] ?? null;
    }

    public
    function getAcademyCategoryContexts()
    {
        return array_filter((new ReflectionClass(self::class))->getConstants(), function ($key) {
            return str_contains($key, "CATEGORY_CONTEXT");
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Enrol User in Course
     *
     * @param int      $uid User ID
     * @param int      $cid Course ID
     * @param int|null $rid Role ID
     *
     * @return mixed
     * @throws \Exception
     */
    public
    function enrolUser(
        int $uid,
        int $cid,
        ?int $rid = null
    ) {
        if (is_null($rid)) {
            $rid = $this->roleIds['STU'];
        }

        return $this->request("enrol_manual_enrol_users",
            ["enrolments" => [0 => ["roleid" => $rid, "userid" => $uid, "courseid" => $cid]]]);
    }

    /**
     * Enrol many users in courses in a single request.
     *
     * @param array $items Each: ['uid' => int, 'cid' => int, 'rid' => int|null]
     *
     * @return mixed
     * @throws \Exception
     */
    public
    function enrolUsersBulk(
        array $items
    ) {
        return $this->request("enrol_manual_enrol_users", [
            "enrolments" => array_values(array_map(fn ($item) => [
                "roleid"   => $item['rid'] ?? $this->roleIds['STU'],
                "userid"   => $item['uid'],
                "courseid" => $item['cid']
            ], $items))
        ], self::METHOD_POST);
    }

    /**
     * Unenrol User from Course
     *
     * @param int      $uid User ID
     * @param int      $cid Course ID
     * @param int|null $rid Role ID
     *
     * @return mixed
     * @throws \Exception
     */
    public
    function unenrolUser(
        int $uid,
        int $cid,
        ?int $rid = null
    ) {
        if (is_null($rid)) {
            $rid = $this->roleIds['STU'];
        }

        return $this->request("enrol_manual_unenrol_users",
            ["enrolments" => [0 => ["roleid" => $rid, "userid" => $uid, "courseid" => $cid]]]);
    }

    /**
     * Get quiz attempts
     *
     * @param int      $quizid The Quiz ID
     * @param int|null $cid    The user's CID
     * @param int|null $uid
     *
     * @return array
     */
    public
    function getQuizAttempts(
        int $quizid,
        ?int $cid,
        ?int $uid = null
    ): array {
        try {
            $userid = $uid ?? $this->getUserId($cid);
            if (!$userid) {
                return [];
            }

            $attempts = $this->request("mod_quiz_get_user_attempts",
                    ["quizid" => $quizid, "userid" => $userid])['attempts'] ?? [];

            for ($i = 0; $i < count($attempts); $i++) {
                $review = $this->request("mod_quiz_get_attempt_review",
                        ["attemptid" => $attempts[$i]['id']]) ?? [];
                if (!empty($review)) {
                    $attempts[$i]['grade'] = round(floatval($review['grade']));
                } else {
                    return [];
                }
            }

            return $attempts;
        } catch (Exception $e) {
            return [];
        }
    }

    public
    function getUserEnrolmentInfo(
        ?int $uid,
        int $enrolmentId
    ) {
        return DB::connection('moodle')->table('user_enrolments')
            ->where('userid', $uid)
            ->where('enrolid', $enrolmentId)
            ->first();
    }

    public
    function getUserEnrolmentTimestamp(
        ?int $uid,
        int $enrolmentId
    ) {
        $info = $this->getUserEnrolmentInfo($uid, $enrolmentId);

        return $info->timecreated ?? false;

    }


}