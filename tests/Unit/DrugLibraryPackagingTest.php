<?php

namespace Tests\Unit;

use App\Livewire\Pharmacy\ManageDrugLibrary;
use ReflectionMethod;
use Tests\TestCase;

class DrugLibraryPackagingTest extends TestCase
{
    public function test_edit_and_brand_copy_accept_records_without_packaging_volume(): void
    {
        $component = new ManageDrugLibrary;
        (new ReflectionMethod($component, 'resetDrugForm'))->invoke($component);
        $record = (object) $component->drugForm;
        unset($record->packvolno, $record->packvolunitcode);

        (new ReflectionMethod($component, 'fillDrugForm'))->invoke($component, $record);
        $this->assertSame('', $component->drugForm['packvolno']);
        $this->assertSame('', $component->drugForm['packvolunitcode']);

        $component->drugForm['packvolno'] = '100';
        $component->drugForm['packvolunitcode'] = 'mL';
        (new ReflectionMethod($component, 'copySharedDrugDefinition'))->invoke($component, $record);
        $this->assertSame('', $component->drugForm['packvolno']);
        $this->assertSame('', $component->drugForm['packvolunitcode']);
    }

    public function test_edit_preserves_existing_packaging_volume(): void
    {
        $component = new ManageDrugLibrary;
        (new ReflectionMethod($component, 'resetDrugForm'))->invoke($component);
        $record = (object) $component->drugForm;
        $record->packvolno = ' 100.00 ';
        $record->packvolunitcode = ' mL ';

        foreach (['fillDrugForm', 'copySharedDrugDefinition'] as $method) {
            (new ReflectionMethod($component, $method))->invoke($component, $record);
            $this->assertSame('100.00', $component->drugForm['packvolno']);
            $this->assertSame('mL', $component->drugForm['packvolunitcode']);
        }
    }
}
