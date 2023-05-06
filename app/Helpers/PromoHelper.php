<?php
namespace App\Helpers;

use App\User;
use App\Promotion;
use App\Action;

class PromoHelper
{
    public static function handle($cid, $ins, $to, $data) {
        $user = User::where('cid', $cid)->first();

        if(!VATSIMApi2Helper::updateRating($cid, $to)) {
            return 0;
        } else {
            $promo = new Promotion;
            $promo->cid = $cid;
            $promo->grantor = $ins;
            $promo->to = $to;
            $promo->from = $user->rating;
            $promo->exam = ((isset($data['exam']))?$data['exam']:'0000-00-00');
            $promo->examiner = ((isset($data['examiner'])?$data['examiner']:0));
            $promo->position = ((isset($data['position'])?$data['position']:''));
            $promo->save();

            $log = new Action;
            $log->to = $cid;
            $log->from = $ins;
            $log->log = "Rating Change: ".$user->urating->short." to ". RatingHelper::intToShort($to)." issued by "
                .Helper::nameFromCID($ins);
            $log->save();

            $user->rating = $to;
            $user->save();

            return 1;
        }
    }
}