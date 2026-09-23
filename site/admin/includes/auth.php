<?php

function admin_require_login(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }
}

function admin_attempt_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    return false;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_username']);
}
