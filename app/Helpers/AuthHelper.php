<?php
namespace App\Helpers;

use App\User;
use App\Exceptions\JWTTokenException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthHelper {
    public static function getAuthUser() {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            throw new JWTTokenException("token_expired");
        } catch (TokenInvalidException $e) {
            throw new JWTTokenException("token_invalid");
        } catch (JWTException $e) {
            return response()->json(generate_error("token_absent", true), 404);
        } catch (Exception $e) {
            return false;
        }

        return $user;
    }

    public static function validToken() {
        if (JWTAuth::parseToken()->authenticate()) {
            return true;
        }

        return false;
    }
}
