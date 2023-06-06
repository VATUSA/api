<?php

namespace App\Http\Controllers\API\v2;

use App\Facility;
use App\Helpers\RoleHelper;
use Illuminate\Http\Request;
use App\Bucket;

class BucketController extends APIController
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @OA\\Get(
     *     path="/bucket/(facility)",
     *     summary="Get bucket information. [Auth]",
     *     description="Get bucket information. Requires JWT/Session Key.",
     *     responses={"application/json"},
     *     tags={"auth"},
     *     security={"session","jwt"},
     *     @OA\\Parameter(name="facility", in="path", @OA\Schema(type="string"), description="Facility IATA ID"),
     *     @OA\\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @OA\\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @OA\\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @OA\\Schema(
     *             type="object",
     *             @OA\\Items(ref="#/components/schemas/Bucket"),
     *        )
     *     )
     * )
     */
    public function getBucket(Request $request, $facility) {
        if (!\Auth::check()) return response()->unauthenticated();
        $f = Facility::find($facility);
        if (!$f) return response()->notfound(["addl" => "Invalid facility"]);
        if (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $facility,["ATM","DATM","WM"])) {
            return response()->forbidden();
        }
        $bucket = Bucket::where('facility', $facility)->first();
        if (!$bucket) return response()->notfound(["addl" => "No bucket defined"]);

        return response()->ok(['bucket' => $bucket->toArray()]);
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse|string
     *
     * @OA\\Post(
     *     path="/bucket/(facility)",
     *     summary="Create bucket. Requires JWT/Session Key [Auth]",
     *     description="Create bucket. Requires JWT/Session Key.",
     *     responses={"application/json"},
     *     tags={"auth"},
     *     security={"session","jwt"},
     *     @OA\\Parameter(name="facility", in="path", @OA\Schema(type="string"), description="Facility IATA ID"),
     *     @OA\\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Unauthorized"}},
     *     ),
     *     @OA\\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Forbidden"}},
     *     ),
     *     @OA\\Response(
     *         response="404",
     *         description="Not found",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Not found"}},
     *     ),
     *     @OA\\Response(
     *         response="409",
     *         description="Conflict, bucket exists",
     *         @OA\\Schema(ref="#/components/schemas/error"),
     *         content={"application/json":{"status"="error","msg"="Conflict"}},
     *     ),
     *     @OA\\Response(
     *         response=200,
     *         description="Return JSON Token.",
     *         @OA\\Schema(
     *             type="object",
     *             @OA\\Items(ref="#/components/schemas/Bucket"),
     *        )
     *     )
     * )
     */
    public function postBucket(Request $request, $facility) {
        if (!\Auth::check()) return response()->unauthenticated();
        $f = Facility::find($facility);
        if (!$f) return response()->notfound(["addl" => "Invalid facility"]);
        if (!RoleHelper::isVATUSAStaff() && !RoleHelper::has(\Auth::user()->cid, $facility,["ATM","DATM","WM"])) {
            return response()->forbidden();
        }
        $bucket = Bucket::where('facility', $facility)->first();
        if ($bucket) return response()->conflict();

        $facility = strtolower($facility);

        $s3 = \AWS::createClient('s3');
        $result = $s3->createBucket([
            'ACL' => 'private',
            'Bucket' => "$facility.backups.vatusa.net",
            'CreateBucketConfiguration' => [
                'LocationConstraint' => 'us-west-2'
            ]
        ]);
        // Rule:
        // - After 30 days, transition to glacier [cold storage]
        // - After 730 days (2 years), delete object
        $result = $s3->putBucketLifecycleConfiguration([
            'Bucket' => "$facility.backups.vatusa.net",
            'LifecycleConfiguration' => [
                'Rules' => [
                    [
                        'Filter' => [
                            'Prefix' => '',
                        ],
                        'Expiration' => [
                            'Days' => 729,
                        ],
                        'NoncurrentVersionExpiration' => [
                            'Days' => 1,
                        ],
                        'ID' => 'glacier',
                        'Status' => 'Enabled',
                        'Transitions' => [
                            [
                                'Days' => 30,
                                'StorageClass' => 'GLACIER'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $bucket = new Bucket();
        $bucket->facility = $facility;
        $bucket->name = "$facility.backups.vatusa.net";
        $bucket->arn = $bucket->arn();

        $iam = \AWS::createClient("IAM");
        $user_result = $iam->createUser([
            'UserName' => "backups_$facility"
        ]);
        $policy = '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": ["' . $bucket->arn() . '"]
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": ["' . $bucket->arn . '/*"]
    }
  ]
}';
        $policy_result = $iam->createPolicy([
            'PolicyName' => "backups_$facility",
            'PolicyDocument' => $policy
        ]);
        $result = $iam->attachUserPolicy([
            'UserName' => "backups_$facility",
            'PolicyArn' => $policy_result['Policy']['Arn']
        ]);
        $access_result = $iam->createAccessKey([
            'UserName' => "backups_$facility"
        ]);
        $bucket->access_key = $access_result['AccessKey']['AccessKeyId'];
        $bucket->save();
        $bucket->log(\Auth::user()->cid,
            (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']),
            "Created bucket for $facility, access key $bucket->accesskey");

        $return = $bucket->toArray();
        $return['secret'] = $access_result['AccessKey']['SecretAccessKey'];

        return response()->ok(['bucket' => $return]);
    }
}
