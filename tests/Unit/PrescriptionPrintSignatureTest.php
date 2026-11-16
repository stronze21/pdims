<?php

namespace Tests\Unit;

use Tests\TestCase;

final class PrescriptionPrintSignatureTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aOZkAAAAASUVORK5CYII=';

    public function test_each_print_view_keeps_signatures_with_their_items(): void
    {
        $original = $this->item('Historical medicine', 100, self::PNG, ' 001234 ');
        $unsigned = $this->item('Unsigned medicine');
        $currentPng = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
        $current = $this->item('Recent medicine', 101, $currentPng);
        $another = $this->item('Another recent medicine', 101, $currentPng);

        foreach (['pharmacy.prescriptions.print', 'pharmacy.dispensing.print-prescription'] as $view) {
            $pages = $this->pages($view, [$original, $unsigned, $current, $another]);

            $this->assertCount(3, $pages);
            $this->assertStringContainsString('Historical medicine', $pages[0]);
            $this->assertStringContainsString('Dr. Sample', $pages[0]);
            $this->assertStringContainsString('<span class="line">001234</span><strong>LICENSE NO.</strong>', $pages[0]);
            $this->assertStringContainsString('data:image/png;base64,'.self::PNG, $pages[0]);
            $this->assertStringNotContainsString($currentPng, $pages[0]);
            $this->assertStringContainsString('Unsigned medicine', $pages[1]);
            $this->assertStringNotContainsString('data:image/png', $pages[1]);
            $this->assertStringNotContainsString('Dr. Sample', $pages[1]);
            $this->assertStringContainsString('<span class="line"></span><strong>LICENSE NO.</strong>', $pages[1]);
            $this->assertStringContainsString('Recent medicine', $pages[2]);
            $this->assertStringContainsString('Another recent medicine', $pages[2]);
            $this->assertStringContainsString('data:image/png;base64,'.$currentPng, $pages[2]);
            $this->assertStringNotContainsString(self::PNG, $pages[2]);
            $this->assertStringContainsString('<span class="line"></span><strong>LICENSE NO.</strong>', $pages[2]);
            $this->assertStringContainsString('<ol start="3">', $pages[2]);
        }
    }

    public function test_a_signature_repeats_when_its_items_continue_on_a_second_page(): void
    {
        $items = array_map(fn ($number) => $this->item("Medicine {$number}", 100, self::PNG), range(1, 11));

        $pages = $this->pages('pharmacy.dispensing.print-prescription', $items);

        $this->assertCount(2, $pages);
        $this->assertStringContainsString(self::PNG, $pages[0]);
        $this->assertStringContainsString(self::PNG, $pages[1]);
        $this->assertStringContainsString('<ol start="11">', $pages[1]);
    }

    public function test_training_print_does_not_render_prescriber_metadata(): void
    {
        $item = $this->item('Synthetic medicine', 100, self::PNG, '001234');
        $pages = $this->pages('pharmacy.dispensing.print-prescription', [$item], training: true);

        $this->assertStringContainsString('SYNTHETIC PATIENT RECORD', $pages[0]);
        $this->assertStringNotContainsString('Dr. Sample', $pages[0]);
        $this->assertStringNotContainsString('001234', $pages[0]);
        $this->assertStringNotContainsString('data:image/png', $pages[0]);
    }

    private function pages(string $view, array $items, bool $training = false): array
    {
        $html = view($view, [
            'patient' => null,
            'wardRoom' => 'OPD',
            'items' => collect($items),
            'opdTraining' => $training,
            'prescriptionSource' => 'cdoe',
        ])->render();

        preg_match_all('/<main class="form">.*?<\/main>/s', $html, $matches);

        return $matches[0];
    }

    private function item(string $medicine, ?int $signatureId = null, ?string $png = null, ?string $license = null): object
    {
        return (object) [
            'drug_concat' => $medicine,
            'print_qty' => 1,
            'remaining_qty' => 1,
            'deduct_issued' => true,
            'qty_issued' => 0,
            'qty_per_administration' => null,
            'schedule_text' => '',
            'days_to_cover' => null,
            'addtl_remarks' => null,
            'signature_id' => $signatureId,
            'signature_png' => $png,
            'prescriber_name' => $signatureId === null ? null : 'Dr. Sample',
            'prescriber_license' => $license,
        ];
    }
}
