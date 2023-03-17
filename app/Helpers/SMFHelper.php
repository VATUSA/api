<?php

namespace App\Helpers;

use App\User;

/**
 * Class SMFHelper
 * @package App\Classes
 */
class SMFHelper
{
    /**
     * @param        $cid
     * @param        $grp
     * @param string $addl
     */
    public static function setGroups($cid, $grp, $addl = "")
    {
        \DB::connection('forum')->table("smf_members")->where('member_name', $cid)
            ->update([
                'id_group'          => $grp,
                'additional_groups' => $addl
            ]);
    }

    /**
     * @param $facility
     *
     * @return int
     */
    public static function findFacilityStaffGroup($facility)
    {
        return static::findGroup($facility . " Staff");
    }

    /**
     * @param $group
     *
     * @return int
     */
    public static function findGroup($group)
    {
        $staff = 0;
        $grp = \DB::connection('forum')->table("smf_membergroups")->where('group_name', $group)->first();
        if ($grp) {
            $staff = $grp->id_group;
        }

        return $staff;
    }

    /**
     * @param $cid
     *
     * @return bool|void
     */

    public static function setPermissions($cid)
    {
        if (in_array(app()->environment(), ["livedev", "dev", "devel"])) {
            return true;
        }
        $primary = "";
        $secondary = [];
        $roles = [];

        $user = User::find($cid);

        if ($user->rating == Helper::ratingIntFromShort("ADM")) {
            if (!RoleHelper::isVATUSAStaff($cid, true, true)) {
                static::setGroups($cid, static::findGroup("VATSIM Leadership"));

                return;
            } else {
                // Allow for them to get the VATUSA Staff group
                // as secondary group if they have a VATUSA Staff role
                // per Mark Hubbert
                static::setGroups($cid, static::findGroup("VATSIM Leadership"), static::findGroup("VATUSA Staff"));

                return;
            }
        }

        if ($user->facility()->atm == $user->cid || $user->facility()->datm == $user->cid) {
            $roles[] = "ATM";
        }
        if ($user->facility()->ta == $user->cid) {
            $roles[] = "TA";
        }
        if ($user->facility()->ec == $user->cid) {
            $roles[] = "EC";
        }
        if ($user->facility()->fe == $user->cid) {
            $roles[] = "FE";
        }
        if ($user->facility()->wm == $user->cid) {
            $roles[] = "WM";
        }

        if (count($roles) > 0) {
            $primary = static::findFacilityStaff($user->facility);
        } else {
            $primary = static::findGroup("Members");
        }
        foreach ($roles as $role) {
            $secondary[] = static::findGroup($role);
        }

        if (RoleHelper::isVATUSAStaff($cid, true, true)) {
            $primary = static::findGroup("VATUSA Staff");
            if (RoleHelper::hasRole($user->cid, "ZHQ", "US1")
                || RoleHelper::hasRole($user->cid, "ZHQ", "US6")) {
                $secondary[] = static::findGroup("Administrator");
            }
        }

        if (RoleHelper::hasRole($cid, 'ZHQ', 'ACE')) {
            $secondary[] = static::findGroup("Ace Team");
        }
        if ($user->rating === Helper::ratingIntFromShort("SUP") && $primary === static::findGroup("Members")) {
            //Supervisor over Members (same perms set), WT, INSs, and MTRs
            $primary = static::findGroup("VATSIM Supervisors");
            $secondary[] = static::findGroup("Members");
        }
        if (RoleHelper::isWebTeam($cid)) {
            if ($primary === static::findGroup("Members")) {
                //WT Priority over INS, MTRs, Members
                $primary = static::findGroup("Web Team");
                $secondary[] = static::findGroup("Members");
            } else {
                $secondary[] = static::findGroup("Web Team");
            }
        }
        if (RoleHelper::isInstructor($cid)) {
            if ($primary === static::findGroup("Members")) {
                //INS Priority over Members and MTRs
                $primary = static::findGroup("Instructors");
                $secondary[] = static::findGroup("Members");
            } else {
                $secondary[] = static::findGroup("Instructors");
            }
        }

        if (RoleHelper::hasRole($cid, $user->facility, "MTR")) {
            if ($primary === static::findGroup("Members")) {
                //MTR Priority over Members
                $primary = static::findGroup("Mentors");
                $secondary[] = static::findGroup("Members");
            } else {
                $secondary[] = static::findGroup("Mentors");
            }
        }


        static::setGroups($cid, $primary, implode(",", $secondary));
    }

    public static function findFacilityStaff($facility)
    {
        return static::findGroup($facility . " Staff");
    }

    /**
     * @param $memberID
     * @param $board
     * @param $subject
     * @param $body
     */
    public static function createPost($memberID, $board, $subject, $body)
    {
        /*$smf_subject = $subject;
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
        createPost($msgOptions, $topicOptions, $posterOptions);*/
        // Coming soon?
    }

    /**
     * @param $cid
     *
     * @return mixed
     */
    public static function isRegistered($cid)
    {
        return \DB::connection("forum")->table("smf_members")->where("member_name", $cid)->count();
    }

    public static function updateData($cid, $last, $first, $email)
    {
        \DB::connection("forum")->table("smf_members")
            ->where("member_name", $cid)
            ->update([
                'real_name'     => "$first $last",
                'email_address' => "$email"
            ]);
    }
}
