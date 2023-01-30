<?php
namespace App\Http\Controllers\API\v2;

use \App\Http\Controllers\Controller as BaseController;

/**
 * Class ApiController
 *
 * @package App\Http\Controllers\API\v2
 *
 * @SWG\Swagger(
 *     basePath="/v2",
 *     host="api.vatusa.net",
 *     schemes={"https"},
 *     @SWG\Info(
 *         version="2.3",
 *         title="VATUSA API",
 *         description="VATUSA APIv2 Documentation.
            Authentication methods are: <ul>
            <li> JSON Web Tokens (Translated from Laravel session)</li>
            <li> API Keys (Issued to facilities)</li></ul>
            <p>Method security, if applicable, is indicated in brackets at the end of each endpoint title.</p>
            <p>Security classification: <ul>
                <li><strong>Private:</strong> CORS Restricted (Internal)</li>
                <li><strong>Auth:</strong> Accepts JWT</li>
                <li><strong>Key:</strong> Accepts API Key, Session Cookie, or JWT</li>
            </ul></p>
            <p>Facilities that have a APIv2 JWK defined in facility settings will have the data encapsulated in a
            signed package.  For more information, please see the IT section of the VATUSA forums.</p>
            <p>To prevent database changes in a development environment, you can either use your API sandbox key
             or pass the <strong>?test</strong> query parameter with the call. Whether or not <strong>?test</strong> is present,
             if both Sandbox JWK and Dev URL are configured, and the domains match, the response will be formatted according to JSON Web Signature, RFC 7515. </p>",
 *         x={
 *           "logo": {
 *             "url": "https://www.vatusa.net/img/logo-full.png",
 *           },
 *         },
 *         @SWG\Contact(name="Blake Nahin", url="https://www.vatusa.net/info/members"),
 *     ),
 *     @SWG\Tag(name="academy",description="Interaction with Moodle (Academy)"),
 *     @SWG\Tag(name="auth",description="Internal authentication handling commands for use by VATUSA Web Systems to translate Laravel Sessions into JSON Web Tokens"),
 *     @SWG\Tag(name="email",description="User email addresses for staff members"),
 *     @SWG\Tag(name="facility",description="Facility management actions"),
 *     @SWG\Tag(name="public",description="Public feeds of events and news"),
 *     @SWG\Tag(name="rating",description="Rating changes"),
 *     @SWG\Tag(name="role",description="Role handling"),
 *     @SWG\Tag(name="solo",description="Solo certifications"),
 *     @SWG\Tag(name="support",description="Support Center"),
 *     @SWG\Tag(name="survey", description="Survey management"),
 *     @SWG\Tag(name="tmu",description="Traffic Management Unit - Notices (NTOS)"),
 *     @SWG\Tag(name="training", description="Centralized training records"),
 *     @SWG\Tag(name="transfer",description="Transfer request submission and handling actions"),
 *     @SWG\Tag(name="user",description="User account management actions"),
 * )
 */
class APIController extends BaseController {
    public function __construct()
    {
        //Log
    }
}

/**
 * @SWG\SecurityScheme(
 *   securityDefinition="jwt",
 *   type="apiKey",
 *   in="header",
 *   name="JSON Web Token",
 *   description="JSON Web Token translated from Laravel session"
 * )
 */
/**
 * @SWG\SecurityScheme(
 *   securityDefinition="session",
 *   type="apiKey",
 *   in="header",
 *   name="Session Cookie (Authentication on main website)"
 * )
 */
/**
 * @SWG\SecurityScheme(
 *     securityDefinition="apikey",
 *     type="apiKey",
 *     in="query",
 *     name="apikey",
 *     description="API Key issued to facilities and generated on Facility Management page"
 * )
 */

/**
 *
 * @SWG\Definition(
 *     definition="error",
 *     type="object",
 *     @SWG\Property(
 *         property="status",
 *         type="string",
 *         example="error",
 *     ),
 *     @SWG\Property(
 *         property="message",
 *         type="string",
 *         example="not_logged_in",
 *     ),
 *     @SWG\Property(
 *         property="exception",
 *         type="string"
 *     ),
 *     @SWG\Property(
 *         property="testing",
 *         type="boolean",
 *         example="false",
 *     ),
 * ),
 * @SWG\Definition(
 *     definition="OK",
 *     type="object",
 *     @SWG\Property(
 *         property="status",
 *         type="string",
 *         example="OK",
 *     ),
 *     @SWG\Property(
 *         property="testing",
 *         type="boolean",
 *         example="false",
 *     ),
 * ),
 * @SWG\Definition(
 *     definition="OKID",
 *     type="object",
 *     @SWG\Property(
 *         property="status",
 *         type="string",
 *         example="OK",
 *     ),
 *     @SWG\Property(
 *         property="id",
 *         type="integer",
 *         example=0,
 *     ),
 * ),
 */
