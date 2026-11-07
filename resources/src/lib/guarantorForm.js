let keySeq = 0;

export function emptyGuarantorRow(overrides = {}) {
  return {
    _key: ++keySeq,
    id: null,
    full_name: '',
    father_name: '',
    cnic: '',
    mobile: '',
    whatsapp: '',
    email: '',
    permanent_address: '',
    business_address: '',
    _files: {},
    _previews: {},
    ...overrides,
  };
}

/** Append guarantors[index][...] fields to an existing FormData instance. */
export function appendGuarantorsToFormData(data, guarantors) {
  let i = 0;
  guarantors.forEach((g) => {
    if (!String(g.full_name || '').trim()) return;
    const fields = ['id', 'full_name', 'father_name', 'cnic', 'mobile', 'whatsapp', 'email', 'permanent_address', 'business_address'];
    fields.forEach((f) => {
      if (g[f] != null && g[f] !== '') data.append(`guarantors[${i}][${f}]`, g[f]);
    });
    ['photo', 'cnic_front', 'cnic_back'].forEach((f) => {
      if (g._files?.[f]) data.append(`guarantors[${i}][${f}]`, g._files[f]);
    });
    i += 1;
  });
}
