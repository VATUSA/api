<?php
namespace App;

/**
 * Class EmailTemplate
 * @package App
 *
 * @SWG\Definition(
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="facility_id", type="string"),
 *     @SWG\Property(property="template", type="string", description="Template name"),
 *     @SWG\Property(property="body", type="string", description="Template"),
 *     @SWG\Property(property="created_at", type="string", description="date created"),
 *     @SWG\Property(property="updated_at", type="string", description="date last modified"),
 * )
 */
class EmailTemplate extends Model {
    protected $table = 'email_templates';
}

