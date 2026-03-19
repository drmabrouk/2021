<?php
if (!defined('ABSPATH')) exit;

$request_id = intval($_GET['request_id'] ?? 0);
global $wpdb;
$r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_membership_requests WHERE id = %d", $request_id));

if (!$r) wp_die('طلب غير موجود.');

$syndicate = SM_Settings::get_syndicate_info();
$govs = SM_Settings::get_governorates();
$univs = SM_Settings::get_universities();
$facs = SM_Settings::get_faculties();
$depts = SM_Settings::get_departments();
$branch_name = SM_Settings::get_branch_name($r->governorate);
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>نموذج طلب عضوية - <?php echo esc_html($r->name); ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.5; color: #333; margin: 0; padding: 0; background: #fff; font-size: 13px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
        .header-logo { max-height: 80px; }
        .header-info { text-align: center; flex: 1; }
        .header-info h1 { margin: 0; font-size: 20px; font-weight: 900; }
        .header-info h2 { margin: 5px 0 0 0; font-size: 16px; color: #555; }

        .form-title { text-align: center; margin-bottom: 25px; }
        .form-title span { display: inline-block; border: 1px solid #333; padding: 8px 30px; font-weight: 900; font-size: 18px; border-radius: 5px; background: #f9f9f9; }

        .section { margin-bottom: 20px; }
        .section-header { background: #eee; padding: 5px 15px; font-weight: 900; border-right: 5px solid #333; margin-bottom: 12px; font-size: 14px; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px 30px; padding: 0 10px; }
        .grid-item { display: flex; border-bottom: 1px dashed #ccc; padding-bottom: 5px; }
        .label { font-weight: 700; width: 140px; color: #555; }
        .value { flex: 1; font-weight: 600; color: #000; }

        .full-width { grid-column: span 2; }

        .photo-box { width: 100px; height: 120px; border: 1px solid #999; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; float: left; margin-top: -100px; }

        .footer { margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; text-align: center; margin-top: 30px; }
        .sig-box { height: 80px; }

        .watermark { position: fixed; top: 40%; left: 10%; transform: rotate(-30deg); font-size: 80px; color: rgba(0,0,0,0.03); z-index: -1; font-weight: 900; width: 100%; text-align: center; }

        @media print {
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #333; color: #fff; border: none; border-radius: 5px;">طباعة النموذج</button>
    </div>

    <div class="watermark">نموذج قيد نقابي</div>

    <div class="header">
        <div style="width: 120px;">
            <?php if(!empty($syndicate['syndicate_logo'])): ?>
                <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" class="header-logo">
            <?php endif; ?>
        </div>
        <div class="header-info">
            <h1><?php echo esc_html($syndicate['syndicate_name']); ?></h1>
            <h2>إدارة شؤون الأعضاء واللجان</h2>
            <div style="font-size: 11px; margin-top: 5px; color: #777;">تاريخ تقديم الطلب: <?php echo date('Y/m/d', strtotime($r->created_at)); ?> | رقم الطلب: #REQ-<?php echo $r->id; ?></div>
        </div>
        <div style="width: 120px; text-align: left;">
            <div style="font-size: 12px; font-weight: 700;"><?php echo esc_html($branch_name); ?></div>
            <div style="font-size: 10px;"><?php echo esc_html($syndicate['address']); ?></div>
        </div>
    </div>

    <div class="form-title">
        <span>استمارة طلب القيد بجدول النقابة</span>
    </div>

    <div class="photo-box">صورة العضو<br>(4 × 6)</div>

    <div class="section">
        <div class="section-header">أولاً: البيانات الشخصية</div>
        <div class="grid">
            <div class="grid-item full-width"><div class="label">الاسم رباعي:</div><div class="value"><?php echo esc_html($r->name); ?></div></div>
            <div class="grid-item"><div class="label">الرقم القومي:</div><div class="value"><?php echo esc_html($r->national_id); ?></div></div>
            <div class="grid-item"><div class="label">الجنس:</div><div class="value"><?php echo ($r->gender === 'male' ? 'ذكر' : 'أنثى'); ?></div></div>
            <div class="grid-item"><div class="label">رقم الهاتف:</div><div class="value"><?php echo esc_html($r->phone); ?></div></div>
            <div class="grid-item"><div class="label">البريد الإلكتروني:</div><div class="value" style="font-family: Arial; font-size: 11px;"><?php echo esc_html($r->email); ?></div></div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">ثانياً: بيانات المؤهل الدراسي</div>
        <div class="grid">
            <div class="grid-item"><div class="label">الجامعة:</div><div class="value"><?php echo esc_html($univs[$r->university] ?? $r->university); ?></div></div>
            <div class="grid-item"><div class="label">الكلية:</div><div class="value"><?php echo esc_html($facs[$r->faculty] ?? $r->faculty); ?></div></div>
            <div class="grid-item"><div class="label">القسم:</div><div class="value"><?php echo esc_html($depts[$r->department] ?? $r->department); ?></div></div>
            <div class="grid-item"><div class="label">سنة التخرج:</div><div class="value"><?php echo esc_html($r->graduation_date); ?></div></div>
            <div class="grid-item full-width"><div class="label">الدرجة العلمية:</div><div class="value"><?php echo esc_html($r->academic_degree); ?></div></div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">ثالثاً: بيانات السكن والفرع المختص</div>
        <div class="grid">
            <div class="grid-item"><div class="label">محافظة الإقامة:</div><div class="value"><?php echo esc_html($govs[$r->residence_governorate] ?? $r->residence_governorate); ?></div></div>
            <div class="grid-item"><div class="label">المدينة:</div><div class="value"><?php echo esc_html($r->residence_city); ?></div></div>
            <div class="grid-item full-width"><div class="label">العنوان بالتفصيل:</div><div class="value"><?php echo esc_html($r->residence_street); ?></div></div>
            <div class="grid-item"><div class="label">الفرع التابع له:</div><div class="value" style="color: #333;"><?php echo esc_html($branch_name); ?></div></div>
            <div class="grid-item"><div class="label">الدرجة المستهدفة:</div><div class="value"><?php echo esc_html($r->professional_grade); ?></div></div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">رابعاً: بيانات سداد الرسوم (للاستخدام الإداري)</div>
        <div class="grid">
            <div class="grid-item"><div class="label">طريقة السداد:</div><div class="value"><?php echo esc_html($r->payment_method ?: '---'); ?></div></div>
            <div class="grid-item"><div class="label">رقم المرجع:</div><div class="value"><?php echo esc_html($r->payment_reference ?: '---'); ?></div></div>
            <div class="grid-item"><div class="label">المبلغ المسدد:</div><div class="value">........................ ج.م</div></div>
            <div class="grid-item"><div class="label">رقم إيصال التوريد:</div><div class="value">........................</div></div>
        </div>
    </div>

    <div class="footer">
        <p style="font-weight: 700;">إقرار من مقدم الطلب:</p>
        <p style="font-size: 11px; margin-bottom: 30px;">أقر أنا الموقع أدناه بصحة كافة البيانات الواردة في هذا النموذج، وأتعهد بالالتزام بكافة القوانين واللوائح المنظمة للعمل النقابي، كما أقر بأنني لم يسبق شطبي من سجلات النقابة لأسباب تأديبية.</p>

        <div class="signatures">
            <div>
                <div style="font-weight: 700; margin-bottom: 10px;">توقيع مقدم الطلب</div>
                <div class="sig-box">..............................</div>
            </div>
            <div>
                <div style="font-weight: 700; margin-bottom: 10px;">موظف الشؤون الإدارية</div>
                <div class="sig-box">..............................</div>
            </div>
            <div>
                <div style="font-weight: 700; margin-bottom: 10px;">اعتماد مدير الفرع</div>
                <div class="sig-box">..............................</div>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #999; border-top: 1px dashed #eee; padding-top: 10px;">
        تم إنشاء هذا النموذج آلياً عبر المنصة الرقمية لنقابة الإصابات والتأهيل - <?php echo date('Y-m-d H:i'); ?>
    </div>

    <script>
        // Auto print window optional
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
