<?php

namespace App\Auth;

interface AuthProviderInterface
{
    public function getAccessToken(): string;
}