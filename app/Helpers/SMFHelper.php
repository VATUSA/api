<?php
namespace App\Classes;

use App\Helpers\RoleHelper;
use App\User;
use App\Helpers\RatingHelper;

/**
 * Class SMFHelper
 * @package App\Classes
 */
class SMFHelper
{
    /**
     * @param $cid
     * @param $grp
     * @param string $addl
     */
    public static function setGroups($cid, $grp, $addl = "")
    {
        \DB::connection('forum')->table("smf_members")->where('member_name', $cid)
            ->update([
                'id_group' => $grp,
                'additional_groups' => $addl
            ]);
    }

    /**
     * @param $facility
     * @return int
     */
    public static function findFacilityStaffGroup($facility)
    {
        return static::findGroup($facility . " Staff");
    }

    /**
     * @param $group
     * @return int
     */
    public static function findGroup($group)
    {
        $staff = 0;
        $grp = \DB::connection('forum')->table("smf_membergroups")->where('group_name', $group)->first();
        if ($grp) { $staff = $grp->id_group; }
        return $staff;
    }

    /**
     * @param $cid
     */
    public static function setPermissions($cid)
    {
        $role = "";
        $addl = "";
        $grp = "";

        $user = User::find($cid);

        if ($user->rating == RatingHelper::shortToInt("ADM")) {
            static::setGroups($cid, static::findGroup("VATSIM Staff")); return;
        }

        if (RoleHelper::isSeniorStaff($user->cid, $user->facility))
            $role = "ATM";
        elseif (RoleHelper::has($user->cid, $user->facility, "TA"))
            $role = "TA";
        elseif (RoleHelper::has($user->cid, $user->facility, "EC"))
            $role = "EC";
        elseif (RoleHelper::has($user->cid, $user->facility, "FE"))
            $role = "FE";
        elseif (RoleHelper::has($user->cid, $user->facility, "WM"))
            $role = "WM";

        if ($role) {
            $grp = static::findFacilityStaffGroup($user->facility);
        } else {
            $grp = static::findGroup("Members");
        }

        if (RoleHelper::isVATUSAStaff($cid, true)) {
            $grp = static::findGroup("VATUSA Staff");
            $role = "";
            if (RoleHelper::has($cid, "ZHQ", "US1") ||
                RoleHelper::has($cid, "ZHQ", "US2") ||
                RoleHelper::has($cid, "ZHQ", "US6")) {
                $role = "Administrator";
            }
        }
        if ($role) {
            $addl = static::findGroup($role);
        }

        if (RoleHelper::isWebTeam($cid)) {
            if ($addl) $addl .= ",";
            $addl .= static::findGroup("Web Team");
        }
        if (RoleHelper::has($cid, 'ZHQ', 'ACE')) {
            if ($addl) $addl .= ",";
            $addl .= static::findGroup("Ace Team");
        }

        static::setGroups($cid, $grp, $addl);
    }

    /**
     * @param $memberID
     * @param $board
     * @param $subject
     * @param $body
     */
    public static function createPost($memberID, $board, $subject, $body)
    {
        $smf_subject = $subject;
        $smf_subject = addslashes(htmlspecialchars($smf_subject));
        $smf_body = addslashes(htmlspecialchars($body));
        $smf_board = $board;
        $smf_member = $memberID; //Website psuedo user
        require_once(base_path() . "/../public_html/forums/SSI.php");
        require_once(base_path() . "/../public_html/forums/Sources/Subs-Post.php");

        $msgOptions = [
            'subject' => $smf_subject,
            'body' => $smf_body
        ];
        $topicOptions = [
            'board' => $smf_board,
        ];
        $posterOptions = [
            'id' => $smf_member
        ];
        createPost($msgOptions, $topicOptions, $posterOptions);
    }

    /**
     * @param $cid
     * @return mixed
     */
    public static function isRegistered($cid) {
        return \DB::connection("forum")->table("smf_members")->where("member_name", $cid)->count();
    }

    public static function updateData($cid, $last, $first, $email) {
        \DB::connection("forum")->table("smf_members")
                                ->where("member_name", $cid)
                                ->update([
                                    'real_name' => "$first $last",
                                    'email_address' => "$email"
                                ]);
    }
}
