<?php

use App\Models\User;

if (! function_exists('username_url')) {
    function username_url(string|User $username): string
    {
        $value = $username instanceof User ? $username->username : $username;

        return route('profile.show', ['user' => $value]);
    }
}

if (! function_exists('at_username')) {
    function at_username(string|User $username): string
    {
        $value = $username instanceof User ? $username->username : $username;

        return '@'.$value;
    }
}
