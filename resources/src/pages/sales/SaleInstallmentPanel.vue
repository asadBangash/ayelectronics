<template>
  <a-card v-if="bundle" title="Installment & KYC" style="margin-top: 16px">
    <a-tabs>
      <a-tab-pane key="plan" tab="Installment plan">
        <template v-if="bundle.installment_plan">
          <a-descriptions bordered size="small" :column="{ xs: 1, md: 2 }">
            <a-descriptions-item label="Down payment">PKR {{ bundle.installment_plan.down_payment }}</a-descriptions-item>
            <a-descriptions-item label="Financed">PKR {{ bundle.installment_plan.financed_amount }}</a-descriptions-item>
            <a-descriptions-item label="Months">{{ bundle.installment_plan.duration_months }}</a-descriptions-item>
            <a-descriptions-item label="Monthly">PKR {{ bundle.installment_plan.monthly_installment }}</a-descriptions-item>
            <a-descriptions-item label="Outstanding">PKR {{ bundle.installment_plan.outstanding_balance }}</a-descriptions-item>
            <a-descriptions-item label="Next due">{{ bundle.installment_plan.next_due_date || '—' }}</a-descriptions-item>
            <a-descriptions-item label="Overdue days">{{ bundle.installment_plan.overdue_days }}</a-descriptions-item>
            <a-descriptions-item label="Overdue amount">PKR {{ bundle.installment_plan.overdue_amount }}</a-descriptions-item>
          </a-descriptions>
          <a-table
            style="margin-top: 12px"
            size="small"
            :pagination="false"
            :data-source="bundle.schedule"
            :row-key="r => r.id"
            :columns="scheduleColumns"
          />
        </template>
        <a-empty v-else description="No installment plan for this sale" />
      </a-tab-pane>

      <a-tab-pane key="customer" tab="Customer KYC">
        <a-tag :color="ratingColor(bundle.client_rating?.level)">{{ bundle.client_rating?.label }}</a-tag>
        <a-descriptions bordered size="small" :column="1" style="margin-top: 8px">
          <a-descriptions-item label="CNIC">{{ bundle.client?.cnic || '—' }}</a-descriptions-item>
          <a-descriptions-item label="WhatsApp">{{ bundle.client?.whatsapp || '—' }}</a-descriptions-item>
          <a-descriptions-item label="Area">{{ bundle.client?.area || '—' }}</a-descriptions-item>
          <a-descriptions-item label="Occupation">{{ bundle.client?.occupation || '—' }}</a-descriptions-item>
        </a-descriptions>
      </a-tab-pane>

      <a-tab-pane key="guarantors" tab="Guarantors">
        <div v-for="sg in bundle.guarantors" :key="sg.id" style="margin-bottom: 12px">
          <strong>Guarantor {{ sg.position }}</strong> — {{ sg.guarantor?.full_name }}
          <div class="muted">CNIC: {{ sg.guarantor?.cnic || '—' }} · {{ sg.relationship || '' }}</div>
        </div>
        <a-empty v-if="!bundle.guarantors?.length" description="No guarantors linked" />
      </a-tab-pane>

      <a-tab-pane key="payments" tab="Payment history">
        <a-table size="small" :pagination="false" :data-source="bundle.payments" :row-key="r => r.id" :columns="paymentColumns" />
      </a-tab-pane>

      <a-tab-pane key="whatsapp" tab="WhatsApp">
        <a-space style="margin-bottom: 8px">
          <a-button v-if="canWhatsapp" type="primary" :loading="sending" @click="sendReminder">Send reminder</a-button>
        </a-space>
        <a-table size="small" :pagination="false" :data-source="bundle.whatsapp_history" :row-key="r => r.id" :columns="waColumns" />
      </a-tab-pane>
    </a-tabs>
  </a-card>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { message } from 'ant-design-vue';
// message used for setup / error hints when API returns not-ready
import http from '../../lib/http';
import { useAuthStore } from '../../stores/auth';

const props = defineProps({ saleId: { type: [Number, String], required: true } });
const auth = useAuthStore();
const bundle = ref(null);
const sending = ref(false);

const canWhatsapp = computed(() => auth.can('Installments_whatsapp'));

const scheduleColumns = [
  { title: '#', dataIndex: 'installment_number', key: 'n', width: 48 },
  { title: 'Due', dataIndex: 'due_date', key: 'due' },
  { title: 'Due amount', dataIndex: 'amount_due', key: 'due_amt' },
  { title: 'Paid', dataIndex: 'amount_paid', key: 'paid' },
  { title: 'Remaining', dataIndex: 'remaining_amount', key: 'rem' },
  { title: 'Status', dataIndex: 'status', key: 'st' },
];

const paymentColumns = [
  { title: 'Ref', dataIndex: 'Ref', key: 'ref' },
  { title: 'Date', dataIndex: 'date', key: 'date' },
  { title: 'Amount', dataIndex: 'montant', key: 'amt' },
];

const waColumns = [
  { title: 'When', dataIndex: 'created_at', key: 'c' },
  { title: 'To', dataIndex: 'to_number', key: 'to' },
  { title: 'Status', dataIndex: 'status', key: 'st' },
];

function ratingColor(level) {
  return ({ green: 'success', blue: 'processing', orange: 'warning', red: 'error', dark_red: 'error' }[level] || 'default');
}

async function load() {
  if (!props.saleId) return;
  try {
    const data = await http.get(`installments/sales/${props.saleId}/details`);
    if (data?.ready === false && data?.message) {
      message.warning(data.message);
    }
    bundle.value = data?.ready === false ? { installment_plan: null, schedule: [], guarantors: [], payments: [], whatsapp_history: [], client_rating: { level: 'green', label: '' }, client: {} } : data;
  } catch (e) {
    bundle.value = null;
    message.error(e?.data?.message || 'Installment module failed to load (run migrate + seed on server).');
  }
}

async function sendReminder() {
  sending.value = true;
  try {
    await http.post(`installments/sales/${props.saleId}/whatsapp-reminder`);
    message.success('WhatsApp reminder queued');
    await load();
  } catch (e) {
    message.error(e.response?.data?.message || 'Failed to send reminder');
  } finally {
    sending.value = false;
  }
}

watch(() => props.saleId, load, { immediate: true });
</script>

<style scoped>
.muted { color: var(--text-muted, #888); font-size: 12px; }
</style>
