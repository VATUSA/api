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
 *         @SWG\Contact(name="Daniel Hawton", url="https://www.danielhawton.com"),
 *     ),
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
 *     securityDefinition="apiKey",
 *     type="apiKey",
 *     in="query",
 *     name="api key",
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
 * )
 */