<template>
  <div class="page">
    <PageHeader :title="$t('SaleDetail')" :breadcrumb="[$t('Sales'), $t('SaleDetail')]">
      <template #actions>
        <a-space v-if="!loading" wrap>
          <!-- Multi-Currency: view this document's amounts in another currency -->
          <a-select
            v-if="showCurrencySelect"
            v-model:value="currencySelectId"
            :options="currencyOptions"
            :title="$t('Currency')"
            style="min-width: 92px"
          />
          <a-button @click="$router.push('/sales')">
            <template #icon><ArrowLeftOutlined /></template>
            {{ $t('Back') }}
          </a-button>
          <a-button
            v-if="auth.can('Sales_edit') && sale.sale_has_return === 'no'"
            @click="$router.push(`/sales/${sale.id}/edit`)"
          >
            <template #icon><EditOutlined /></template>
            {{ $t('EditSale') }}
          </a-button>
          <a-button :loading="sendingEmail" @click="sendEmail">
            <template #icon><MailOutlined /></template>
            {{ $t('Email') }}
          </a-button>
          <a-button :loading="sendingSms" @click="sendSms">
            <template #icon><MessageOutlined /></template>
            SMS
          </a-button>
          <a-button :loading="downloadingPdf" @click="downloadPdf">
            <template #icon><FilePdfOutlined /></template>
            PDF
          </a-button>
          <a-button @click="printInvoice">
            <template #icon><PrinterOutlined /></template>
            {{ $t('print') }}
          </a-button>
          <a-button
            v-if="auth.can('Sales_delete') && sale.sale_has_return === 'no'"
            danger
            @click="removeSale"
          >
            <template #icon><DeleteOutlined /></template>
            {{ $t('Del') }}
          </a-button>
        </a-space>
      </template>
    </PageHeader>

    <div v-if="loading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-card v-else id="sale-invoice">
      <div class="inv-head">
        <div>
          <img
            v-if="company.logo"
            :src="'/images/' + company.logo"
            :style="{
              maxWidth: (company.invoice_logo_width || 180) + 'px',
              maxHeight: (company.invoice_logo_height || 60) + 'px',
            }"
            alt=""
          />
        </div>
        <div class="inv-ref">
          <div class="inv-ref-badge">{{ sale.Ref }}</div>
          <table class="inv-meta">
            <tr><td>{{ $t('date') }}</td><td>{{ dateTime(sale.date) }}</td></tr>
            <tr>
              <td>{{ $t('Status') }}</td>
              <td>
                <a-tag :color="docStatusColor(sale.statut)">
                  {{ statusKey(SALE_STATUSES, sale.statut) ? $t(statusKey(SALE_STATUSES, sale.statut)) : sale.statut }}
                </a-tag>
              </td>
            </tr>
            <tr>
              <td>{{ $t('PaymentStatus') }}</td>
              <td>
                <a-tag :color="payStatusColor(sale.payment_status)">
                  {{ statusKey(PAYMENT_STATUSES, sale.payment_status) ? $t(statusKey(PAYMENT_STATUSES, sale.payment_status)) : sale.payment_status }}
                </a-tag>
              </td>
            </tr>
          </table>
        </div>
      </div>

      <a-row :gutter="[16, 16]" style="margin: 16px 0">
        <a-col :xs="24" :md="12">
          <div class="inv-box">
            <div class="inv-box-title">{{ $t('Customer') }}</div>
            <div class="inv-box-name">{{ sale.client_name }}</div>
            <div v-if="sale.client_father_name">Father's name: {{ sale.client_father_name }}</div>
            <div v-if="sale.client_cnic_number">CNIC: {{ sale.client_cnic_number }}</div>
            <div v-if="sale.client_phone">{{ $t('Phone') }}: {{ sale.client_phone }}</div>
            <div v-if="sale.client_email">{{ $t('Email') }}: {{ sale.client_email }}</div>
            <div v-if="sale.client_adr">{{ $t('Adress') }}: {{ sale.client_adr }}</div>
            <div v-if="sale.client_tax">Tax #: {{ sale.client_tax }}</div>
            <div style="display:flex;gap:8px;margin-top:8px">
              <img v-if="sale.client_has_photo" :src="`/api/installment-documents/customer-photo/${sale.client_id}`" alt="Customer photograph" style="width:90px" />
              <img v-if="sale.client_has_cnic_front" :src="`/api/installment-documents/customer-front/${sale.client_id}`" alt="Customer CNIC front" style="width:140px" />
              <img v-if="sale.client_has_cnic_back" :src="`/api/installment-documents/customer-back/${sale.client_id}`" alt="Customer CNIC back" style="width:140px" />
            </div>
          </div>
        </a-col>
        <a-col :xs="24" :md="12">
          <div class="inv-box">
            <div class="inv-box-title">{{ $t('Company') }}</div>
            <div class="inv-box-name">{{ company.CompanyName }}</div>
            <div v-if="company.CompanyPhone">{{ $t('Phone') }}: {{ company.CompanyPhone }}</div>
            <div v-if="company.email">{{ $t('Email') }}: {{ company.email }}</div>
            <div v-if="company.CompanyAdress">{{ $t('Adress') }}: {{ company.CompanyAdress }}</div>
          </div>
        </a-col>
      </a-row>

      <a-table
        :columns="itemColumns"
        :data-source="details"
        :pagination="false"
        size="middle"
        :row-key="(r, i) => i"
        :scroll="{ x: 'max-content' }"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'product'">
            <div style="font-weight: 500">{{ record.name }}</div>
            <div class="muted">{{ record.code }}</div>
            <div v-if="record.is_imei && record.imei_number" class="muted">SN: {{ record.imei_number }}</div>
            <div v-if="record.is_batch_tracked && (record.batches || []).length">
              <a-tag v-for="(b, i) in record.batches" :key="i" style="margin-top: 2px">
                {{ b.batch_number || b.batch_no || b }}
              </a-tag>
            </div>
          </template>
          <template v-else-if="column.key === 'price'">{{ docMoney(record.price) }}</template>
          <template v-else-if="column.key === 'quantity'">
            {{ num(record.quantity) }} {{ record.pack_name || record.unit_sale }}
            <div v-if="record.pack_name && Number(record.pack_multiplier) > 1" class="muted">
              (×{{ record.pack_multiplier }}) = {{ num(record.quantity * record.pack_multiplier) }} {{ record.unit_sale }}
            </div>
          </template>
          <!-- DiscountNet is per unit; this column is a line total like Tax below. -->
          <template v-else-if="column.key === 'discount'">{{ docMoney(record.DiscountNet * record.quantity) }}</template>
          <template v-else-if="column.key === 'tax'">{{ docMoney(record.taxe * record.quantity) }}</template>
          <template v-else-if="column.key === 'total'">{{ docMoney(record.total) }}</template>
        </template>
      </a-table>

      <div class="inv-summary">
        <!-- Summary labels hardcoded English in legacy's invoice too. -->
        <table>
          <tr><td>Subtotal:</td><td>{{ docMoney(subtotal) }}</td></tr>
          <tr><td>Order Tax:</td><td>{{ docMoney(sale.TaxNet) }}</td></tr>
          <tr v-if="Number(sale.discount) > 0">
            <td>Discount:</td>
            <td class="neg">
              <template v-if="String(sale.discount_Method || '2') === '1'">
                - {{ num(sale.discount) }}% ({{ docMoney(discountAmount) }})
              </template>
              <template v-else>- {{ docMoney(discountAmount) }}</template>
            </td>
          </tr>
          <tr v-if="Number(sale.discount_from_points) > 0">
            <td>Discount from Points:</td>
            <td class="neg">- {{ docMoney(sale.discount_from_points) }}</td>
          </tr>
          <tr><td>Shipping:</td><td>{{ docMoney(sale.shipping) }}</td></tr>
          <tr class="grand"><td>{{ $t('Total') }}:</td><td>{{ docMoney(sale.GrandTotal) }}</td></tr>
          <tr><td>{{ $t('Paid') }}:</td><td style="color: #52c41a">{{ docMoney(sale.paid_amount) }}</td></tr>
          <tr><td>{{ $t('Due') }}:</td><td style="color: #ff4d4f">{{ docMoney(sale.due) }}</td></tr>
          <tr v-if="Number(sale.previous_dues) > 0">
            <td>{{ $t('Previous_Dues') }}:</td><td>{{ docMoney(sale.previous_dues) }}</td>
          </tr>
          <tr v-if="Number(sale.previous_dues) > 0">
            <td>{{ $t('Net_Balance') }}:</td>
            <td>{{ docMoney(Number(sale.previous_dues) + Number(sale.due)) }}</td>
          </tr>
        </table>
      </div>

      <a-card title="Installment plan and payment history" size="small" style="margin-top:20px">
        <template v-if="plan">
          <p>Advance: {{ planMoney(plan.advance_amount) }} · Balance: {{ planMoney(Number(plan.sale_total) - Number(plan.advance_amount)) }}</p>
          <a-table :data-source="plan.installments" :pagination="false" row-key="id" size="small" :columns="installmentColumns">
            <template #bodyCell="{column,record}">
              <template v-if="column.key==='amount'">{{ planMoney(record.amount) }}</template>
              <template v-else-if="column.key==='paid'">{{ planMoney(record.paid_amount) }}</template>
              <template v-else-if="column.key==='balance'">{{ planMoney(Number(record.amount)-Number(record.paid_amount)) }}</template>
              <template v-else-if="column.key==='action'"><a-button size="small" :disabled="record.status==='paid'" @click="openPayment(record)">Record payment</a-button></template>
            </template>
          </a-table>
          <h3>Guarantors</h3>
          <a-row :gutter="16"><a-col v-for="g in plan.guarantors" :key="g.id" :span="12">
            <p>{{ g.full_name }} · {{ g.father_name }} · {{ g.cnic_number }} · {{ g.mobile_number }}</p><p>{{ g.address }}</p>
            <img :src="`/api/installment-documents/guarantor-front/${g.id}`" alt="Guarantor CNIC front" style="width:140px;margin-right:8px" />
            <img :src="`/api/installment-documents/guarantor-back/${g.id}`" alt="Guarantor CNIC back" style="width:140px" />
          </a-col></a-row>
          <h3>Payment history</h3>
          <a-table :data-source="paymentHistory" :pagination="false" row-key="id" size="small" :columns="paymentColumns">
            <template #bodyCell="{column,record}">
              <template v-if="column.key==='scheduled'">{{ planMoney(record.installment?.amount) }}</template>
              <template v-else-if="column.key==='received'">{{ planMoney(record.montant) }}</template>
              <template v-else-if="column.key==='remaining'">{{ planMoney(record.remaining_balance) }}</template>
              <template v-else-if="column.key==='method'">{{ record.installment_payment_method || record.payment_method?.name || '—' }}</template>
              <template v-else-if="column.key==='staff'">{{ record.user?.username || '—' }}</template>
              <template v-else-if="column.key==='when'">{{ dateTime(record.created_at || record.date) }}</template>
            </template>
          </a-table>
        </template>
        <template v-else>
          <p>This sale has no installment plan.</p>
          <a-button v-if="Number(sale.due)>0" type="primary" @click="planOpen=true">Create installment plan</a-button>
        </template>
      </a-card>

      <a-modal v-model:open="planOpen" title="Create installment plan" :confirm-loading="savingPlan" @ok="createPlan">
        <a-form layout="vertical">
          <a-form-item label="Advance payment"><a-input-number v-model:value="planForm.advance" :min="0" :max="Number(sale.GrandTotal)" style="width:100%" /></a-form-item>
          <a-form-item label="Number of installments"><a-input-number v-model:value="planForm.count" :min="1" :max="120" style="width:100%" /></a-form-item>
          <a-form-item label="First due date"><a-date-picker v-model:value="planForm.firstDue" value-format="YYYY-MM-DD" style="width:100%" /></a-form-item>
          <div v-for="(g,i) in planForm.guarantors" :key="i"><h3>Guarantor {{ i+1 }}</h3>
            <a-input v-model:value="g.full_name" placeholder="Full name" style="margin-bottom:6px"/><a-input v-model:value="g.father_name" placeholder="Father's name" style="margin-bottom:6px"/>
            <a-input v-model:value="g.cnic_number" placeholder="CNIC number" style="margin-bottom:6px"/><a-input v-model:value="g.mobile_number" placeholder="Mobile number" style="margin-bottom:6px"/>
            <a-textarea v-model:value="g.address" placeholder="Address" style="margin-bottom:6px"/>
            CNIC front <input type="file" accept="image/*" @change="selectGuarantorImage(g,'cnic_front',$event)" /> CNIC back <input type="file" accept="image/*" @change="selectGuarantorImage(g,'cnic_back',$event)" />
            <div style="display:flex;gap:8px;margin:8px 0"><img v-if="g.cnic_front_preview" :src="g.cnic_front_preview" alt="Guarantor CNIC front preview" style="width:120px;max-height:90px;object-fit:contain"/><img v-if="g.cnic_back_preview" :src="g.cnic_back_preview" alt="Guarantor CNIC back preview" style="width:120px;max-height:90px;object-fit:contain"/></div>
          </div>
        </a-form>
      </a-modal>
      <a-modal v-model:open="paymentOpen" title="Record installment payment" :confirm-loading="savingPayment" @ok="savePayment">
        <p>Installment {{ selectedInstallment?.number }} · Remaining {{ docMoney(Number(selectedInstallment?.amount || 0)-Number(selectedInstallment?.paid_amount || 0)) }}</p>
        <a-form layout="vertical"><a-form-item label="Amount received"><a-input-number v-model:value="paymentForm.amount" :min="0.01" style="width:100%" /></a-form-item>
          <a-form-item label="Payment method"><a-select v-model:value="paymentForm.method" :options="paymentMethods" /></a-form-item>
          <a-form-item label="Transaction reference"><a-input v-model:value="paymentForm.reference" /></a-form-item>
        </a-form>
      </a-modal>

      <div v-if="company.is_invoice_footer && company.invoice_footer" class="inv-footer">
        {{ company.invoice_footer }}
      </div>
    </a-card>

    <SaleInstallmentPanel v-if="!loading && sale.id" :sale-id="sale.id" />
  </div>
