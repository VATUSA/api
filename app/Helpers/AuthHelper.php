<?php
namespace App\Helpers;

use Tymon\JWTAuth\JWTAuth;
use App\User;
use App\Exceptions\JWTTokenException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthHelper {
    public static function getAuthUser() {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(generate_error("user not found", true), 404);
            }
        } catch (TokenExpiredException $e) {
            throw new JWTTokenException("token_expired");
        } catch (TokenInvalidException $e) {
            throw new JWTTokenException("token_invalid");
        } catch (JWTException $e) {
            return response()->json(generate_error("token_absent", true), 404);
        } catch (Exception $e) {
            return response()->json(generate_error("other exception", true), 500);
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
