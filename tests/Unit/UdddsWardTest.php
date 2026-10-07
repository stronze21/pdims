<?php

use App\Livewire\Pharmacy\Prescriptions\UdddsWard;

uses(Tests\TestCase::class);

function udddsWardWithItems(array $items): UdddsWard
{
    return new class($items) extends UdddsWard {
        public function __construct(private array $fixtureItems) {}

        protected function filteredItems(): array
        {
            return $this->fixtureItems;
        }
    };
}

function udddsWardFixture(string $encounter, ?string $code): object
{
    return (object) [
        'enccode' => $encounter, 'hpercode' => $encounter,
        'patlast' => 'Patient', 'patfirst' => $encounter, 'patmiddle' => '',
        'wardname' => 'Ward A', 'rmname' => 'Room 1',
        'docointkey' => $encounter, 'is_actionable' => false,
        'pcchrgcod' => $code, 'dodate' => '2026-10-07 09:00:00',
    ];
}

test('UDDDS patient range selects numbered patients with existing slips', function () {
    $page = udddsWardWithItems([
        udddsWardFixture('E1', 'P1'), udddsWardFixture('E2', 'P2'), udddsWardFixture('E3', 'P3'),
    ]);
    $page->selected_date = '2026-10-07';
    $page->print_range = '2-3';
    $page->applyPrintRange();

    expect($page->selected_print_patients)->toBe(['E2', 'E3'])
        ->and($page->printSelectionProblem)->toBeNull();
});

test('UDDDS print range rejects a patient without a slip without replacing selection', function () {
    $page = udddsWardWithItems([udddsWardFixture('E1', 'P1'), udddsWardFixture('E2', null)]);
    $page->selected_date = '2026-10-07';
    $page->selected_print_patients = ['E1'];
    $page->print_range = '1-2';
    $page->applyPrintRange();

    expect($page->selected_print_patients)->toBe(['E1'])
        ->and($page->printSelectionProblem)->toContain('Patient #2 has no existing charge slip');
});

test('UDDDS processed view cannot charge or issue orders', function () {
    $page = udddsWardWithItems([]);
    $page->queue_view = 'processed';
    $page->processingProblem = 'Unchanged';
    $page->processSelected(['E1']);

    expect($page->processingProblem)->toBe('Unchanged')
        ->and($page->lastBatchPrintUrl)->toBeNull();
});

test('UDDDS initial page renders queue data without querying hospital orders', function () {
    $user = new \App\Models\User();
    $user->pharm_location_id = 'TEST';
    auth()->setUser($user);
    $page = udddsWardWithItems([]);
    $page->selected_date = '2026-10-07';
    $view = $page->render();

    expect($view->name())->toBe('livewire.pharmacy.prescriptions.uddds-ward')
        ->and($view->getData()['patients'])->toBe([])
        ->and($view->getData()['udddsReady'])->toBeTrue()
        ->and(route('rx.uddds', [], false))->toBe('/rx/uddds');
});

test('UDDDS loaded page checks schema readiness and displays missing-column guidance', function (bool $ready) {
    $schema = Mockery::mock();
    $schema->shouldReceive('hasColumns')->once()->with('hrxo', [
        'order_type', 'is_uddds', 'uddds_start_date', 'uddds_end_date', 'uddds_source_docointkey',
    ])->andReturn($ready);
    $connection = Mockery::mock();
    $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
    \Illuminate\Support\Facades\DB::shouldReceive('connection')->once()->with('hospital')->andReturn($connection);
    $user = new \App\Models\User();
    $user->pharm_location_id = 'TEST';
    auth()->setUser($user);
    $page = udddsWardWithItems([]);
    $page->selected_date = '2026-10-07';
    $page->loadQueue();
    $data = $page->render()->getData();

    expect($data['udddsReady'])->toBe($ready);
    if ($ready) {
        expect($data['udddsMessage'])->toBeNull();
    } else {
        expect($data['udddsMessage'])->toContain('missing required UDDDS columns');
    }
})->with([true, false]);