</template>

<script setup>
/**
 * GET sales/{id} → {sale, details, company}. Invoice-style page; discount math
 * mirrors legacy: subtotal = Σ line totals, percent discounts (discount_Method
 * '1') apply to that subtotal, fixed ones are capped at it. Edit/Delete hidden
 * once the sale has a return. Print uses the browser with a print-scoped
 * stylesheet — the PDF/email/SMS actions stay legacy for now.
 */
import { ref, computed, createVNode, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import {
  ArrowLeftOutlined, PrinterOutlined, EditOutlined, DeleteOutlined,
  ExclamationCircleOutlined, MailOutlined, MessageOutlined, FilePdfOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import SaleInstallmentPanel from './SaleInstallmentPanel.vue';
import { useFormat } from '../../composables/useFormat';
import { useDetailsCurrency } from '../../composables/useDetailsCurrency';
import { useAuthStore } from '../../stores/auth';
import { docStatusColor, payStatusColor } from '../../lib/statusColors';
import { SALE_STATUSES, PAYMENT_STATUSES, statusKey } from './saleVocab';
import http from '../../lib/http';

const { t } = useI18n();
const { date, dateTime } = useFormat();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const sale = ref({});

// Multi-Currency: amounts arrive converted into the document's currency;
// the header selector re-expresses them in any other currency (view-only).
const { docMoney, currencyOptions, currencySelectId, showCurrencySelect } = useDetailsCurrency(sale);
const details = ref([]);
const company = ref({});
const sendingEmail = ref(false);
const sendingSms = ref(false);
const downloadingPdf = ref(false);
const plan = ref(null);
const planOpen = ref(false);
const savingPlan = ref(false);
const paymentOpen = ref(false);
const savingPayment = ref(false);
const selectedInstallment = ref(null);
const planForm = ref({ advance: 0, count: 3, firstDue: new Date().toISOString().slice(0, 10), guarantors: [emptyGuarantor(), emptyGuarantor()] });
const paymentForm = ref({ amount: null, method: 'Cash', reference: '' });
const paymentMethods = ['Cash','Bank Transfer','Easypaisa','JazzCash','Bank Deposit','Online Transfer','Other'].map(value => ({label:value,value}));
const installmentColumns = [
  {title:'#',dataIndex:'number',key:'number'}, {title:'Due date',dataIndex:'due_date',key:'due_date'},
  {title:'Amount',key:'amount',align:'right'}, {title:'Paid',key:'paid',align:'right'},
  {title:'Balance',key:'balance',align:'right'}, {title:'Status',dataIndex:'status',key:'status'}, {title:'',key:'action'},
];
const paymentColumns = [
  {title:'Date/time',key:'when'}, {title:'Installment #',dataIndex:['installment','number'],key:'number'},
  {title:'Scheduled',key:'scheduled'}, {title:'Received',key:'received'}, {title:'Remaining',key:'remaining'},
  {title:'Method',key:'method'}, {title:'Reference',dataIndex:'transaction_reference',key:'reference'}, {title:'Received by',key:'staff'},
];
const paymentHistory = computed(() => (plan.value?.installments || []).flatMap(installment => {
  let paid = 0;
  return [...(installment.payments || [])]
    .sort((a,b) => String(a.created_at || a.date).localeCompare(String(b.created_at || b.date)))
    .map(payment => {
      paid += Number(payment.montant) || 0;
      return {...payment, installment, remaining_balance:Math.max(0, Number(installment.amount) - paid)};
    });
}).sort((a,b) => String(b.created_at || b.date).localeCompare(String(a.created_at || a.date))));
const planMoney = value => docMoney((Number(value) || 0) * (Number(sale.value.currency_rate) || 1));
function emptyGuarantor() { return {full_name:'',father_name:'',cnic_number:'',mobile_number:'',address:'',cnic_front:null,cnic_back:null}; }
function selectGuarantorImage(guarantor, key, event) {
  const file = event.target.files?.[0];
  if (!file) return;
  guarantor[key] = file;
  const previewKey = `${key}_preview`;
  if (guarantor[previewKey]?.startsWith('blob:')) URL.revokeObjectURL(guarantor[previewKey]);
  guarantor[previewKey] = URL.createObjectURL(file);
}

const num = v => {
  const n = Number(v);
  return Number.isFinite(n) ? +n.toFixed(2) : 0;
};

const subtotal = computed(() =>
  details.value.reduce((sum, d) => sum + (Number(d?.total) || 0), 0)
);

const discountAmount = computed(() => {
  const method = String(sale.value.discount_Method || '2');
  const val = Number(sale.value.discount) || 0;
  if (subtotal.value <= 0) return 0;
  if (method === '1') return +(subtotal.value * (val / 100)).toFixed(2);
  return +Math.min(val, subtotal.value).toFixed(2);
});

const itemColumns = computed(() => [
  { title: t('ProductName'), key: 'product' },
  { title: t('Price'), key: 'price', align: 'right' },
  { title: t('Quantity'), key: 'quantity', align: 'right' },
  { title: t('Discount'), key: 'discount', align: 'right' },
  { title: t('Tax'), key: 'tax', align: 'right' },
  { title: t('Total'), key: 'total', align: 'right' },
]);

function printInvoice() {
  window.print();
}

function openPayment(installment) {
  selectedInstallment.value = installment;
  paymentForm.value = {amount:null, method:'Cash', reference:'', requestKey:crypto.randomUUID()};
  paymentOpen.value = true;
}

async function createPlan() {
  const f = planForm.value;
  if (!f.firstDue || !f.count || f.guarantors.some(g => !g.full_name || !g.father_name || !g.cnic_number || !g.mobile_number || !g.address || !g.cnic_front || !g.cnic_back)) {
    message.error('Enter the schedule and all details and CNIC images for both guarantors.'); return;
  }
  savingPlan.value = true;
  try {
    const rate = Number(sale.value.currency_rate) || 1;
    const advance = Number(f.advance || 0) / rate;
    const due = (Number(sale.value.GrandTotal) - Number(f.advance || 0)) / rate;
    const total = Math.round(due * 1000) / 1000;
    const count = Number(f.count);
    const each = Math.floor((total / count) * 1000) / 1000;
    const installments = Array.from({length:count}, (_,i) => {
      const date = new Date(`${f.firstDue}T12:00:00`); date.setMonth(date.getMonth()+i);
      return {due_date:date.toISOString().slice(0,10),amount:i===count-1 ? Math.round((total-each*(count-1))*1000)/1000 : each};
    });
    const data = new FormData();
    data.append('client_id', sale.value.client_id); data.append('advance_amount', advance);
    installments.forEach((row,i) => { data.append(`installments[${i}][due_date]`,row.due_date); data.append(`installments[${i}][amount]`,row.amount); });
    f.guarantors.forEach((g,i) => Object.entries(g).forEach(([key,value]) => { if (!key.endsWith('_preview') && value !== null) data.append(`guarantors[${i}][${key}]`,value); }));
    const result = await http.postForm(`sales/${sale.value.id}/installments`,data);
    plan.value = result.plan;
    planOpen.value = false;
    message.success('Installment plan saved.');
  } catch (e) { message.error(e?.data?.message || 'Could not save installment plan.'); }
  finally { savingPlan.value = false; }
}

async function savePayment() {
  if (!selectedInstallment.value || !(Number(paymentForm.value.amount) > 0)) return;
  savingPayment.value = true;
  try {
    const result = await http.post(`sales/${sale.value.id}/installments/${selectedInstallment.value.id}/payments`, {
      amount: Number(paymentForm.value.amount) / (Number(sale.value.currency_rate) || 1), method:paymentForm.value.method,
      transaction_reference:paymentForm.value.reference || null, request_key:paymentForm.value.requestKey,
    });
    const [data, refreshed] = await Promise.all([
      http.get(`sales/${sale.value.id}/installments`),
      http.get(`sales/${sale.value.id}`),
    ]);
    plan.value = data.plan;
    sale.value = {...refreshed.sale, currency_rate: sale.value.currency_rate};
    paymentOpen.value = false; message.success('Payment recorded.');
  } catch (e) { message.error(e?.data?.message || 'Could not record payment.'); }
  finally { savingPayment.value = false; }
}

// Same endpoints as legacy detail_sale.vue.
async function sendEmail() {
  sendingEmail.value = true;
  try {
    await http.post('sales_send_email', { id: sale.value.id });
    message.success(t('SendEmail'));
  } catch (e) {
    message.error(t('SMTPIncorrect'));
  } finally {
    sendingEmail.value = false;
  }
}

async function sendSms() {
  sendingSms.value = true;
  try {
    await http.post('sales_send_sms', { id: sale.value.id });
    message.success(t('Send_SMS'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    sendingSms.value = false;
  }
}

async function downloadPdf() {
  downloadingPdf.value = true;
  try {
    await http.download(`sale_pdf/${sale.value.id}`, `Sale_${sale.value.Ref}.pdf`);
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    downloadingPdf.value = false;
  }
}

function removeSale() {
  Modal.confirm({
    title: t('Delete_Title'),
    icon: createVNode(ExclamationCircleOutlined),
    content: `${t('Delete_Text')} — ${sale.value.Ref}`,
    okText: t('Delete_confirmButtonText'),
    okType: 'danger',
    cancelText: t('Delete_cancelButtonText'),
    async onOk() {
      try {
        await http.delete(`sales/${sale.value.id}`);
        message.success(t('Deleted_in_successfully'));
        router.push('/sales');
      } catch (e) {
        message.error(t('InvalidData'));
      }
    },
  });
}

onMounted(async () => {
  try {
    const data = await http.get(`sales/${route.params.id}`);
    sale.value = data.sale || {};
    details.value = data.details || [];
    company.value = data.company || {};
    const installmentData = await http.get(`sales/${route.params.id}/installments`);
    sale.value = {...sale.value, currency_rate: sale.value.currency_rate || installmentData.sale?.exchange_rate || 1};
    plan.value = installmentData.plan || null;
  } catch (e) {
    message.error(t('InvalidData'));
    router.push('/sales');
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.inv-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}
.inv-ref-badge {
  display: inline-block;
  background: #6d28d9;
  color: #fff;
  font-weight: 600;
  padding: 4px 14px;
  border-radius: 6px;
  margin-bottom: 8px;
}
.inv-meta td {
  padding: 2px 8px 2px 0;
  color: rgba(0, 0, 0, 0.65);
  font-size: 13px;
}
.inv-meta td:first-child {
  font-weight: 500;
}
.inv-box {
  border: 1px solid rgba(5, 5, 5, 0.08);
  border-radius: 8px;
  padding: 12px 16px;
  height: 100%;
  font-size: 13px;
  color: rgba(0, 0, 0, 0.65);
}
.inv-box-title {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgba(0, 0, 0, 0.45);
  margin-bottom: 4px;
}
.inv-box-name {
  font-weight: 600;
  font-size: 15px;
  color: rgba(0, 0, 0, 0.88);
  margin-bottom: 4px;
}
.muted {
  color: rgba(0, 0, 0, 0.45);
  font-size: 12px;
}
.inv-summary {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.inv-summary table {
  min-width: 320px;
  border-collapse: collapse;
}
.inv-summary td {
  padding: 6px 8px;
  border-bottom: 1px solid rgba(5, 5, 5, 0.06);
  font-size: 13px;
}
.inv-summary td:last-child {
  text-align: right;
  font-weight: 500;
}
.inv-summary .neg {
  color: #ff4d4f;
}
.inv-summary .grand td {
  font-size: 15px;
  font-weight: 700;
  border-top: 2px solid rgba(5, 5, 5, 0.15);
}
.inv-footer {
  margin-top: 24px;
  padding-top: 12px;
  border-top: 1px dashed rgba(5, 5, 5, 0.15);
  color: rgba(0, 0, 0, 0.45);
  font-size: 13px;
  text-align: center;
}

@media print {
  /* Print just the invoice card. */
  :global(.ant-layout-sider),
  :global(.ant-layout-header),
  :global(.page-header) {
    display: none !important;
  }
  #sale-invoice {
    box-shadow: none !important;
    border: none !important;
  }
}
</style>
