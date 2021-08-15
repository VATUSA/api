<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OAuthClient
 * @package App
 *
 * @SWG\Definition(
 *     definition="oauth_client",
 *     type="object",
 *     @SWG\Property(property="name", type="string", description="Facility identifier"),
 *     @SWG\Property(property="client_id", type="string", description="OAuth Client ID"),
 *     @SWG\Property(property="client_secret", type="string", description="OAuth Client Secret (only passed on creation or when changed)"),
 *     @SWG\Property(property="redirect_uri", type="array", description="Array of authorized redirect URIs", @SWG\Items(type="string")),
 *     @SWG\Property(property="created_at", type="string", description="Date created"),
 *     @SWG\Property(property="updated_at", type="string", description="Date updated"),
 * )
 */
class OAuthClient extends Model
{
    protected $table = "oauth_clients";
    protected $hidden = [
        'id',
        'client_secret'
    ];

    public function getDateFormat()
    {
        return 'Y-m-d H:i:s.u';
    } 
}
