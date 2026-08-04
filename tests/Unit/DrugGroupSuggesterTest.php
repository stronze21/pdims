<?php

use App\Services\Pharmacy\DrugImport\DrugGroupSuggester;

it('ranks exact, spelling, and sounds-like generic names', function () {
    $suggester = new DrugGroupSuggester;

    expect($suggester->score('Acetylcysteine', 'Acetylcysteine'))->toBe(100.0)
        ->and($suggester->score('Acetylcystine', 'Acetylcysteine'))->toBeGreaterThan(70)
        ->and($suggester->score('Epinephrine (Adrenaline)', 'Epinephrine'))->toBeGreaterThanOrEqual(88)
        ->and($suggester->score('ANTIINFECTIVES FOR SYSTEMIC USE', 'ANTI-INFECTIVES'))->toBe(100.0)
        ->and($suggester->score('ANTIVIRALS FOR SYSTEMIC USE', 'Antivirals'))->toBe(100.0)
        ->and($suggester->score('Acetylcysteine', 'Paracetamol'))->toBeLessThan(35);
});
