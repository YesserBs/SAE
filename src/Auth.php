<?php
declare(strict_types=1);

// Démarre la session si elle n'est pas encore active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie que l'utilisateur est connecté.
 * Redirige vers login.php sinon.
 */
function requireAuth(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /public/login.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur connecté a le rôle attendu.
 * Redirige vers index.php si le rôle ne correspond pas.
 */
function requireRole(string $role): void
{
    requireAuth();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: /public/index.php');
        exit;
    }
}

/**
 * Retourne true si l'utilisateur est connecté.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Retourne le rôle de l'utilisateur connecté, ou null.
 */
function currentRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Retourne l'id de l'utilisateur connecté, ou null.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}