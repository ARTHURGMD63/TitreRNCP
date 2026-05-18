<?php
/**
 * Sécurité — headers HTTP, CSRF, utilitaires.
 * Inclus automatiquement via auth_check.php
 */

// ─── Headers de sécurité ──────────────────────────────────────────────────

function setSecurityHeaders(): void {
    if (headers_sent()) return;

    // Clickjacking
    header('X-Frame-Options: DENY');
    // MIME sniffing
    header('X-Content-Type-Options: nosniff');
    // XSS filter legacy browsers
    header('X-XSS-Protection: 1; mode=block');
    // Referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // HSTS (HTTPS only)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Content Security Policy
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: https:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none';"
    );
}

// ─── CSRF ─────────────────────────────────────────────────────────────────

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Retourne un champ hidden HTML prêt à inclure dans un <form> */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/** Vérifie le token CSRF. Stoppe l'exécution si invalide. */
function csrfVerify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('CSRF token invalide. <a href="javascript:history.back()">Retour</a>');
    }
}

// ─── Rate limiting (anti-bruteforce) ──────────────────────────────────────

/**
 * Limite les tentatives de login par IP.
 * Retourne true si bloqué, false sinon.
 */
function isRateLimited(PDO $pdo, string $ip, int $maxAttempts = 5, int $windowSeconds = 900): bool {
    // Nettoie les anciennes entrées
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)")
        ->execute([$windowSeconds]);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $stmt->execute([$ip, $windowSeconds]);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}

function recordLoginAttempt(PDO $pdo, string $ip, string $email): void {
    $pdo->prepare("INSERT INTO login_attempts (ip, email) VALUES (?,?)")
        ->execute([$ip, $email]);
}

function clearLoginAttempts(PDO $pdo, string $ip): void {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")
        ->execute([$ip]);
}

// ─── Mot de passe oublié ───────────────────────────────────────────────────

function createPasswordResetToken(PDO $pdo, string $email): ?string {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) return null; // Email inconnu — on ne révèle pas

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    // Invalide les anciens tokens
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
        ->execute([$email]);

    $pdo->prepare(
        "INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
    )->execute([$email, $hash]);

    return $token; // Token brut à envoyer par email
}

function validateResetToken(PDO $pdo, string $token): ?string {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT email FROM password_resets WHERE token_hash = ? AND expires_at > NOW() AND used = 0"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? $row['email'] : null;
}

function consumeResetToken(PDO $pdo, string $token, string $newPassword): bool {
    $email = validateResetToken($pdo, $token);
    if (!$email) return false;

    $hash = hash('sha256', $token);
    $pdo->prepare("UPDATE password_resets SET used=1 WHERE token_hash=?")->execute([$hash]);
    $pdo->prepare("UPDATE users SET password=? WHERE email=?")
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $email]);

    return true;
}

/** Envoi de l'email de réinitialisation.
 *  En dev (localhost) affiche le lien dans la réponse.
 *  En prod utilise mail() — configurer SMTP via sendmail_path ou service externe.
 */
function sendResetEmail(string $email, string $token, string $baseUrl): bool {
    $link = $baseUrl . '/auth/reset.php?token=' . urlencode($token);
    $subject = 'StudentLink — Réinitialisation de votre mot de passe';
    $body = "Bonjour,\n\nCliquez sur ce lien pour réinitialiser votre mot de passe (valable 1 heure) :\n\n$link\n\nSi vous n'avez pas fait cette demande, ignorez cet email.\n\nL'équipe StudentLink";
    $headers = "From: noreply@studentlink.app\r\nContent-Type: text/plain; charset=UTF-8";

    // Mode dev : pas d'envoi réel, on stocke le lien en session pour affichage
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    if ($isLocal) {
        $_SESSION['dev_reset_link'] = $link;
        return true;
    }

    return mail($email, $subject, $body, $headers);
}
