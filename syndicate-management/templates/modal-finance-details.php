<?php if (!defined('ABSPATH')) exit; ?>
<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 30px;">
    <div>
        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">كشف الحساب المستحق</h4>
        <?php
        $in_grace = false;
        $current_month = (int)date('n');
        if ($current_month >= 1 && $current_month <= 3) {
            foreach ($dues['breakdown'] as $item) {
                if (strpos($item['item'], 'تجديد عضوية') !== false && $item['penalty'] == 0) {
                    $in_grace = true;
                    break;
                }
            }
        }
        if ($in_grace): ?>
            <div style="background: #ebf8ff; color: #2b6cb0; padding: 15px; border-radius: 8px; border: 1px solid #bee3f8; margin-bottom: 15px; font-size: 13px;">
                <span class="dashicons dashicons-info" style="font-size: 18px; width: 18px; height: 18px;"></span> أنت حالياً في <strong>فترة السماح</strong> لتجديد العضوية (يناير - مارس). يمكنك التجديد الآن بدون أي غرامات تأخير.
            </div>
        <?php endif; ?>

        <div style="background: #fff; border: 1px solid #eee; border-radius: 8px; overflow: hidden;">
            <table class="sm-table" style="font-size: 13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th>البند (سبب الاستحقاق)</th>
                        <th>الحالة</th>
                        <th>قيمة الخدمة</th>
                        <th>قيمة الغرامة</th>
                        <th>إجمالي المستحق</th>
                        <?php if (current_user_can('sm_manage_finance')): ?>
                        <th>تحديد للسداد</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dues['breakdown'] as $index => $item):
                        $is_late = $item['penalty'] > 0;
                    ?>
                    <tr <?php echo $is_late ? 'style="background: #fff5f5;"' : ''; ?>>
                        <td style="<?php echo $is_late ? 'color: #c53030; font-weight: 700;' : ''; ?>">
                            <?php if ($is_late): ?>
                                <span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; color: #e53e3e; vertical-align: middle;"></span>
                                <span style="color: #e53e3e; font-size: 10px; display: block;">غرامة تأخير (Fine)</span>
                            <?php endif; ?>
                            <?php echo $item['item']; ?>
                        </td>
                        <td><span class="sm-badge" style="background:#feb2b2; color:#9b2c2c; font-size:10px;">غير مسدد</span></td>
                        <td><?php echo number_format($item['amount'], 2); ?></td>
                        <td style="color:#e53e3e; font-weight: 700;"><?php echo number_format($item['penalty'], 2); ?></td>
                        <td style="font-weight:900;"><?php echo number_format($item['total'], 2); ?></td>
                        <?php if (current_user_can('sm_manage_finance')): ?>
                        <td style="text-align: center;">
                            <button type="button" class="sm-btn" style="height: 25px; padding: 0 10px; font-size: 10px; width: auto; background: #2c3e50;"
                                onclick="smSelectForPayment(<?php echo $item['total']; ?>, '<?php echo esc_js($item['item']); ?>')">سداد هذا البند</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f1f5f9; font-weight: 800;">
                        <td colspan="3">إجمالي المتأخرات والمستحقات (Fine/Amount Due)</td>
                        <td colspan="<?php echo current_user_can('sm_manage_finance') ? '2' : '1'; ?>" style="color:var(--sm-primary-color); font-size:1.1em;"><?php echo number_format($dues['total_owed'], 2); ?> ج.م</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (current_user_can('sm_manage_finance')): ?>
        <div style="margin-top: 25px; background: #ffffff; border: 2px solid #e2e8f0; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <h5 style="margin: 0 0 20px 0; color: #111F35; font-size: 1.1em; font-weight: 800; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <span class="dashicons dashicons-money-alt" style="vertical-align: middle;"></span> تحصيل مبلغ مالي جديد
            </h5>
            <form id="record-payment-form">
                <?php wp_nonce_field('sm_finance_action', 'nonce'); ?>
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div style="grid-column: span 2;">
                        <label class="sm-label" style="font-size:12px; font-weight: 700;">نوع التحصيل (الخدمة):</label>
                        <select name="payment_type" class="sm-select" style="border-width: 2px; font-weight: 600; background: #f8fafc;">
                            <option value="membership">💳 اشتراك عضوية سنوي</option>
                            <option value="license">📜 ترخيص مزاولة مهنة</option>
                            <option value="facility">🏢 ترخيص منشأة صحية/رياضية</option>
                            <option value="penalty">⚠️ غرامة تأخير / جزاء مالي</option>
                            <option value="other">⚙️ خدمات إدارية أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:12px;">المبلغ المحصل (ج.م):</label>
                        <input type="number" name="amount" class="sm-input" value="<?php echo $dues['balance']; ?>" step="0.01" required style="font-size: 1.2em; font-weight: 900; color: #27ae60;">
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:12px;">تاريخ السداد:</label>
                        <input type="date" name="payment_date" class="sm-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:12px;">السنة المستهدفة:</label>
                        <input type="number" name="target_year" class="sm-input" value="<?php echo date('Y'); ?>">
                    </div>
                    <div>
                        <label class="sm-label" style="font-size:12px;">رقم الإيصال الورقي:</label>
                        <input type="text" name="paper_invoice_code" class="sm-input" placeholder="000000">
                    </div>
                    <div style="grid-column: span 2;">
                        <label class="sm-label" style="font-size:12px;">بيان العملية المختصر:</label>
                        <input type="text" name="details_ar" class="sm-input" placeholder="مثال: سداد قيمة اشتراك عام 2024">
                    </div>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label" style="font-size:12px;">ملاحظات التدقيق:</label>
                    <textarea name="notes" class="sm-input" rows="2" placeholder="أي ملاحظات إضافية بخصوص هذه العملية..."></textarea>
                </div>
                <button type="button" onclick="smSubmitPayment(this)" class="sm-btn" style="background:#27ae60; height: 50px; font-weight: 800; font-size: 1.1em; width: 100%; border-radius: 10px; box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);">
                    إتمام عملية السداد وإصدار فاتورة إلكترونية
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">سجل المدفوعات السابقة</h4>
        <div style="max-height: 500px; overflow-y: auto;">
            <?php if (empty($history)): ?>
                <div style="text-align:center; padding: 30px; color: #718096; background: #f8fafc; border-radius: 8px;">لا يوجد سجل مدفوعات لهذا العضو.</div>
            <?php else: ?>
                <table class="sm-table" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>البيان (Reason)</th>
                            <th>الحالة</th>
                            <th>المبلغ المسدد</th>
                            <th>الفاتورة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $p): ?>
                        <tr>
                            <td><?php echo $p->payment_date; ?></td>
                            <td style="font-size:11px;">
                                <?php echo esc_html($p->details_ar ?: $p->payment_type); ?>
                            </td>
                            <td><span class="sm-badge" style="background:#c6f6d5; color:#22543d; font-size:10px;">تم السداد</span></td>
                            <td style="font-weight:700; color:#27ae60;"><?php echo number_format($p->amount, 2); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin-ajax.php?action=sm_print_invoice&payment_id='.$p->id); ?>" target="_blank" class="sm-btn" style="height:24px; padding:0 8px; font-size:10px; width:auto; background:#111F35;">عرض الفاتورة</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function smSelectForPayment(amount, reason) {
    const form = document.getElementById('record-payment-form');
    if (!form) return;
    form.querySelector('[name="amount"]').value = amount;
    form.querySelector('[name="details_ar"]').value = reason;

    // Auto-detect type
    const select = form.querySelector('[name="payment_type"]');
    if (reason.includes('عضوية')) select.value = 'membership';
    else if (reason.includes('تصريح')) select.value = 'license';
    else if (reason.includes('منشأة')) select.value = 'facility';

    // Smooth scroll to form
    form.scrollIntoView({ behavior: 'smooth' });
    form.style.boxShadow = '0 0 15px rgba(39, 174, 96, 0.3)';
    setTimeout(() => form.style.boxShadow = 'none', 2000);
}
</script>
