<?php

namespace App\Http\Controllers\API\v2;

use App\Helpers\RatingHelper;
use App\Role;
use App\User;
use http\Env\Request;

class IntegrationController extends APIController
{
    public function getStaffMembers() {
        $staffRoles = ['ATM', 'DATM', 'TA', 'EC', 'FE', 'WM', 'INS', 'MTR'];

        $userRoles = Role::whereIn('role', $staffRoles)->get();

        $controllers = [];
        foreach ($userRoles as $r) {
            $user = $r->user();
            $c = $user->toArray();

            unset($c['flag_broadcastOptedIn']);
            unset($c['email']);
            unset($c['flag_preventStaffAssign']);

            $c['rating_short'] = RatingHelper::intToShort($c['rating']);
            $c['visiting_facilities'] = $user->visits->toArray();

            $controllers[$user->cid] = $c;
        }

        $usersByRating = User::whereIn('rating', [8, 10])->get();

        foreach ($usersByRating as $user) {
            $c = $user->toArray();

            unset($c['flag_broadcastOptedIn']);
            unset($c['email']);
            unset($c['flag_preventStaffAssign']);

            $c['rating_short'] = RatingHelper::intToShort($c['rating']);
            $c['visiting_facilities'] = $user->visits->toArray();

            $controllers[$user->cid] = $c;
        }


        return response()->api($controllers);
    }

}