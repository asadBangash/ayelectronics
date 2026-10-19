<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Guarantor;
use App\Models\InstallmentAuditLog;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\InstallmentSetting;
use App\Models\InstallmentWhatsappMessage;
use App\Models\PaymentSale;
use App\Models\Sale;
use App\Services\Installments\CustomerOverdueRatingService;
use App\Services\Installments\InstallmentPlanService;
use App\Services\Installments\InstallmentWhatsAppService;
use App\Support\PakistanValidators;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstallmentController extends BaseController
{
    public function dashboard(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', InstallmentPlan::class);

        $today = Carbon::today();
        $week = $today->copy()->addDays(7);

        $plans = InstallmentPlan::query()->where('status', 'active');

        return response()->json([
            'total_installment_sales' => InstallmentPlan::count(),
            'total_down_payments' => (float) InstallmentPlan::sum('down_payment'),
            'total_collections' => (float) InstallmentPlan::sum('total_paid'),
            'total_outstanding' => (float) InstallmentPlan::where('status', 'active')->sum('outstanding_balance'),
            'due_today' => (float) InstallmentScheduleItem::whereDate('due_date', $today)->where('remaining_amount', '>', 0)->sum('remaining_amount'),
            'due_next_7_days' => (float) InstallmentScheduleItem::whereBetween('due_date', [$today, $week])->where('remaining_amount', '>', 0)->sum('remaining_amount'),
            'overdue_installments' => (float) InstallmentScheduleItem::where('remaining_amount', '>', 0)->whereDate('due_date', '<', $today)->count(),
            'customers_rating' => [
                'green' => Client::where('overdue_rating', 'green')->count(),
                'blue' => Client::where('overdue_rating', 'blue')->count(),
                'orange' => Client::where('overdue_rating', 'orange')->count(),
                'red' => Client::where('overdue_rating', 'red')->count(),
                'dark_red' => Client::where('overdue_rating', 'dark_red')->count(),
            ],
            'whatsapp_sent' => InstallmentWhatsappMessage::whereIn('status', ['accepted', 'delivered', 'test_logged'])->count(),
        ]);
    }

    public function settings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'manageSettings', InstallmentPlan::class);

        return response()->json(['settings' => InstallmentSetting::current()]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'manageSettings', InstallmentPlan::class);

        $settings = InstallmentSetting::current();
        $settings->fill($request->only([
            'require_guarantors_for_installment', 'guarantors_required', 'due_day_of_month',
            'default_markup_percent', 'rating_thresholds',
            'whatsapp_enabled', 'whatsapp_test_mode', 'whatsapp_api_url',
            'whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_webhook_verify_token',
            'whatsapp_reminder_rules',
        ]));
        if ($request->filled('whatsapp_access_token')) {
            $settings->whatsapp_access_token = $request->whatsapp_access_token;
        }
        $settings->save();

        return response()->json(['success' => true, 'settings' => $settings]);
    }

    public function saleDetails(Request $request, $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'view', InstallmentPlan::class);
        $this->authorizeForUser($request->user('api'), 'view', Sale::class);

        $sale = Sale::with([
            'client.documents',
            'details.product',
            'facture.payment_method',
            'installmentPlan.scheduleItems.allocations',
            'saleGuarantors.guarantor.documents',
        ])->findOrFail($saleId);

        $client = $sale->client;
        $ratingService = app(CustomerOverdueRatingService::class);

        $payments = PaymentSale::where('sale_id', $sale->id)->whereNull('deleted_at')->orderByDesc('date')->get();

        $whatsappHistory = InstallmentWhatsappMessage::where('sale_id', $sale->id)->orderByDesc('id')->limit(50)->get();
        $audit = InstallmentAuditLog::where('auditable_type', 'installment_plan')
            ->whereIn('auditable_id', $sale->installmentPlan ? [$sale->installmentPlan->id] : [])
            ->orderByDesc('id')->limit(30)->get();

        return response()->json([
            'sale' => $sale,
            'client' => $client,
            'client_rating' => [
                'level' => $client->overdue_rating ?? 'green',
                'label' => $ratingService->labelForLevel($client->overdue_rating ?? 'green'),
            ],
            'installment_plan' => $sale->installmentPlan,
            'schedule' => $sale->installmentPlan?->scheduleItems ?? [],
            'guarantors' => $sale->saleGuarantors,
            'payments' => $payments,
            'whatsapp_history' => $whatsappHistory,
            'audit_logs' => $audit,
        ]);
    }

    public function createPlan(Request $request, $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'create', InstallmentPlan::class);

        $sale = Sale::findOrFail($saleId);
        $plan = app(InstallmentPlanService::class)->createForSale($sale, $request->all());

        return response()->json(['success' => true, 'plan' => $plan], 201);
    }

    public function guarantorIndex(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', InstallmentPlan::class);

        $q = Guarantor::query()->whereNull('deleted_at');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($query) use ($s) {
                $query->where('full_name', 'like', "%{$s}%")
                    ->orWhere('cnic', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        return response()->json(['guarantors' => $q->orderBy('full_name')->limit(50)->get()]);
    }

    public function guarantorStore(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', InstallmentPlan::class);

        $request->validate([
            'full_name' => 'required|string|max:191',
            'cnic' => 'nullable|string|max:15',
        ]);

        $cnic = PakistanValidators::normalizeCnic($request->cnic);
        if ($cnic && ! PakistanValidators::isValidCnic($cnic)) {
            return response()->json(['message' => 'Invalid CNIC format.'], 422);
        }

        $guarantor = Guarantor::create([
            'full_name' => $request->full_name,
            'cnic' => $cnic,
            'mobile' => $request->mobile,
            'whatsapp' => $request->whatsapp,
            'phone_secondary' => $request->phone_secondary,
            'address' => $request->address,
            'occupation' => $request->occupation,
        ]);

        return response()->json(['success' => true, 'guarantor' => $guarantor], 201);
    }

    public function sendWhatsappReminder(Request $request, $saleId)
    {
        $this->authorizeForUser($request->user('api'), 'sendWhatsapp', InstallmentPlan::class);

        $message = app(InstallmentWhatsAppService::class)->sendReminder((int) $saleId, $request->template_key);

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function overdueReport(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'viewAny', InstallmentPlan::class);

        $query = Client::query()
            ->whereNotNull('overdue_rating')
            ->when($request->filled('rating'), fn ($q) => $q->where('overdue_rating', $request->rating));

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('cnic', 'like', "%{$s}%");
            });
        }

        $clients = $query->orderBy('name')->paginate((int) ($request->limit ?? 25));

        return response()->json($clients);
    }

    public function downloadKyc(Request $request, string $type, int $id, string $field)
    {
        $this->authorizeForUser($request->user('api'), 'viewDocuments', InstallmentPlan::class);

        $allowed = ['photo_path', 'cnic_front_path', 'cnic_back_path'];
        if (! in_array($field, $allowed, true)) {
            abort(404);
        }

        if ($type === 'client') {
            $model = Client::findOrFail($id);
        } elseif ($type === 'guarantor') {
            $model = Guarantor::findOrFail($id);
        } else {
            abort(404);
        }

        $path = $model->{$field};
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path);
    }
}
