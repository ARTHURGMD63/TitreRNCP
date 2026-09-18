<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Expiration des sessions par inactivité.
 *
 * Le back-office ouvre le fichier clients, les coordonnées des étudiants et
 * la trésorerie : une session de fondateur oubliée sur un écran doit se
 * fermer seule, et bien plus vite que celle d'un étudiant.
 */
final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('delaiInactivite')) {
            require_once __DIR__ . '/../../includes/auth_check.php';
        }
        $_SESSION = [];
    }

    public function testLeBackOfficeExpireEnUneHeure(): void
    {
        $this->assertSame(3600, delaiInactivite('admin'));
    }

    public function testLesAutresComptesTiennentTrenteJours(): void
    {
        // Une reconnexion trop fréquente côté étudiant ferait fuir l'usage,
        // et un compte étudiant ne donne accès qu'à ses propres sorties.
        $this->assertSame(30 * 24 * 3600, delaiInactivite('etudiant'));
        $this->assertSame(30 * 24 * 3600, delaiInactivite('partenaire'));
        $this->assertSame(30 * 24 * 3600, delaiInactivite(null));
    }

    public function testUneSessionAdminRecenteEstConservee(): void
    {
        $_SESSION = ['user_id' => 1, 'user_type' => 'admin', 'derniere_activite' => time() - 300];
        expirerSessionInactive();
        $this->assertSame(1, $_SESSION['user_id']);
    }

    public function testChaquePassageRepousseLEcheance(): void
    {
        $_SESSION = ['user_id' => 1, 'user_type' => 'admin', 'derniere_activite' => time() - 300];
        expirerSessionInactive();
        $this->assertEqualsWithDelta(time(), $_SESSION['derniere_activite'], 2);
    }

    public function testUneSessionAdminInactiveDepuisDeuxHeuresTombe(): void
    {
        $_SESSION = ['user_id' => 1, 'user_type' => 'admin', 'derniere_activite' => time() - 7200];
        expirerSessionInactive();
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertTrue($_SESSION['session_expiree']);
    }

    public function testUnEtudiantInactifDeuxHeuresResteConnecte(): void
    {
        $_SESSION = ['user_id' => 2, 'user_type' => 'etudiant', 'derniere_activite' => time() - 7200];
        expirerSessionInactive();
        $this->assertSame(2, $_SESSION['user_id']);
    }

    public function testUnEtudiantInactifQuaranteJoursTombe(): void
    {
        $_SESSION = ['user_id' => 2, 'user_type' => 'etudiant', 'derniere_activite' => time() - 40 * 86400];
        expirerSessionInactive();
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testUneSessionAnonymeNEstPasTouchee(): void
    {
        // Sur l'écran de connexion il n'y a pas de session à expirer, et
        // poser le drapeau y afficherait un message sans raison.
        $_SESSION = ['csrf_token' => 'abc'];
        expirerSessionInactive();
        $this->assertSame(['csrf_token' => 'abc'], $_SESSION);
    }

    // ─── Politique de mot de passe ───────────────────────────────────

    public function testUnCompteFondateurExigeUnMotDePassePlusLong(): void
    {
        // Trois planchers coexistaient sans s'accorder (6 / 8 / 10) : un
        // fondateur pouvait redescendre sous sa propre règle en passant par
        // « mot de passe oublié ». Une seule source désormais.
        $this->assertSame(12, longueurMinimaleMotDePasse('admin'));
    }

    public function testLesAutresComptesGardentLePlancherCommun(): void
    {
        $this->assertSame(8, longueurMinimaleMotDePasse('etudiant'));
        $this->assertSame(8, longueurMinimaleMotDePasse('partenaire'));
        $this->assertSame(8, longueurMinimaleMotDePasse(null));
    }

    public function testUnePremiereVisiteNExpirePasImmediatement(): void
    {
        // Sans `derniere_activite` — session ouverte avant cette mesure —
        // on repart de maintenant plutôt que de déconnecter tout le monde
        // au déploiement.
        $_SESSION = ['user_id' => 1, 'user_type' => 'admin'];
        expirerSessionInactive();
        $this->assertSame(1, $_SESSION['user_id']);
        $this->assertArrayHasKey('derniere_activite', $_SESSION);
    }
}
