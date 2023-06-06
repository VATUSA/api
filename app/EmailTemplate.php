<?php
namespace App;

/**
 * Class EmailTemplate
 * @package App
 *
 * @OA\Schema(
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="facility_id", type="string"),
 *     @OA\Property(property="template", type="string", description="Template name"),
 *     @OA\Property(property="body", type="string", description="Template"),
 *     @OA\Property(property="created_at", type="string", description="date created"),
 *     @OA\Property(property="updated_at", type="string", description="date last modified"),
 * )
 */
class EmailTemplate extends Model {
    protected $table = 'email_templates';
}

