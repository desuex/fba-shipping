<?php

namespace App\Auth;

class EnvAuthProvider implements AuthProviderInterface
{
    public function getAccessToken(): string
    {
        $token = $_ENV['AMAZON_ACCESS_TOKEN'] ?? '';
        
        if (empty($token)) {
             throw new \RuntimeException('AMAZON_ACCESS_TOKEN is not set in .env');
        }

        return $token;
    }
}