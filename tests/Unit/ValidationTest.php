<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests des règles de validation utilisées dans les formulaires.
 * Reproduisent la logique métier (whitelists, bornes, formats).
 */
final class ValidationTest extends TestCase
{
    /** Whitelist exacte autorisée dans profil.php */
    private const ALLOWED_INTERESTS = [
        'Sorties', 'Boîtes', 'Running', 'Muscu', 'Vélo', 'Foot', 'Tennis', 'Gaming',
        'Cuisine', 'Voyage', 'Cinéma', 'Lecture', 'Musique', 'Art', 'Photo',
        'Animaux', 'Code', 'Yoga', 'Échecs', 'Mixologie', 'Bénévolat', 'Soirées'
    ];

    public function testInterestsWhitelistRejectsInjection(): void
    {
        $userInput = ['Sorties', '<script>alert(1)</script>', 'Boîtes', 'DROP TABLE users'];
        $kept = array_values(array_intersect($userInput, self::ALLOWED_INTERESTS));
        $this->assertSame(['Sorties', 'Boîtes'], $kept);
    }

    public function testInterestsWhitelistAcceptsAllValid(): void
    {
        $userInput = self::ALLOWED_INTERESTS;
        $kept = array_intersect($userInput, self::ALLOWED_INTERESTS);
        $this->assertCount(count(self::ALLOWED_INTERESTS), $kept);
    }

    public function testInterestsWhitelistEmptyStaysEmpty(): void
    {
        $kept = array_intersect([], self::ALLOWED_INTERESTS);
        $this->assertSame([], $kept);
    }

    /**
     * Borne du quota squad : entre 2 et 500 (api/create_squad.php).
     */
    public function testQuotaIsClampedBetweenTwoAndFiveHundred(): void
    {
        $clamp = fn(int $v): int => max(2, min(500, $v));
        $this->assertSame(2, $clamp(0));
        $this->assertSame(2, $clamp(-50));
        $this->assertSame(2, $clamp(1));
        $this->assertSame(10, $clamp(10));
        $this->assertSame(500, $clamp(500));
        $this->assertSame(500, $clamp(9999));
    }

    /**
     * Note d'un avis : doit être entre 1 et 5 (avis.php).
     */
    public function testNoteValidationOutOfBounds(): void
    {
        $isValid = fn(int $n): bool => $n >= 1 && $n <= 5;
        $this->assertFalse($isValid(0));
        $this->assertFalse($isValid(6));
        $this->assertFalse($isValid(-1));
        $this->assertTrue($isValid(1));
        $this->assertTrue($isValid(3));
        $this->assertTrue($isValid(5));
    }

    public function testCommentaireIsTruncatedTo1000Chars(): void
    {
        $long = str_repeat('a', 5000);
        $truncated = substr(trim($long), 0, 1000);
        $this->assertSame(1000, strlen($truncated));
    }

    public function testHtmlSpecialcharsEscapesXss(): void
    {
        $danger = '<script>alert("xss")</script>';
        $safe = htmlspecialchars($danger, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $safe);
        $this->assertStringContainsString('&lt;script&gt;', $safe);
    }

    public function testEmailValidationRejectsInvalid(): void
    {
        $this->assertFalse((bool)filter_var('not-an-email', FILTER_VALIDATE_EMAIL));
        $this->assertFalse((bool)filter_var('@example.com', FILTER_VALIDATE_EMAIL));
        $this->assertFalse((bool)filter_var('test@', FILTER_VALIDATE_EMAIL));
    }

    public function testEmailValidationAcceptsValid(): void
    {
        $this->assertNotFalse(filter_var('arthur@studentlink.fr', FILTER_VALIDATE_EMAIL));
        $this->assertNotFalse(filter_var('test+tag@uca.fr', FILTER_VALIDATE_EMAIL));
    }

    public function testPasswordHashIsBcryptAndVerifiable(): void
    {
        $password = 'super-secret-123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrong', $hash));
        // BCrypt hashes commencent par $2y$
        $this->assertStringStartsWith('$2y$', $hash);
    }
}
