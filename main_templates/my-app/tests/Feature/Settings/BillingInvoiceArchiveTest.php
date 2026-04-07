<?php

namespace Tests\Feature\Settings;

use App\Models\BillingInvoiceFile;
use App\Models\User;
use App\Support\BillingInvoiceStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingInvoiceArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_archive_page_can_render_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('electricity-billing.archive'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/BillingInvoices')
                ->where('invoiceArchive.items', [])
                ->where('invoiceArchive.current_period', now()->format('Y-m'))
            );
    }

    public function test_user_can_upload_a_previous_invoice(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invoice-mar-2026.pdf', 120, 'application/pdf');
        $gridFsPath = 'gridfs://65f1a7b8c9d0e1f234567890';

        $this->mock(BillingInvoiceStorage::class, function (MockInterface $mock) use ($gridFsPath): void {
            $mock->shouldReceive('storeUploadedFile')
                ->once()
                ->andReturn($gridFsPath);
        });

        $this->actingAs($user)
            ->post(route('electricity-billing.invoices.store'), [
                'billing_period' => '2026-03',
                'files' => [$file],
            ])
            ->assertRedirect(route('electricity-billing.archive'));

        $invoice = BillingInvoiceFile::query()->firstOrFail();

        $this->assertSame('2026-03', $invoice->billing_period);
        $this->assertSame('invoice-mar-2026.pdf', $invoice->original_name);
        $this->assertSame((string) $user->getAuthIdentifier(), $invoice->owner_key);
        $this->assertSame($gridFsPath, $invoice->storage_path);
    }

    public function test_user_can_preview_a_local_invoice_inline(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $ownerKey = (string) $user->getAuthIdentifier();
        $invoice = BillingInvoiceFile::query()->create([
            'id' => 'inv-preview-local',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2026-03',
            'billing_year' => 2026,
            'billing_month' => 3,
            'original_name' => 'invoice-mar-2026.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 3,
        ]);

        Storage::disk('local')->put($invoice->storage_path, 'pdf');

        $response = $this->actingAs($user)
            ->get(route('electricity-billing.invoices.preview', $invoice->id));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=invoice-mar-2026.pdf');
    }

    public function test_user_can_preview_a_local_invoice_with_non_ascii_name(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $ownerKey = (string) $user->getAuthIdentifier();
        $invoice = BillingInvoiceFile::query()->create([
            'id' => 'inv-preview-unicode',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2026-04',
            'billing_year' => 2026,
            'billing_month' => 4,
            'original_name' => 'factură-energie-aprilie-2026.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2026-04/factura-aprilie-2026.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 3,
        ]);

        Storage::disk('local')->put($invoice->storage_path, 'pdf');

        $response = $this->actingAs($user)
            ->get(route('electricity-billing.invoices.preview', $invoice->id));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_user_can_rename_a_year_folder(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $ownerKey = (string) $user->getAuthIdentifier();

        $invoice = BillingInvoiceFile::query()->create([
            'id' => 'inv-year-rename',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2026-03',
            'billing_year' => 2026,
            'billing_month' => 3,
            'original_name' => 'invoice-mar-2026.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 1024,
        ]);

        Storage::disk('local')->put($invoice->storage_path, 'pdf');

        $this->actingAs($user)
            ->patch(route('electricity-billing.archive.folders.update'), [
                'folder_type' => 'year',
                'folder_key' => '2026',
                'target_year' => '2027',
            ])
            ->assertRedirect(route('electricity-billing.archive'));

        $invoice->refresh();

        $this->assertSame('2027-03', $invoice->billing_period);
        $this->assertSame(2027, $invoice->billing_year);
        $this->assertSame(3, $invoice->billing_month);
        $this->assertSame('billing-invoices/'.$ownerKey.'/2027-03/invoice-mar-2026.pdf', $invoice->storage_path);
        Storage::disk('local')->assertMissing('billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026.pdf');
        Storage::disk('local')->assertExists($invoice->storage_path);
    }

    public function test_user_can_move_a_month_folder(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $ownerKey = (string) $user->getAuthIdentifier();

        $invoiceA = BillingInvoiceFile::query()->create([
            'id' => 'inv-month-move-a',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2026-03',
            'billing_year' => 2026,
            'billing_month' => 3,
            'original_name' => 'invoice-mar-2026-a.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026-a.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 1024,
        ]);
        $invoiceB = BillingInvoiceFile::query()->create([
            'id' => 'inv-month-move-b',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2026-03',
            'billing_year' => 2026,
            'billing_month' => 3,
            'original_name' => 'invoice-mar-2026-b.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026-b.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 2048,
        ]);

        Storage::disk('local')->put($invoiceA->storage_path, 'pdf-a');
        Storage::disk('local')->put($invoiceB->storage_path, 'pdf-b');

        $this->actingAs($user)
            ->patch(route('electricity-billing.archive.folders.update'), [
                'folder_type' => 'period',
                'folder_key' => '2026-03',
                'target_period' => '2026-04',
            ])
            ->assertRedirect(route('electricity-billing.archive'));

        $invoiceA->refresh();
        $invoiceB->refresh();

        $this->assertSame('2026-04', $invoiceA->billing_period);
        $this->assertSame('2026-04', $invoiceB->billing_period);
        $this->assertSame(4, $invoiceA->billing_month);
        $this->assertSame(4, $invoiceB->billing_month);
        Storage::disk('local')->assertMissing('billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026-a.pdf');
        Storage::disk('local')->assertMissing('billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026-b.pdf');
        Storage::disk('local')->assertExists('billing-invoices/'.$ownerKey.'/2026-04/invoice-mar-2026-a.pdf');
        Storage::disk('local')->assertExists('billing-invoices/'.$ownerKey.'/2026-04/invoice-mar-2026-b.pdf');
    }

    public function test_user_can_delete_a_year_folder(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $ownerKey = (string) $user->getAuthIdentifier();

        $targetInvoice = BillingInvoiceFile::query()->create([
            'id' => 'inv-delete-year-target',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2026-03',
            'billing_year' => 2026,
            'billing_month' => 3,
            'original_name' => 'invoice-mar-2026.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2026-03/invoice-mar-2026.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 1024,
        ]);
        $keptInvoice = BillingInvoiceFile::query()->create([
            'id' => 'inv-delete-year-keep',
            'owner_key' => $ownerKey,
            'owner_email' => (string) $user->email,
            'billing_period' => '2025-12',
            'billing_year' => 2025,
            'billing_month' => 12,
            'original_name' => 'invoice-dec-2025.pdf',
            'storage_path' => 'billing-invoices/'.$ownerKey.'/2025-12/invoice-dec-2025.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'size_bytes' => 1024,
        ]);

        Storage::disk('local')->put($targetInvoice->storage_path, 'target');
        Storage::disk('local')->put($keptInvoice->storage_path, 'keep');

        $this->actingAs($user)
            ->delete(route('electricity-billing.archive.folders.destroy'), [
                'folder_type' => 'year',
                'folder_key' => '2026',
            ])
            ->assertRedirect(route('electricity-billing.archive'));

        $this->assertDatabaseMissing('billing_invoice_files', [
            'id' => 'inv-delete-year-target',
        ]);
        $this->assertDatabaseHas('billing_invoice_files', [
            'id' => 'inv-delete-year-keep',
        ]);
        Storage::disk('local')->assertMissing($targetInvoice->storage_path);
        Storage::disk('local')->assertExists($keptInvoice->storage_path);
    }
}
