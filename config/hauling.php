<?php

return [
    /*
     * Nilai awal pajak saat membuat invoice (masih bisa diubah di form).
     * PPN diisi otomatis bila perusahaan PKP; PPh 23 diisi bila customer
     * berstatus PKP (dianggap badan pemotong pajak).
     */
    'company_is_pkp' => (bool) env('HAULING_COMPANY_PKP', true),
    'ppn_percent' => (float) env('HAULING_PPN_PERCENT', 11),
    'pph23_percent' => (float) env('HAULING_PPH23_PERCENT', 2),
];
