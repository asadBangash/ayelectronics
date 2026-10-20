<?php

namespace App\Http\Controllers;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\PaymentSale;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstallmentController extends BaseController
{
    public function show(Request $request, int $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);
        $sale = Sale::with(['details.product', 'client'])->findOrFail($saleId);
        $this->abortIfDocumentWarehouseDenied($sale);
        if (! Auth::user()->hasRecordView()) $this->authorizeForUser($request->user('api'), 'check_record', $sale);
        $plan = InstallmentPlan::with(['installments.payments.payment_method', 'installments.payments.user', 'guarantors'])->where('sale_id', $sale->id)->first();
        return response()->json(['sale' => $sale, 'plan' => $plan]);
    }

    public function create(Request $request, int $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'create', Sale::class);
        $sale = Sale::with('client')->findOrFail($saleId);
        $this->abortIfDocumentWarehouseDenied($sale);
        if (! Auth::user()->hasRecordView()) $this->authorizeForUser($request->user('api'), 'check_record', $sale);
        abort_if($sale->client_id !== (int) $request->input('client_id'), 422, 'Sale customer mismatch.');
        abort_if(InstallmentPlan::where('sale_id', $sale->id)->exists(), 409, 'An installment plan already exists.');
        $validated = $request->validate([
            'advance_amount' => 'required|numeric|min:0|lte:'.(float) $sale->GrandTotal,
            'installments' => 'required|array|min:1|max:120',
            'installments.*.due_date' => 'required|date',
            'installments.*.amount' => 'required|numeric|gt:0',
            'guarantors' => 'required|array|size:2',
            'guarantors.*.full_name' => 'required|string|max:255',
            'guarantors.*.father_name' => 'required|string|max:255',
            'guarantors.*.cnic_number' => 'required|string|max:32',
            'guarantors.*.mobile_number' => 'required|string|max:50',
            'guarantors.*.address' => 'required|string',
            'guarantors.*.cnic_front' => 'required|image|max:5120',
            'guarantors.*.cnic_back' => 'required|image|max:5120',
        ]);
        abort_if(!$sale->client->cnic_front_path || !$sale->client->cnic_back_path, 422, 'Customer CNIC images are required for installment sales.');
        abort_if(abs((float) $sale->paid_amount - (float) $validated['advance_amount']) > 0.001, 422, 'The plan advance must match the amount already paid on this sale.');
        $scheduled = round(array_sum(array_column($validated['installments'], 'amount')), 3);
        $expected = round((float) $sale->GrandTotal - (float) $validated['advance_amount'], 3);
        abort_unless(abs($scheduled - $expected) < 0.001, 422, 'Installments must add up to the balance after advance.');

        $plan = DB::transaction(function () use ($request, $sale, $validated, $expected) {
            $plan = InstallmentPlan::create([
                'sale_id' => $sale->id, 'client_id' => $sale->client_id, 'sale_total' => $sale->GrandTotal,
                'advance_amount' => $validated['advance_amount'], 'installment_count' => count($validated['installments']),
            ]);
            foreach ($validated['installments'] as $i => $item) $plan->installments()->create(['number' => $i + 1, 'due_date' => $item['due_date'], 'amount' => $item['amount']]);
            foreach ($validated['guarantors'] as $i => $g) {
                $plan->guarantors()->create([
                    'number' => $i + 1, 'full_name' => $g['full_name'], 'father_name' => $g['father_name'],
                    'cnic_number' => $g['cnic_number'], 'mobile_number' => $g['mobile_number'], 'address' => $g['address'],
                    'cnic_front_path' => $g['cnic_front']->store('private/installments/'.$plan->id, 'local'),
                    'cnic_back_path' => $g['cnic_back']->store('private/installments/'.$plan->id, 'local'),
                ]);
            }
            return $plan->load('installments', 'guarantors');
        });
        return response()->json(['plan' => $plan], 201);
    }

    public function pay(Request $request, int $saleId, int $installmentId)
    {
        $this->authorizeForUser($request->user('api'), 'create', PaymentSale::class);
        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0', 'method' => 'required|in:Cash,Bank Transfer,Easypaisa,JazzCash,Bank Deposit,Online Transfer,Other',
            'transaction_reference' => 'nullable|string|max:255', 'request_key' => 'required|uuid',
        ]);
        $sale = Sale::findOrFail($saleId);
        $this->abortIfDocumentWarehouseDenied($sale);
        if (! Auth::user()->hasRecordView()) $this->authorizeForUser($request->user('api'), 'check_record', $sale);
        $payment = DB::transaction(function () use ($validated, $sale, $installmentId) {
            $installment = Installment::whereHas('plan', fn($q) => $q->where('sale_id', $sale->id))->lockForUpdate()->findOrFail($installmentId);
            if ($existing = PaymentSale::where('installment_request_key', $validated['request_key'])->first()) return $existing;
            $remaining = round((float) $installment->amount - (float) $installment->paid_amount, 3);
            abort_if((float) $validated['amount'] > $remaining + 0.0005, 422, 'Payment exceeds the installment balance.');
            $payment = PaymentSale::create([
                'sale_id' => $sale->id, 'date' => now(), 'montant' => $validated['amount'],
                'Ref' => app(PaymentSalesController::class)->getNumberOrder(), 'user_id' => Auth::id(),
                'installment_id' => $installment->id, 'installment_payment_method' => $validated['method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'installment_request_key' => $validated['request_key'],
            ]);
            $installment->paid_amount = (float) $installment->paid_amount + (float) $validated['amount'];
            $installment->status = $installment->paid_amount >= (float) $installment->amount - 0.0005 ? 'paid' : ($installment->paid_amount > 0 ? 'partial' : 'unpaid');
            $installment->save();
            $sale->paid_amount = min((float) $sale->GrandTotal, (float) $sale->paid_amount + (float) $validated['amount']);
            $sale->payment_statut = $sale->paid_amount >= (float) $sale->GrandTotal ? 'paid' : ($sale->paid_amount > 0 ? 'partial' : 'unpaid');
            $sale->save();
            return $payment;
        });
        return response()->json(['payment' => $payment]);
    }

    public function document(Request $request, string $kind, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);
        abort_unless(in_array($kind, ['customer-photo', 'customer-front', 'customer-back', 'guarantor-front', 'guarantor-back'], true), 404);
        if (str_starts_with($kind, 'customer-')) {
            $client = \App\Models\Client::findOrFail($id);
            $this->authorizeForUser($request->user('api'), 'view', \App\Models\Client::class);
            $path = match ($kind) { 'customer-photo' => $client->photo_path, 'customer-front' => $client->cnic_front_path, default => $client->cnic_back_path };
        } else {
            $guarantor = \App\Models\InstallmentGuarantor::with('plan.sale')->findOrFail($id);
            $sale = $guarantor->plan->sale;
            $this->abortIfDocumentWarehouseDenied($sale);
            if (! Auth::user()->hasRecordView()) $this->authorizeForUser($request->user('api'), 'check_record', $sale);
            $path = str_ends_with($kind, 'front') ? $guarantor->cnic_front_path : $guarantor->cnic_back_path;
        }
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }
}
