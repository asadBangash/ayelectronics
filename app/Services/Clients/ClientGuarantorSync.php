<?php

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\Guarantor;
use App\Models\SaleGuarantor;
use App\Support\PakistanValidators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClientGuarantorSync
{
    public function sync(Client $client, Request $request): void
    {
        if (! Schema::hasTable('client_guarantors')) {
            return;
        }

        $rows = $this->normalizeRows($request);
        if ($rows === null) {
            return;
        }

        $previousIds = $client->guarantorLinks()->pluck('guarantor_id')->all();
        $client->guarantorLinks()->delete();

        $keptGuarantorIds = [];
        $position = 0;

        foreach ($rows as $index => $row) {
            if (trim((string) ($row['full_name'] ?? '')) === '') {
                continue;
            }
            $position++;
            $guarantor = $this->upsertGuarantor($row, $request, $index);

            $client->guarantorLinks()->create([
                'guarantor_id' => $guarantor->id,
                'position' => $position,
                'relationship' => $row['relationship'] ?? null,
            ]);

            $keptGuarantorIds[] = $guarantor->id;
        }

        foreach (array_diff($previousIds, $keptGuarantorIds) as $guarantorId) {
            $this->deleteGuarantorIfOrphaned((int) $guarantorId);
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null null = do not sync (field omitted)
     */
    protected function normalizeRows(Request $request): ?array
    {
        if ($request->boolean('guarantors_cleared')) {
            $rows = [];

            return $rows;
        }

        if (! $request->has('guarantors') && ! $request->hasFile('guarantors')) {
            return null;
        }

        $raw = $request->input('guarantors', []);
        if ($raw === '[]') {
            return [];
        }

        $rows = is_array($raw) ? $raw : [];
        if (! is_array($rows)) {
            return [];
        }

        ksort($rows, SORT_NUMERIC);

        return array_values($rows);
    }

    protected function upsertGuarantor(array $row, Request $request, int $index): Guarantor
    {
        $guarantor = ! empty($row['id'])
            ? Guarantor::find($row['id'])
            : null;

        $cnic = PakistanValidators::normalizeCnic($row['cnic'] ?? null);
        if ($cnic && ! PakistanValidators::isValidCnic($cnic)) {
            $cnic = null;
        }

        $attributes = [
            'full_name' => trim((string) ($row['full_name'] ?? '')),
            'father_name' => $row['father_name'] ?? null,
            'cnic' => $cnic,
            'mobile' => $row['mobile'] ?? $row['contact'] ?? null,
            'whatsapp' => $row['whatsapp'] ?? $row['contact'] ?? null,
            'email' => $row['email'] ?? null,
            'permanent_address' => $row['permanent_address'] ?? $row['address'] ?? null,
            'business_address' => $row['business_address'] ?? null,
            // Legacy single address column used in installment UI.
            'address' => $row['permanent_address'] ?? $row['address'] ?? null,
        ];

        if (! $guarantor) {
            $guarantor = Guarantor::create($attributes);
        } else {
            $guarantor->fill($attributes)->save();
        }

        $this->storeGuarantorFiles($guarantor, $request, $index);

        return $guarantor;
    }

    protected function storeGuarantorFiles(Guarantor $guarantor, Request $request, int $index): void
    {
        $map = [
            'photo' => 'photo_path',
            'cnic_front' => 'cnic_front_path',
            'cnic_back' => 'cnic_back_path',
        ];

        foreach ($map as $inputKey => $column) {
            $file = $request->file("guarantors.{$index}.{$inputKey}");
            if (! $file) {
                continue;
            }
            if ($guarantor->{$column}) {
                Storage::disk('local')->delete($guarantor->{$column});
            }
            $guarantor->{$column} = $file->store("private/guarantors/{$guarantor->id}", 'local');
        }

        $guarantor->save();
    }

    protected function deleteGuarantorIfOrphaned(int $guarantorId): void
    {
        $stillLinkedToClient = \App\Models\ClientGuarantor::where('guarantor_id', $guarantorId)->exists();
        $stillOnSale = SaleGuarantor::where('guarantor_id', $guarantorId)->exists();
        if ($stillLinkedToClient || $stillOnSale) {
            return;
        }

        $guarantor = Guarantor::find($guarantorId);
        if (! $guarantor) {
            return;
        }

        foreach (['photo_path', 'cnic_front_path', 'cnic_back_path'] as $col) {
            if ($guarantor->{$col}) {
                Storage::disk('local')->delete($guarantor->{$col});
            }
        }

        $guarantor->delete();
    }
}
