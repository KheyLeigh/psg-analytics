<?php
declare(strict_types=1);
// Vérifie les fabriques fromRow et la logique de nom/résultat des modèles.
final class PlayerModelTest extends TestCase
{
    public function testFullNameEtInitiales(): void
    {
        $p = Player::fromRow([
            'id' => 10, 'season_id' => 1, 'shirt_number' => 10,
            'first_name' => 'Ousmane', 'last_name' => 'Dembélé',
            'position' => 'FW', 'detailed_position' => 'CF', 'foot' => 'both',
            'nationality' => 'France', 'birth_date' => '1997-05-15',
            'height_cm' => 178, 'is_captain' => 0,
        ]);
        $this->assertSame('Ousmane Dembélé', $p->fullName());
        $this->assertSame('OD', $p->initials());
    }

    public function testResultatMatchDuPointDeVuePsg(): void
    {
        // PSG (id 1) joue à l'extérieur, gagne 0-1
        $m = MatchGame::fromRow([
            'id' => 1, 'season_id' => 1, 'competition_id' => 1, 'round_label' => 'J1',
            'played_at' => '2025-08-17', 'home_team_id' => 2, 'away_team_id' => 1,
            'home_goals' => 0, 'away_goals' => 1, 'went_to_extra' => 0,
            'penalty_shootout' => 0, 'penalty_score' => null, 'attendance' => 34053,
            'psg_possession' => 62.0, 'psg_shots' => 14, 'psg_shots_on_target' => 6, 'source_id' => 1,
        ]);
        $this->assertSame('W', $m->result(1));
        $this->assertSame(1, $m->psgGoals(1));
        $this->assertSame(0, $m->opponentGoals(1));
    }
}
