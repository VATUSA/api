<?php

use Tymon\JWTAuth;
use App\User;
use App\Exceptions\JWTTokenException;

class AuthHelper {
    public static function getAuthUser() {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(generate_error("user not found", true), 404);
            }
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            throw new JWTTokenException("token_expired");
        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            throw new JWTTokenException("token_invalid");
        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(generate_error("token_absent", true), 404);
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
