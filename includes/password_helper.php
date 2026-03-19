<?php

function is_legacy_sha256_hash($hash) {
    return is_string($hash) && preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
}

function verify_password_with_legacy($plainPassword, $storedHash, &$needsRehash = false) {
    $needsRehash = false;

    if (!is_string($storedHash) || $storedHash === '') {
        return false;
    }

    if (password_verify($plainPassword, $storedHash)) {
        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            $needsRehash = true;
        }
        return true;
    }

    if (is_legacy_sha256_hash($storedHash) && hash('sha256', $plainPassword) === $storedHash) {
        $needsRehash = true;
        return true;
    }

    return false;
}

function hash_password_secure($plainPassword) {
    return password_hash($plainPassword, PASSWORD_DEFAULT);
}
