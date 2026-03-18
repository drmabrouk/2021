<?php if (!defined('ABSPATH')) exit; ?>
<?php
$request_id = intval($_GET['id']);
global $wpdb;
$req = $wpdb->get_row($wpdb->prepare("
    SELECT r.*, s.name as service_name, s.description as service_desc, s.selected_profile_fields, s.required_fields as service_fields, m.name as member_name, m.national_id, m.membership_number, m.governorate, m.professional_grade, m.specialization, m.phone, m.email, m.facility_name
    FROM {$wpdb->prefix}sm_service_requests r
    JOIN {$wpdb->prefix}sm_services s ON r.service_id = s.id
    LEFT JOIN {$wpdb->prefix}sm_members m ON r.member_id = m.id
    WHERE r.id = %d", $request_id));

if (!$req) wp_die('Request not found');

$syndicate = SM_Settings::get_syndicate_info();
$data = json_decode($req->request_data, true);

// Map field names to labels
$field_labels = [];
if (!empty($req->service_fields)) {
    $fields_def = json_decode($req->service_fields, true);
    if (is_array($fields_def)) {
        foreach ($fields_def as $f) {
            $field_labels[$f['name']] = $f['label'];
        }
    }
}
// Add common external fields
$field_labels['cust_name'] = 'اسم العميل (خارجي)';
$field_labels['cust_email'] = 'بريد العميل';
$field_labels['cust_phone'] = 'هاتف العميل';
$field_labels['cust_branch'] = 'فرع العميل';
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>مستند رقمي - <?php echo esc_html($req->service_name); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap');
        body { font-family: 'Amiri', serif; padding: 40px; color: #1a202c; line-height: 1.8; background: #fff; }
        .page-border { border: 10px double #111F35; padding: 40px; min-height: 1000px; position: relative; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 50px; }
        .syndicate-info { text-align: right; }
        .logo-box { text-align: center; }
        .logo { max-height: 110px; margin-bottom: 10px; }
        .authority-info { text-align: left; }

        .doc-title { text-align: center; margin: 40px 0; }
        .doc-title h1 {
            display: inline-block;
            border-bottom: 3px double #111F35;
            padding-bottom: 10px;
            font-size: 32px;
            margin: 0;
            color: #111F35;
        }

        .meta-data {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }

        .content-area { font-size: 20px; margin-bottom: 50px; text-align: justify; }
        .content-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
        .content-table td { padding: 15px; border: 1px solid #cbd5e0; font-size: 18px; }
        .content-table td:first-child { background: #f7fafc; font-weight: bold; width: 35%; color: #2d3748; }

        .footer-sigs { margin-top: 80px; display: grid; grid-template-columns: 1fr 1fr 1fr; text-align: center; font-weight: bold; }
        .stamp-area { position: absolute; bottom: 100px; left: 100px; width: 160px; height: 160px; border: 2px dashed #cbd5e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #cbd5e0; font-size: 14px; transform: rotate(-15deg); }

        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 120px; color: rgba(0,0,0,0.03); z-index: -1; white-space: nowrap; pointer-events: none; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .page-border { border: 10px double #111F35 !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position:fixed; top:20px; left:20px; z-index: 100;">
        <button onclick="window.print()" style="padding:12px 25px; background:#111F35; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">طباعة المستند الرسمي</button>
    </div>

    <div class="page-border">
        <div class="watermark"><?php echo esc_html($syndicate['syndicate_name']); ?></div>

        <div class="header">
            <div class="syndicate-info">
                <div style="font-size: 18px; font-weight: bold;"><?php echo esc_html($syndicate['authority_name']); ?></div>
                <div style="font-size: 22px; font-weight: 900; color: #111F35; margin: 5px 0;"><?php echo esc_html($syndicate['syndicate_name']); ?></div>
                <div style="font-size: 16px; font-weight: bold;"><?php echo esc_html(SM_Settings::get_governorates()[$req->governorate] ?? $req->governorate); ?></div>
            </div>

            <div class="logo-box">
                <?php if (!empty($syndicate['syndicate_logo'])): ?>
                    <img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" class="logo">
                <?php endif; ?>
            </div>

            <div class="authority-info" dir="ltr">
                <div style="font-size: 14px; font-weight: bold;">Date: <?php echo date('d / m / Y'); ?></div>
                <div style="font-size: 14px; font-weight: bold;">Ref: <?php echo $req->id; ?>/SR/<?php echo date('Y'); ?></div>
            </div>
        </div>

        <div class="doc-title">
            <h1><?php echo esc_html($req->service_name); ?></h1>
        </div>

        <div class="content-area">
            <p>تشهد إدارة النقابة بأن السيد الزميل/ <strong><?php echo esc_html($req->member_name); ?></strong></p>
            <p>المقيد بسجلات النقابة برقم عضوية: <strong><?php echo esc_html($req->membership_number ?: '---'); ?></strong></p>
            <p>والحاصل على الرقم القومي المصري: <strong><?php echo esc_html($req->national_id); ?></strong></p>
            <p style="margin-top: 30px;">قد تقدم بطلب رسمي للحصول على المستند الموضح أعلاه، وبعد المراجعة والتدقيق الفني اللازم من قبل الإدارة المختصة، تم اعتماد البيانات التالية:</p>
        </div>

    <table class="content-table">
        <tr style="background: #f8fafc;"><td colspan="2" style="text-align: center; border-top: 2px solid #111F35;">بيانات المستند المعتمدة</td></tr>
        <tr><td>تاريخ تقديم الطلب:</td><td><?php echo date_i18n('j F Y', strtotime($req->created_at)); ?></td></tr>
        <?php
        $pFields = json_decode($req->selected_profile_fields, true) ?: [];
        $profile_map = [
            'name' => ['label' => 'الاسم الكامل', 'value' => $req->member_name],
            'national_id' => ['label' => 'الرقم القومي', 'value' => $req->national_id],
            'membership_number' => ['label' => 'رقم العضوية', 'value' => $req->membership_number],
            'professional_grade' => ['label' => 'الدرجة الوظيفية', 'value' => SM_Settings::get_professional_grades()[$req->professional_grade] ?? $req->professional_grade],
            'specialization' => ['label' => 'التخصص', 'value' => SM_Settings::get_specializations()[$req->specialization] ?? $req->specialization],
            'phone' => ['label' => 'رقم الهاتف', 'value' => $req->phone],
            'email' => ['label' => 'البريد الإلكتروني', 'value' => $req->email],
            'governorate' => ['label' => 'الفرع', 'value' => SM_Settings::get_governorates()[$req->governorate] ?? $req->governorate],
            'facility_name' => ['label' => 'اسم المنشأة', 'value' => $req->facility_name]
        ];

        foreach ($pFields as $fKey) {
            if (isset($profile_map[$fKey])) {
                echo "<tr><td>{$profile_map[$fKey]['label']}:</td><td>{$profile_map[$fKey]['value']}</td></tr>";
            }
        }
        ?>
        <?php foreach ($data as $key => $val):
            $display_label = $field_labels[$key] ?? $key;
        ?>
            <tr><td><?php echo esc_html($display_label); ?>:</td><td><?php echo esc_html($val); ?></td></tr>
        <?php endforeach; ?>
    </table>

    <div style="background: #f1f5f9; padding: 20px; border-radius: 8px; font-size: 13px;">
        يعتبر هذا المستند رسمياً وصادراً من المنصة الرقمية للنقابة.
    </div>

    <div class="footer">
        <div style="text-align: center;">
            <p>توقيع المسؤول</p>
            <br><br>
            <p>..........................</p>
        </div>
        <div class="stamp-box">ختم النقابة</div>
    </div>

    <script>window.onload = () => { /* window.print(); */ }</script>
</body>
</html>
