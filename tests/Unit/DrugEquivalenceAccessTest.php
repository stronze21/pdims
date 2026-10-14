<?php

namespace Tests\Unit;

use App\Livewire\Pharmacy\ManageDrugLibrary;
use App\Models\User;
use App\Services\Pharmacy\DrugEquivalenceService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DrugEquivalenceAccessTest extends TestCase
{
    private function signInWith(array $permissions): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->name = 'Permission Test';
        $user->shouldReceive('can')->andReturnUsing(fn ($ability) => in_array($ability, $permissions, true));
        $user->shouldReceive('canAny')->andReturnUsing(fn ($abilities) => (bool) array_intersect($abilities, $permissions));
        $this->actingAs($user);
    }

    public function test_equivalence_permissions_open_only_equivalence_surface(): void
    {
        foreach (['view-equivalent-groups', 'manage-equivalent-groups'] as $permission) {
            $this->signInWith([$permission]);
            $component = new ManageDrugLibrary;
            $component->mount();
            $this->assertSame('equivalents', $component->tab);
            $component->tab = 'drugs';
            try {
                $component->updatedTab();
                $this->fail('Unrelated library tab should be forbidden.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
    }

    public function test_viewers_cannot_call_any_equivalence_mutation(): void
    {
        $this->signInWith(['view-equivalent-groups']);
        $component = new ManageDrugLibrary;
        foreach ([
            'createEquivalenceGroup' => ['001', '1'],
            'saveReviewedEquivalenceGroup' => [],
            'dissolveEquivalenceGroup' => [1],
            'addEquivalenceMember' => ['001', '1'],
            'removeEquivalenceMember' => ['001', '1'],
            'setEquivalenceCanonical' => ['001', '1'],
            'toggleEquivalenceStatus' => [1],
        ] as $method => $args) {
            try {
                $component->$method(...$args);
                $this->fail($method.' should be forbidden.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode(), $method);
            }
        }
    }

    public function test_equivalence_manager_cannot_modify_other_library_records(): void
    {
        $this->signInWith(['manage-equivalent-groups']);
        $component = new ManageDrugLibrary;
        foreach ([
            'createDrug' => [], 'editDrug' => ['001', '1'],
            'createBrandVariant' => ['001', '1'], 'saveDrug' => [],
            'toggleDrugStatus' => ['001', '1', 'A'],
            'createLookup' => ['generics'], 'editLookup' => ['generics', '001'],
            'saveLookup' => [], 'toggleLookupStatus' => ['generics', '001', 'A'],
            'createGroup' => [], 'editGroup' => ['001'], 'saveGroup' => [],
            'toggleGroupStatus' => ['001', 'A'], 'editSub' => ['001', '1', '1'],
            'createSub' => ['001', '1'], 'saveSub' => [],
            'toggleSubStatus' => ['001', '1', '1', 'A'],
        ] as $method => $args) {
            try {
                $component->$method(...$args);
                $this->fail($method.' should be forbidden.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode(), $method);
            }
        }
    }

    public function test_viewer_can_open_group_members(): void
    {
        $this->signInWith(['view-equivalent-groups']);
        DB::shouldReceive('connection->table->where->exists')->once()->andReturnTrue();
        $component = new ManageDrugLibrary;
        $component->editEquivalenceGroup(7);
        $this->assertTrue($component->equivalenceModal);
        $this->assertSame(7, $component->editingEquivalenceGroupId);
    }

    public function test_manager_can_change_group_status(): void
    {
        $this->signInWith(['manage-equivalent-groups']);
        $service = \Mockery::mock(DrugEquivalenceService::class);
        $service->shouldReceive('toggle')->once()->with(7, \Mockery::any());
        $this->app->instance(DrugEquivalenceService::class, $service);
        (new ManageDrugLibrary)->toggleEquivalenceStatus(7);
        $this->assertSame('Equivalent group status updated.', session('success'));
    }

    public function test_settings_permission_alone_does_not_grant_equivalence_access(): void
    {
        $this->signInWith(['view-settings']);
        $component = new ManageDrugLibrary;
        $component->tab = 'equivalents';
        $this->expectException(HttpException::class);
        $component->updatedTab();
    }
}
