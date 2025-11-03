<?php

class Auth {
    public static function register($username, $email, $password, $inviteCode, $fullName) {
        // validates invite code
        // checks uniqueness
        // hashes password
        // creates user
        // marks invite used
        // logs activity
    }

    public static function login($usernameOrEmail, $password) {
        // checks rate limit
        // validates credentials
        // creates session
        // updates last_login
        // logs activity
    }

    public static function logout() {
        // destroys session
        // deletes from sessions table
    }

    public static function isLoggedIn() {
        // checks valid session token
    }

    public static function getCurrentUser() {
        // returns user array with role/status
    }

    public static function checkSession($token) {
        // validates session expiry
    }

    public static function createSession($userId, $rememberMe=false) {
        // generates token
        // stores in sessions table with IP/user agent
    }

    public static function updateLastLogin($userId) {
        // increments login_count
    }

    public static function changePassword($userId, $currentPassword, $newPassword) {
        // validates current
        // hashes new
    }

    public static function validateInviteCode($code) {
        // checks active/not expired/under max_uses
    }

    public static function hasPermission($permission) {
        // for role-based access
    }

    public static function requireRole($role) {
        // throws exception
    }

    public static function getUserById($userId) {
        // retrieves user by ID
    }
}