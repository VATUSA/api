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
 *         version="2.0",
 *         title="VATUSA API",
 *         description="VATUSA APIv2 Documentation.
            Authentication methods are: <ul>
            <li> JSON Web Tokens (issued by VATUSA for VATUSA services only)</li>
            <li> Session Cookies (issued by VATUSA for VATUSA services only)</li>
            <li> API Keys (issued to facilities for facility use only)</li></ul>
            <p>API methods that do not have a security method attached to it do not require a security check.</p>
            <p>Facilities that have a APIv2 JWK defined in facility settings will have the data encapisulated in a
            signed package.  For more information, please see the IT section of the VATUSA forums.</p>",
 *         x={
 *           "logo": {
 *             "url": "https://www.vatusa.net/img/logo-light.png",
 *           },
 *         },
 *         @SWG\Contact(name="Daniel Hawton", url="https://www.danielhawton.com"),
 *     ),
 *     @SWG\Tag(name="auth",description="[Live] Internal Authentication handling commands for use by VATUSA Web Systems to translate Laravel Sessions into JSON Web Tokens"),
 *     @SWG\Tag(name="cbt",description="Handle Computer Based Training actions"),
 *     @SWG\Tag(name="email",description="Handle user email addresses for staff members"),
 *     @SWG\Tag(name="exam",description="[Partly live] Exam center actions"),
 *     @SWG\Tag(name="facility",description="Facility management actions"),
 *     @SWG\Tag(name="transfer",description="Transfer request submission and handling actions"),
 *     @SWG\Tag(name="rating",description="Rating change handling"),
 *     @SWG\Tag(name="role",description="Role handling"),
 *     @SWG\Tag(name="solo",description="Solo Certification handling"),
 *     @SWG\Tag(name="stats",description="Division statistics"),
 *     @SWG\Tag(name="support",description="Support Center"),
 *     @SWG\Tag(name="survey", description="Survey"),
 *     @SWG\Tag(name="tmu", description="Traffic Management Unit"),
 *     @SWG\Tag(name="user",description="User account management actions"),
 * )
 */
class APIController extends BaseController {
    //
}

/**
 * @SWG\SecurityScheme(
 *   securityDefinition="jwt",
 *   type="apiKey",
 *   in="header",
 *   name="JSON Web Token",
 *   description="JSON Web Token"
 * )
 */
/**
 * @SWG\SecurityScheme(
 *   securityDefinition="session",
 *   type="apiKey",
 *   in="header",
 *   name="Session Cookie"
 * )
 */
/**
 * @SWG\SecurityScheme(
 *     securityDefinition="apikey",
 *     type="apiKey",
 *     in="query",
 *     name="apikey",
 *     description="API Key"
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
 * ),
 * @SWG\Definition(
 *     definition="OK",
 *     type="object",
 *     @SWG\Property(
 *         property="status",
 *         type="string",
 *         example="OK",
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
