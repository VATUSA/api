<?php

namespace App\Classes\OAuth;

use App\User;
use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class VatsimConnect extends GenericProvider
{
    /**
     * @var GenericProvider
     */
    private $provider;

    /**
     * Initializes the provider variable.
     */
    public function __construct()
    {
        parent::__construct([
            'clientId'                => config('oauth.id'),
            // The client ID assigned to you by the provider
            'clientSecret'            => config('oauth.secret'),
            // The client password assigned to you by the provider
            'redirectUri'             => config('oauth.redirect'),
            'urlAuthorize'            => config('oauth.base') . '/oauth/authorize',
            'urlAccessToken'          => config('oauth.base') . '/oauth/token',
            'urlResourceOwnerDetails' => config('oauth.base') . '/api/user',
            'scopes'                  => config('oauth.scopes'),
            'scopeSeparator'          => ' '
        ]);
    }

    /**
     * Gets an (updated) user token
     *
     * @param \League\OAuth2\Client\Token\AccessToken $token
     *
     * @return \League\OAuth2\Client\Token\AccessTokenInterface
     * @return null
     */
    public static function updateToken($token)
    {
        $controller = new VatsimConnect;
        try {
            return $controller->getAccessToken('refresh_token', [
                'refresh_token' => $token->getRefreshToken()
            ]);
        } catch (IdentityProviderException $e) {
            return null;
        }
    }

    /**
     * Handle redirect to Connect
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function redirect(Request $request)
    {
        $url = $this->getAuthorizationUrl();
        $request->session()->put('oauthstate', $this->getState());

        return redirect()->away($url);
    }

    /**
     * Validate SSO return
     *
     * @param \Illuminate\Http\Request $request
     * @param null                     $token
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function validate(Request $request, $token = null)
    {
        $code = $request->input('code', null);
        $state = $request->input('state', null);

        if (!$code/* || !$state || $state !== $request->get('oauthstate')*/) {
            $request->session()->forget("return");
            $error = "Invalid response from VATSIM, please try again. If this error persists, contact VATUSA12.";

            return redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }

        $request->session()->forget('oauthstate');

        if (!$token) {
            try {
                $token = $this->getAccessToken('authorization_code', [
                    'code' => $code
                ]);
            } catch (IdentityProviderException $e) {
                $request->session()->forget("return");
                $error = "An error occurred while logging in with VATSIM, please try again. If this error persists, contact VATUSA12.";

                return redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
            }
        }
        $resource = json_decode(json_encode($this->getResourceOwner($token)->toArray()));
        if (!isset($resource->data, $resource->data->cid, $resource->data->vatsim,
                $resource->data->vatsim->rating, $resource->data->vatsim->division,
                $resource->data->personal, $resource->data->personal->email,
                $resource->data->personal->name_first, $resource->data->personal->name_last) || $resource->data->oauth->token_valid != true) {
            $request->session()->forget("return");
            $error = "Insufficient user data provided. In order to login, you must allow us to continuously recieve all of your VATSIM data: full name, email, and rating information.
             Please try again. If this error persists, contact VATUSA12.";

            return redirect(env('SSO_RETURN_HOME_ERROR'))->with('error', $error);
        }

        $user = User::find($resource->data->cid);

        if ($user && $resource->data->oauth->token_valid == true) {
            //Update user token
            $user->update([
                'access_token'  => $token->getToken(),
                'refresh_token' => $token->getRefreshToken(),
                'token_expires' => $token->getExpires()
            ]);
        }

        return $resource->data;
    }
}
