<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\AuthHelper;
use App\Helpers\RatingHelper;
use App\Role;
use App\User;
use Illuminate\Http\Request;

class IntegrationController extends APIController
{
    public function getStaffMembers(Request $request) {
        $hasApiKey = AuthHelper::validApiKeyv2($request->input('apikey', null));

        $makeOutput = function($user, $hasApiKey) {
            $c = $user->toArray();
            if (!$hasApiKey) {
                //API Key Required
                unset($c['email']);
            }
            unset($c['flag_broadcastOptedIn']);
            unset($c['flag_preventStaffAssign']);
            unset($c['created_at']);
            unset($c['updated_at']);
            unset($c['flag_needbasic']);
            unset($c['flag_xferOverride']);
            unset($c['promotion_eligible']);
            unset($c['transfer_eligible']);
            unset($c['lastactivity']);
            unset($c['last_cert_sync']);
            $c['rating_short'] = RatingHelper::intToShort($c['rating']);
            $c['visiting_facilities'] = $user->visits->toArray();
            return $c;
        };

        $staffRoles = [
            'ATM', 'DATM', 'TA', 'EC', 'FE', 'WM', 'INS', 'MTR', 'DICE', 'USWT',
            'US1', 'US2', 'US3', 'US4', 'US5', 'US6', 'US7', 'US8', 'US9', 'SMT',
            'EMAIL' // Used for manually added email users
        ];

        // Get CIDs of staff members
        $staffCids = Role::whereIn('role', $staffRoles)->pluck('cid')->toArray();

        // Get CIDs of users with specific ratings
        $userCidsByRating = User::whereIn('rating', [8, 10])
            ->where('flag_homecontroller', 1)
            ->pluck('cid')
            ->toArray();

        // Combine and get unique CIDs
        $allCids = array_unique(array_merge($staffCids, $userCidsByRating));

        // Get all users and their visits in one go
        $users = User::with('visits')->whereIn('cid', $allCids)->get();

        $controllers = [];
        foreach ($users as $user) {
            $controllers[] = $makeOutput($user, $hasApiKey);
        }

        return response()->api($controllers);
    }

}