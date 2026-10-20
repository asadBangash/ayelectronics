<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Guarantor;
use App\Models\GuarantorDocument;
use App\Models\InstallmentPlan;
use App\Support\PakistanValidators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InstallmentKycController extends BaseController
{
    public function updateClientProfile(Request $request, $clientId)
    {
        $this->authorizeForUser($request->user('api'), 'create', InstallmentPlan::class);

        $client = Client::findOrFail($clientId);

        $request->validate([
            'cnic' => 'nullable|string|max:15',
            'whatsapp' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
        ]);

        $cnic = PakistanValidators::normalizeCnic($request->cnic);
        if ($cnic && ! PakistanValidators::isValidCnic($cnic)) {
            return response()->json(['message' => 'Invalid CNIC format.'], 422);
        }
        if ($cnic) {
            $dup = Client::where('cnic', $cnic)->where('id', '!=', $client->id)->exists();
            if ($dup) {
                return response()->json(['message' => 'CNIC already registered to another customer.'], 422);
            }
        }

        $client->fill($request->only([
            'firstname', 'lastname', 'name', 'email', 'phone', 'whatsapp', 'phone_secondary',
            'adresse', 'city', 'area', 'state', 'country', 'zip', 'occupation', 'tax_number',
        ]));
        $client->cnic = $cnic;
        $client->save();

        return response()->json(['success' => true, 'client' => $client]);
    }

    public function uploadClientFile(Request $request, $clientId)
    {
        $this->authorizeForUser($request->user('api'), 'viewDocuments', InstallmentPlan::class);

        $request->validate([
            'field' => 'required|in:photo_path,cnic_front_path,cnic_back_path,supporting',
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $client = Client::findOrFail($clientId);
        $field = $request->field;
        $path = $request->file('file')->store("kyc/clients/{$clientId}", 'local');

        if ($field === 'supporting') {
            ClientDocument::create([
                'client_id' => $client->id,
                'type' => 'supporting',
                'label' => $request->label,
                'path' => $path,
                'mime' => $request->file('file')->getMimeType(),
                'uploaded_by' => Auth::id(),
            ]);
        } else {
            $client->{$field} = $path;
            $client->save();
        }

        return response()->json(['success' => true, 'path' => $path]);
    }

    public function uploadGuarantorFile(Request $request, $guarantorId)
    {
        $this->authorizeForUser($request->user('api'), 'viewDocuments', InstallmentPlan::class);

        $request->validate([
            'field' => 'required|in:photo_path,cnic_front_path,cnic_back_path,supporting',
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $guarantor = Guarantor::findOrFail($guarantorId);
        $field = $request->field;
        $path = $request->file('file')->store("kyc/guarantors/{$guarantorId}", 'local');

        if ($field === 'supporting') {
            GuarantorDocument::create([
                'guarantor_id' => $guarantor->id,
                'label' => $request->label,
                'path' => $path,
                'mime' => $request->file('file')->getMimeType(),
                'uploaded_by' => Auth::id(),
            ]);
        } else {
            $guarantor->{$field} = $path;
            $guarantor->save();
        }

        return response()->json(['success' => true, 'path' => $path]);
    }
}
