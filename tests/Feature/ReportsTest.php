<?php

namespace Tests\Feature;

use App\Services\OrderService;
use Carbon\Carbon;
use Tests\Support\ShopTestCase;

class ReportsTest extends ShopTestCase
{
    public function test_xlsx_download_is_real_ooxml_with_typed_totals_and_literal_customer_names(): void
    {
        $o = $this->order(['recipient' => '=SUM(1,2)']);
        app(OrderService::class)->pay($o, 'cash', 50000);
        $response = $this->actingAs($this->user('pemilik'))->get('/pemilik/laporan.xlsx');
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $this->assertNotFalse($zip->getFromName('[Content_Types].xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<v>50000</v>', $sheet);
        $this->assertStringContainsString('=SUM(1,2)', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        $zip->close();
        unlink($file);
    }

    public function test_xlsx_date_filter_excludes_unpaid_and_out_of_period_orders(): void
    {
        $o = $this->order();
        $fileResponse = $this->actingAs($this->user('pemilik'))->get('/pemilik/laporan.xlsx?from=2099-01-01&to=2099-01-02')->assertOk();
        $file = $fileResponse->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($file);
        $this->assertStringNotContainsString($o->code, $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($file);
    }

    public function test_pdf_report_download_is_a_generated_pdf_document(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $r = $this->actingAs($this->user('pemilik'))->get('/pemilik/laporan.pdf');
        $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->assertGreaterThan(1000, strlen($r->getContent()));
    }

    public function test_shipping_document_can_be_downloaded_as_pdf(): void
    {
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $r = $this->actingAs($this->user('admin_gudang'))->get('/gudang/'.$o->id.'/dokumen?format=pdf');
        $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
    }

    public function test_reports_reject_invalid_periods_and_unauthorized_roles(): void
    {
        $this->actingAs($this->user('pemilik'));
        foreach (['xlsx', 'pdf'] as $type) {
            $this->get('/pemilik/laporan.'.$type.'?from=2026-12-01&to=2026-01-01')->assertSessionHasErrors('to');
        }$this->actingAs($this->customer)->get('/pemilik/laporan.xlsx')->assertForbidden();
        $this->get('/pemilik/laporan.pdf')->assertForbidden();
    }

    public function test_dashboard_has_twelve_months_including_zero_and_correct_year_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-01-15 12:00:00'));
        $o = $this->order();
        app(OrderService::class)->pay($o, 'cash', 50000);
        $r = $this->actingAs($this->user('pemilik'))->get('/pemilik');
        $r->assertOk()->assertViewHas('months', fn ($months) => $months->count() === 12 && $months->first()['key'] === '2025-02' && $months->last()['key'] === '2026-01' && $months->last()['online'] == 50000 && $months->first()['online'] == 0);
        $r->assertViewHas('today', 50000)->assertViewHas('thisMonth', 50000);
        $this->travelBack();
    }

    public function test_report_filters_paid_orders_and_exports_real_values(): void
    {
        $o = $this->order();
        $owner = $this->user('pemilik');
        $this->actingAs($owner)->get('/pemilik/laporan.csv')->assertOk()->assertStreamedContent("\xEF\xBB\xBFKode,\"Tanggal Lunas\",Kanal,Status,Subtotal,Ongkir,Total\n");
        app(OrderService::class)->pay($o, 'cash', 50000);
        $r = $this->get('/pemilik/laporan.csv');
        $r->assertOk();
        $this->assertStringContainsString($o->code, $r->streamedContent());
        $this->assertStringContainsString('50000', $r->streamedContent());
        $this->get('/pemilik?from=2099-01-01&to=2099-01-02')->assertOk()->assertDontSee($o->code);
    }
}
