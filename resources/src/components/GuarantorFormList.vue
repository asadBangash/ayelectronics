<template>
  <div class="guarantor-block">
    <div class="guarantor-header">
      <h3 style="margin: 0">Guarantors</h3>
      <a-button type="dashed" @click="add">
        <template #icon><PlusOutlined /></template>
        Add guarantor
      </a-button>
    </div>
    <p class="muted" style="margin: 8px 0 16px">Add one or more guarantors for this customer. They can be reused on installment sales.</p>

    <a-empty v-if="!modelValue.length" description="No guarantors yet — click Add guarantor" />

    <a-card
      v-for="(g, i) in modelValue"
      :key="g._key"
      size="small"
      style="margin-bottom: 12px"
      :title="`Guarantor ${i + 1}`"
    >
      <template #extra>
        <a-button type="text" danger size="small" @click="remove(i)">
          <template #icon><DeleteOutlined /></template>
          Remove
        </a-button>
      </template>

      <a-row :gutter="16">
        <a-col :xs="24" :md="12">
          <a-form-item label="Name" required>
            <a-input v-model:value="g.full_name" placeholder="Full name" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item label="Father's name">
            <a-input v-model:value="g.father_name" placeholder="Father's name" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item label="CNIC">
            <a-input v-model:value="g.cnic" placeholder="12345-1234567-1" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item label="Email (optional)">
            <a-input v-model:value="g.email" type="email" placeholder="Email" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item label="Contact / WhatsApp">
            <a-input v-model:value="g.whatsapp" placeholder="03xx-xxxxxxx" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item label="Mobile">
            <a-input v-model:value="g.mobile" placeholder="Mobile number" />
          </a-form-item>
        </a-col>
        <a-col :span="24">
          <a-form-item label="Permanent address">
            <a-textarea v-model:value="g.permanent_address" :rows="2" />
          </a-form-item>
        </a-col>
        <a-col :span="24">
          <a-form-item label="Business address">
            <a-textarea v-model:value="g.business_address" :rows="2" />
          </a-form-item>
        </a-col>
        <a-col v-for="img in imageFields" :key="img.key" :xs="24" :md="8">
          <a-form-item :label="img.label">
            <input type="file" accept="image/*" @change="pickFile(g, img.key, $event)" />
            <img
              v-if="preview(g, img.key)"
              :src="preview(g, img.key)"
              :alt="img.label"
              style="display:block;max-width:180px;max-height:140px;margin-top:8px;object-fit:contain"
            />
          </a-form-item>
        </a-col>
      </a-row>
    </a-card>
  </div>
</template>

<script setup>
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import { emptyGuarantorRow } from '../lib/guarantorForm';

const props = defineProps({
  modelValue: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

const imageFields = [
  { key: 'photo', label: 'Photograph (optional)' },
  { key: 'cnic_front', label: 'CNIC front image' },
  { key: 'cnic_back', label: 'CNIC back image' },
];

function add() {
  emit('update:modelValue', [...props.modelValue, emptyGuarantorRow()]);
}

function remove(index) {
  const next = [...props.modelValue];
  next.splice(index, 1);
  emit('update:modelValue', next);
}

function pickFile(row, key, event) {
  const file = event.target.files?.[0];
  if (!file) return;
  row._files = { ...row._files, [key]: file };
  if (row._previews[key]?.startsWith('blob:')) URL.revokeObjectURL(row._previews[key]);
  row._previews = { ...row._previews, [key]: URL.createObjectURL(file) };
  emit('update:modelValue', [...props.modelValue]);
}

function preview(row, key) {
  return row._previews?.[key] || '';
}
</script>

<style scoped>
.guarantor-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.muted {
  color: rgba(0, 0, 0, 0.45);
  font-size: 13px;
}
</style>
