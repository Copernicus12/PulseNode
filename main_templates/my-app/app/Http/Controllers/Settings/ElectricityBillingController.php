<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BillingInvoiceFolderDestroyRequest;
use App\Http\Requests\Settings\BillingInvoiceFolderStoreRequest;
use App\Http\Requests\Settings\BillingInvoiceFolderUpdateRequest;
use App\Http\Requests\Settings\BillingInvoiceUploadRequest;
use App\Http\Requests\Settings\ElectricityBillingProfileStoreRequest;
use App\Http\Requests\Settings\ElectricityBillingUpdateRequest;
use App\Models\BillingInvoiceFile;
use App\Models\BillingInvoiceFolder;
use App\Models\BillingTariffProfile;
use App\Support\BillingInvoiceStorage;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ElectricityBillingController extends Controller
{
    public function __construct(
        private readonly BillingInvoiceStorage $invoiceStorage,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $pricePerWh = (float) ($user->electricity_price_per_wh ?? 0);
        $pricePerKwh = $pricePerWh * 1000;

        return Inertia::render('settings/ElectricityBilling', [
            'billingSettings' => [
                'electricity_price_per_kwh' => rtrim(rtrim(number_format($pricePerKwh, 6, '.', ''), '0'), '.'),
                'billing_currency' => $user->billing_currency ?? 'RON',
                'billing_tax_percent' => rtrim(rtrim(number_format((float) ($user->billing_tax_percent ?? 21), 2, '.', ''), '0'), '.'),
            ],
            'billingProfiles' => BillingTariffProfile::query()
                ->where('owner_key', $this->ownerKey($user))
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (BillingTariffProfile $profile) => [
                    'id' => (string) $profile->id,
                    'name' => (string) $profile->name,
                    'electricity_price_per_kwh' => rtrim(rtrim(number_format((float) $profile->electricity_price_per_kwh, 6, '.', ''), '0'), '.'),
                    'billing_currency' => (string) $profile->billing_currency,
                    'billing_tax_percent' => rtrim(rtrim(number_format((float) $profile->billing_tax_percent, 2, '.', ''), '0'), '.'),
                    'created_at' => optional($profile->created_at)?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function archive(Request $request): Response
    {
        return Inertia::render('settings/BillingInvoices', [
            'invoiceArchive' => [
                'items' => $this->invoiceItems($request->user()),
                'folders' => $this->invoiceFolders($request->user()),
                'current_period' => now()->format('Y-m'),
                'accepted_types' => ['PDF', 'JPG', 'PNG', 'WEBP'],
            ],
        ]);
    }

    public function update(ElectricityBillingUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'electricity_price_per_wh' => ((float) $request->validated('electricity_price_per_kwh')) / 1000,
            'billing_currency' => strtoupper($request->validated('billing_currency')),
            'billing_tax_percent' => $request->validated('billing_tax_percent'),
        ])->save();

        return to_route('electricity-billing.edit');
    }

    public function storeProfile(ElectricityBillingProfileStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        BillingTariffProfile::query()->create([
            'id' => (string) Str::ulid(),
            'owner_key' => $this->ownerKey($user),
            'owner_email' => (string) ($user?->email ?? ''),
            'name' => $data['name'],
            'electricity_price_per_kwh' => (float) $data['electricity_price_per_kwh'],
            'billing_currency' => strtoupper($data['billing_currency']),
            'billing_tax_percent' => (float) $data['billing_tax_percent'],
        ]);

        return to_route('electricity-billing.edit');
    }

    public function destroyProfile(Request $request, string $profileId): RedirectResponse
    {
        BillingTariffProfile::query()
            ->where('owner_key', $this->ownerKey($request->user()))
            ->where('id', $profileId)
            ->delete();

        return to_route('electricity-billing.edit');
    }

    public function storeInvoice(BillingInvoiceUploadRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $billingPeriod = $validated['billing_period'];
        $billingYear = (int) substr($billingPeriod, 0, 4);
        $billingMonth = (int) substr($billingPeriod, 5, 2);
        $ownerKey = $this->ownerKey($user);

        foreach ($request->file('files', []) as $uploadedFile) {
            $originalName = (string) $uploadedFile->getClientOriginalName();
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension() ?: '');
            $safeBaseName = Str::slug($baseName);
            $storedName = (string) Str::ulid();

            if ($safeBaseName !== '') {
                $storedName .= '-'.$safeBaseName;
            }

            if ($extension !== '') {
                $storedName .= '.'.$extension;
            }

            $storagePath = $this->invoiceStorage->storeUploadedFile(
                $uploadedFile,
                $storedName,
                [
                    'owner_key' => $ownerKey,
                    'billing_period' => $billingPeriod,
                    'original_name' => $originalName,
                    'mime_type' => (string) ($uploadedFile->getClientMimeType() ?? 'application/octet-stream'),
                ],
            );

            BillingInvoiceFile::query()->create([
                'id' => (string) Str::ulid(),
                'owner_key' => $ownerKey,
                'owner_email' => (string) ($user?->email ?? ''),
                'billing_period' => $billingPeriod,
                'billing_year' => $billingYear,
                'billing_month' => $billingMonth,
                'original_name' => $originalName,
                'storage_path' => $storagePath,
                'mime_type' => (string) ($uploadedFile->getClientMimeType() ?? 'application/octet-stream'),
                'file_extension' => $extension,
                'size_bytes' => (int) $uploadedFile->getSize(),
            ]);
        }

        return to_route('electricity-billing.archive');
    }

    public function storeInvoiceFolder(BillingInvoiceFolderStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $folderKey = (string) $validated['folder_key'];

        BillingInvoiceFolder::query()->create([
            'id' => (string) Str::ulid(),
            'owner_key' => $this->ownerKey($request->user()),
            'owner_email' => (string) ($request->user()?->email ?? ''),
            'folder_type' => $validated['folder_type'],
            'folder_key' => $folderKey,
            'folder_year' => (int) substr($folderKey, 0, 4),
            'folder_month' => $validated['folder_type'] === 'period'
                ? (int) substr($folderKey, 5, 2)
                : null,
        ]);

        return to_route('electricity-billing.archive');
    }

    public function downloadInvoice(Request $request, string $invoiceId): StreamedResponse
    {
        $invoice = $this->invoiceQuery($request->user())
            ->where('id', $invoiceId)
            ->firstOrFail();

        return $this->streamInvoiceResponse($invoice, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    public function previewInvoice(Request $request, string $invoiceId): StreamedResponse
    {
        $invoice = $this->invoiceQuery($request->user())
            ->where('id', $invoiceId)
            ->firstOrFail();

        return $this->streamInvoiceResponse($invoice, HeaderUtils::DISPOSITION_INLINE);
    }

    public function destroyInvoice(Request $request, string $invoiceId): RedirectResponse
    {
        $invoice = $this->invoiceQuery($request->user())
            ->where('id', $invoiceId)
            ->firstOrFail();

        $this->deleteInvoiceBinary($invoice);
        $invoice->delete();

        return to_route('electricity-billing.archive');
    }

    public function updateInvoiceFolder(BillingInvoiceFolderUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $ownerKey = $this->ownerKey($user);
        $folderType = $validated['folder_type'];
        $folderKey = $validated['folder_key'];

        if ($folderType === 'year') {
            $targetYear = (string) ($validated['target_year'] ?? '');

            if ($targetYear === $folderKey) {
                return to_route('electricity-billing.archive');
            }

            $this->invoiceQuery($user)
                ->where('billing_year', (int) $folderKey)
                ->get()
                ->each(function (BillingInvoiceFile $invoice) use ($targetYear, $ownerKey): void {
                    $this->moveInvoiceToPeriod(
                        $invoice,
                        sprintf('%s-%02d', $targetYear, $invoice->billing_month),
                        $ownerKey,
                    );
                });

            $this->moveYearFolderRecords($user, $folderKey, $targetYear);

            return to_route('electricity-billing.archive');
        }

        $targetPeriod = (string) ($validated['target_period'] ?? '');

        if ($targetPeriod === $folderKey) {
            return to_route('electricity-billing.archive');
        }

        $this->invoiceQuery($user)
            ->where('billing_period', $folderKey)
            ->get()
            ->each(function (BillingInvoiceFile $invoice) use ($targetPeriod, $ownerKey): void {
                $this->moveInvoiceToPeriod($invoice, $targetPeriod, $ownerKey);
            });

        $this->movePeriodFolderRecord($user, $folderKey, $targetPeriod);

        return to_route('electricity-billing.archive');
    }

    public function destroyInvoiceFolder(BillingInvoiceFolderDestroyRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $folderType = $validated['folder_type'];
        $folderKey = $validated['folder_key'];
        $query = $this->invoiceQuery($user);

        if ($folderType === 'year') {
            $query->where('billing_year', (int) $folderKey);
            $this->deleteYearFolderRecords($user, $folderKey);
        } else {
            $query->where('billing_period', $folderKey);
            $this->deletePeriodFolderRecord($user, $folderKey);
        }

        $query->get()->each(function (BillingInvoiceFile $invoice): void {
            $this->deleteInvoiceBinary($invoice);
            $invoice->delete();
        });

        return to_route('electricity-billing.archive');
    }

    private function ownerKey(?Authenticatable $user): string
    {
        return (string) ($user?->getAuthIdentifier() ?? '');
    }

    private function invoiceItems(?Authenticatable $user): array
    {
        return $this->invoiceQuery($user)
            ->orderByDesc('billing_period')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BillingInvoiceFile $invoice) => [
                'id' => (string) $invoice->id,
                'name' => (string) $invoice->original_name,
                'period' => (string) $invoice->billing_period,
                'year' => (int) $invoice->billing_year,
                'month' => (int) $invoice->billing_month,
                'size_bytes' => (int) $invoice->size_bytes,
                'mime_type' => (string) $invoice->mime_type,
                'extension' => (string) ($invoice->file_extension ?? ''),
                'uploaded_at' => optional($invoice->created_at)?->toISOString(),
                'preview_url' => route('electricity-billing.invoices.preview', $invoice->id),
                'download_url' => route('electricity-billing.invoices.download', $invoice->id),
                'delete_url' => route('electricity-billing.invoices.destroy', $invoice->id),
            ])
            ->values()
            ->all();
    }

    private function invoiceFolders(?Authenticatable $user): array
    {
        return BillingInvoiceFolder::query()
            ->where('owner_key', $this->ownerKey($user))
            ->orderBy('folder_year')
            ->orderBy('folder_month')
            ->get()
            ->map(fn (BillingInvoiceFolder $folder) => [
                'id' => (string) $folder->id,
                'type' => (string) $folder->folder_type,
                'key' => (string) $folder->folder_key,
                'year' => (int) $folder->folder_year,
                'month' => $folder->folder_month === null ? null : (int) $folder->folder_month,
            ])
            ->values()
            ->all();
    }

    private function invoiceQuery(?Authenticatable $user)
    {
        return BillingInvoiceFile::query()
            ->where('owner_key', $this->ownerKey($user));
    }

    private function moveInvoiceToPeriod(BillingInvoiceFile $invoice, string $targetPeriod, string $ownerKey): void
    {
        $targetPath = $invoice->storage_path;

        if (! $this->invoiceStorage->isGridFsPath($invoice->storage_path)) {
            $targetPath = $this->targetStoragePath($invoice, $ownerKey, $targetPeriod);

            if (
                $invoice->storage_path !== $targetPath &&
                Storage::disk('local')->exists($invoice->storage_path)
            ) {
                Storage::disk('local')->makeDirectory(dirname($targetPath));
                Storage::disk('local')->move($invoice->storage_path, $targetPath);
            }
        }

        $invoice->forceFill([
            'billing_period' => $targetPeriod,
            'billing_year' => (int) substr($targetPeriod, 0, 4),
            'billing_month' => (int) substr($targetPeriod, 5, 2),
            'storage_path' => $targetPath,
        ])->save();
    }

    private function targetStoragePath(BillingInvoiceFile $invoice, string $ownerKey, string $targetPeriod): string
    {
        return 'billing-invoices/'.$ownerKey.'/'.$targetPeriod.'/'.basename($invoice->storage_path);
    }

    private function deleteInvoiceBinary(BillingInvoiceFile $invoice): void
    {
        if ($this->invoiceStorage->isGridFsPath($invoice->storage_path)) {
            $this->invoiceStorage->delete($invoice->storage_path);

            return;
        }

        Storage::disk('local')->delete($invoice->storage_path);
    }

    private function openInvoiceStream(BillingInvoiceFile $invoice)
    {
        if ($this->invoiceStorage->isGridFsPath($invoice->storage_path)) {
            return $this->invoiceStorage->openDownloadStream($invoice->storage_path);
        }

        abort_unless(Storage::disk('local')->exists($invoice->storage_path), 404);

        $stream = Storage::disk('local')->readStream($invoice->storage_path);

        abort_if($stream === false, 404);

        return $stream;
    }

    private function streamInvoiceResponse(BillingInvoiceFile $invoice, string $disposition): StreamedResponse
    {
        $stream = $this->openInvoiceStream($invoice);
        $safeName = $invoice->original_name !== ''
            ? $invoice->original_name
            : 'invoice';
        $asciiFallbackName = Str::ascii($safeName);
        $asciiFallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $asciiFallbackName ?? '') ?: 'invoice';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => (string) ($invoice->mime_type ?: 'application/octet-stream'),
            'Content-Length' => (string) $invoice->size_bytes,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $safeName, $asciiFallbackName),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function folderQuery(?Authenticatable $user)
    {
        return BillingInvoiceFolder::query()
            ->where('owner_key', $this->ownerKey($user));
    }

    private function moveYearFolderRecords(?Authenticatable $user, string $sourceYear, string $targetYear): void
    {
        $this->folderQuery($user)
            ->where('folder_type', 'year')
            ->where('folder_key', $sourceYear)
            ->get()
            ->each(function (BillingInvoiceFolder $folder) use ($targetYear): void {
                $this->moveFolderRecordToKey($folder, 'year', $targetYear);
            });

        $this->folderQuery($user)
            ->where('folder_type', 'period')
            ->where('folder_key', 'like', $sourceYear.'-%')
            ->get()
            ->each(function (BillingInvoiceFolder $folder) use ($targetYear): void {
                $targetPeriod = sprintf('%s-%02d', $targetYear, (int) $folder->folder_month);
                $this->moveFolderRecordToKey($folder, 'period', $targetPeriod);
            });
    }

    private function movePeriodFolderRecord(?Authenticatable $user, string $sourcePeriod, string $targetPeriod): void
    {
        $this->folderQuery($user)
            ->where('folder_type', 'period')
            ->where('folder_key', $sourcePeriod)
            ->get()
            ->each(function (BillingInvoiceFolder $folder) use ($targetPeriod): void {
                $this->moveFolderRecordToKey($folder, 'period', $targetPeriod);
            });
    }

    private function moveFolderRecordToKey(BillingInvoiceFolder $folder, string $targetType, string $targetKey): void
    {
        $duplicate = BillingInvoiceFolder::query()
            ->where('owner_key', $folder->owner_key)
            ->where('folder_type', $targetType)
            ->where('folder_key', $targetKey)
            ->where('id', '!=', $folder->id)
            ->first();

        if ($duplicate) {
            $folder->delete();

            return;
        }

        $folder->forceFill([
            'folder_type' => $targetType,
            'folder_key' => $targetKey,
            'folder_year' => (int) substr($targetKey, 0, 4),
            'folder_month' => $targetType === 'period'
                ? (int) substr($targetKey, 5, 2)
                : null,
        ])->save();
    }

    private function deleteYearFolderRecords(?Authenticatable $user, string $year): void
    {
        $this->folderQuery($user)
            ->where(function ($query) use ($year): void {
                $query->where(function ($yearQuery) use ($year): void {
                    $yearQuery
                        ->where('folder_type', 'year')
                        ->where('folder_key', $year);
                })->orWhere(function ($periodQuery) use ($year): void {
                    $periodQuery
                        ->where('folder_type', 'period')
                        ->where('folder_key', 'like', $year.'-%');
                });
            })
            ->delete();
    }

    private function deletePeriodFolderRecord(?Authenticatable $user, string $period): void
    {
        $this->folderQuery($user)
            ->where('folder_type', 'period')
            ->where('folder_key', $period)
            ->delete();
    }
}
