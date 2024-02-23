<?php
namespace App;

/**
 * Class EmailTemplate
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", @OA\Schema(type="integer")),
 *     @OA\Property(property="facility_id", @OA\Schema(type="string")),
 *     @OA\Property(property="template", @OA\Schema(type="string"), description="Template name"),
 *     @OA\Property(property="body", @OA\Schema(type="string"), description="Template"),
 *     @OA\Property(property="created_at", @OA\Schema(type="string"), description="date created"),
 *     @OA\Property(property="updated_at", @OA\Schema(type="string"), description="date last modified"),
 * )
 */
class EmailTemplate extends Model {
    protected $table = 'email_templates';
}

