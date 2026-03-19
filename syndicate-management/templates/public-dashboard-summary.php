<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$is_officer = current_user_can('sm_manage_members') || current_user_can('manage_options');

// Check for active surveys for current user role
$user_role = !empty(wp_get_current_user()->roles) ? wp_get_current_user()->roles[0] : '';
$member_specialty = '';
if (in_array('sm_syndicate_member', (array)wp_get_current_user()->roles)) {
    $member_specialty = $wpdb->get_var($wpdb->prepare("SELECT specialization FROM {$wpdb->prefix}sm_members WHERE wp_user_id = %d", get_current_user_id()));
}
$active_surveys = SM_DB::get_surveys(get_current_user_id(), $user_role, $member_specialty);

foreach ($active_surveys as $survey):
    // Check if already responded
    $responded = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sm_survey_responses WHERE survey_id = %d AND user_id = %d", $survey->id, get_current_user_id()));
    if ($responded) continue;

    $is_test = $survey->test_type !== 'survey';
    $attempts_made = SM_DB_Education::get_user_attempts_count($survey->id, get_current_user_id());
    $attempts_left = $survey->max_attempts - $attempts_made;
    $best_score = SM_DB_Education::get_user_best_score($survey->id, get_current_user_id());
    $passed = ($best_score !== null && $best_score >= $survey->pass_score);
?>
<div class="sm-survey-card" style="background: <?php echo $is_test ? '#f0f7ff' : '#fffdf2'; ?>; border: 2px solid <?php echo $is_test ? '#bee3f8' : '#fef3c7'; ?>; border-radius: 12px; padding: 25px; margin-bottom: 30px; position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; right: 0; background: <?php echo $is_test ? '#3182ce' : '#fbbf24'; ?>; color: #fff; font-size: 10px; font-weight: 800; padding: 4px 15px; border-radius: 0 0 0 12px;">
        <?php echo $is_test ? 'اختبار مهني مقرر' : 'استطلاع رأي هام'; ?>
    </div>
    <h3 style="margin: 0 0 10px 0; color: <?php echo $is_test ? '#2c5282' : '#92400e'; ?>;"><?php echo esc_html($survey->title); ?></h3>
    <div style="display:flex; gap:20px; margin-bottom:20px; font-size:12px; color:#64748b;">
        <?php if($is_test): ?>
            <span>⏰ المدة: <?php echo $survey->time_limit; ?> دقيقة</span>
            <span>🎯 درجة النجاح: <?php echo $survey->pass_score; ?>%</span>
            <span>🔄 المحاولات المتبقية: <?php echo $attempts_left; ?></span>
        <?php endif; ?>
    </div>

    <?php if ($passed): ?>
        <div style="background:#f0fff4; color:#22543d; padding:12px 20px; border-radius:10px; display:inline-flex; align-items:center; gap:10px; font-weight:700;">
            <span class="dashicons dashicons-yes-alt"></span> لقد اجتزت هذا الاختبار بنجاح بنسبة <?php echo round($best_score); ?>%
        </div>
    <?php elseif ($attempts_left <= 0): ?>
        <div style="background:#fff5f5; color:#c53030; padding:12px 20px; border-radius:10px; display:inline-flex; align-items:center; gap:10px; font-weight:700;">
            <span class="dashicons dashicons-no-alt"></span> لقد استنفدت كافة المحاولات المتاحة لهذا الاختبار.
        </div>
    <?php else: ?>
        <button class="sm-btn" style="background: <?php echo $is_test ? '#2b6cb0' : '#d97706'; ?>; width: auto;" onclick='smStartProfessionalTest(<?php echo json_encode($survey); ?>)'>
            <?php echo $is_test ? 'بدء الاختبار الآن' : 'المشاركة الآن'; ?>
        </button>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- GLOBAL TEST OVERLAY -->
<div id="sm-test-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#fff; z-index:999999; flex-direction:column; overflow-y:auto;">
    <div style="background:var(--sm-dark-color); color:#fff; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10;">
        <div>
            <h2 id="overlay-test-title" style="margin:0; font-size:1.2em; color:#fff;">اسم الاختبار</h2>
            <div id="test-timer" style="font-size:24px; font-weight:900; color:var(--sm-primary-color); margin-top:5px;">00:00</div>
        </div>
        <button onclick="smExitTest()" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; padding:8px 20px; border-radius:8px; cursor:pointer;">انسحاب وإغلاق</button>
    </div>
    <div id="test-questions-area" style="max-width:800px; margin:40px auto; padding:0 20px; width:100%;">
        <!-- Questions injected here -->
    </div>
    <div style="max-width:800px; margin:0 auto 60px; padding:0 20px; width:100%;">
        <button id="submit-test-btn" class="sm-btn" style="height:55px; font-weight:800; font-size:1.1em;" onclick="smFinishTest()">إرسال الإجابات النهائية وتصحيح الاختبار</button>
    </div>
</div>

<script>
let currentTestTimer = null;
let testQuestions = [];
let activeTestId = 0;

function smStartProfessionalTest(s) {
    if(!confirm('هل أنت مستعد لبدء الاختبار؟ سيتم بدء المحتسب الزمني فوراً.')) return;

    activeTestId = s.id;
    document.getElementById('overlay-test-title').innerText = s.title;
    document.getElementById('sm-test-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Fetch questions
    fetch(ajaxurl + '?action=sm_get_test_questions&test_id=' + s.id + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
    .then(r=>r.json()).then(res => {
        if(res.success) {
            testQuestions = res.data;
            smRenderTestQuestions();
            smStartTimer(s.time_limit);
        } else alert(res.data);
    });
}

function smRenderTestQuestions() {
    const area = document.getElementById('test-questions-area');
    let html = '';
    testQuestions.forEach((q, idx) => {
        html += `
            <div class="test-q-block" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:15px; padding:30px; margin-bottom:30px;">
                <div style="font-weight:900; font-size:1.2em; margin-bottom:20px; color:var(--sm-dark-color); line-height:1.6;">
                    ${idx+1}. ${q.question_text}
                </div>
        `;

        if(q.question_type === 'mcq') {
            const opts = JSON.parse(q.options);
            html += '<div style="display:grid; gap:12px;">';
            opts.forEach((opt, oidx) => {
                html += `
                    <label style="display:flex; align-items:center; gap:12px; background:#fff; padding:15px; border-radius:10px; border:1px solid #edf2f7; cursor:pointer; transition:0.2s;">
                        <input type="radio" name="q_${q.id}" value="${opt}" style="width:20px; height:20px;">
                        <span style="font-weight:600;">${opt}</span>
                    </label>
                `;
            });
            html += '</div>';
        } else if(q.question_type === 'true_false') {
            html += `
                <div style="display:flex; gap:20px;">
                    <label style="flex:1; display:flex; align-items:center; gap:12px; background:#fff; padding:15px; border-radius:10px; border:1px solid #edf2f7; cursor:pointer;">
                        <input type="radio" name="q_${q.id}" value="true"> <strong>صح</strong>
                    </label>
                    <label style="flex:1; display:flex; align-items:center; gap:12px; background:#fff; padding:15px; border-radius:10px; border:1px solid #edf2f7; cursor:pointer;">
                        <input type="radio" name="q_${q.id}" value="false"> <strong>خطأ</strong>
                    </label>
                </div>
            `;
        } else {
            html += `<input type="text" class="sm-input" name="q_${q.id}" placeholder="اكتب إجابتك هنا...">`;
        }

        html += '</div>';
    });
    area.innerHTML = html;
}

function smStartTimer(mins) {
    let sec = mins * 60;
    const el = document.getElementById('test-timer');
    currentTestTimer = setInterval(() => {
        let m = Math.floor(sec / 60);
        let s = sec % 60;
        el.innerText = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
        if(sec <= 0) {
            clearInterval(currentTestTimer);
            alert('انتهى الوقت المحدد للاختبار! سيتم إرسال إجاباتك الحالية تلقائياً.');
            smFinishTest();
        }
        sec--;
    }, 1000);
}

function smFinishTest() {
    const responses = {};
    testQuestions.forEach(q => {
        const el = document.querySelector(`[name="q_${q.id}"]:checked`) || document.querySelector(`input[name="q_${q.id}"]`);
        responses[q.id] = el ? el.value : '';
    });

    const fd = new FormData();
    fd.append('action', 'sm_submit_survey_response');
    fd.append('survey_id', activeTestId);
    fd.append('responses', JSON.stringify(responses));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_survey_action"); ?>');

    document.getElementById('submit-test-btn').disabled = true;
    document.getElementById('submit-test-btn').innerText = 'جاري التصحيح وحفظ النتائج...';

    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        clearInterval(currentTestTimer);
        if(res.success) {
            const data = res.data;
            const emoji = data.passed ? '🎉' : '😕';
            const title = data.passed ? 'تهانينا، لقد اجتزت الاختبار!' : 'عذراً، لم تجتز الاختبار هذه المرة';
            const color = data.passed ? '#38a169' : '#e53e3e';

            document.getElementById('test-questions-area').innerHTML = `
                <div style="text-align:center; padding:50px;">
                    <div style="font-size:80px; margin-bottom:20px;">${emoji}</div>
                    <h2 style="font-weight:900; color:${color};">${title}</h2>
                    <div style="font-size:2.5em; font-weight:900; margin:20px 0;">${Math.round(data.score)}%</div>
                    <p style="font-size:1.2em; color:#64748b; margin-bottom:30px;">تم حفظ النتيجة وإخطار الإدارة بنجاح.</p>
                    <button class="sm-btn" onclick="location.reload()" style="width:auto; padding:0 50px;">العودة للوحة التحكم</button>
                </div>
            `;
            document.getElementById('submit-test-btn').style.display = 'none';
            document.getElementById('test-timer').style.display = 'none';
        } else alert(res.data);
    });
}

function smExitTest() {
    if(confirm('هل أنت متأكد من الانسحاب؟ لن يتم حفظ إجاباتك وستخسر محاولة.')) {
        location.reload();
    }
}
</script>

<?php if ($is_officer): ?>
<div class="sm-card-grid" style="margin-bottom: 30px; grid-template-columns: repeat(4, 1fr); gap: 20px;">
    <div class="sm-stat-card" style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--sm-border-color); text-align: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transition: 0.3s;">
        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3182ce, #4299e1); color: #fff; border-radius: 15px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3);">
            <span class="dashicons dashicons-groups" style="font-size: 24px; width: 24px; height: 24px;"></span>
        </div>
        <div style="font-size: 0.85em; color: #64748b; margin-bottom: 8px; font-weight: 700;">إجمالي الأعضاء</div>
        <div style="font-size: 1.8em; font-weight: 900; color: var(--sm-dark-color);"><?php echo number_format($stats['total_members'] ?? 0); ?></div>
    </div>

    <div class="sm-stat-card" style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--sm-border-color); text-align: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transition: 0.3s;">
        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #dd6b20, #ed8936); color: #fff; border-radius: 15px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 12px rgba(221, 107, 32, 0.3);">
            <span class="dashicons dashicons-id-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
        </div>
        <div style="font-size: 0.85em; color: #64748b; margin-bottom: 8px; font-weight: 700;">تصاريح المزاولة</div>
        <div style="font-size: 1.8em; font-weight: 900; color: var(--sm-dark-color);"><?php echo number_format($stats['total_practice_licenses'] ?? 0); ?></div>
    </div>

    <div class="sm-stat-card" style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--sm-border-color); text-align: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transition: 0.3s;">
        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #805ad5, #9f7aea); color: #fff; border-radius: 15px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 12px rgba(128, 90, 213, 0.3);">
            <span class="dashicons dashicons-building" style="font-size: 24px; width: 24px; height: 24px;"></span>
        </div>
        <div style="font-size: 0.85em; color: #64748b; margin-bottom: 8px; font-weight: 700;">تراخيص المنشآت</div>
        <div style="font-size: 1.8em; font-weight: 900; color: var(--sm-dark-color);"><?php echo number_format($stats['total_facility_licenses'] ?? 0); ?></div>
    </div>

    <div class="sm-stat-card" style="background: #fff; padding: 25px; border-radius: 20px; border: 1px solid var(--sm-border-color); text-align: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transition: 0.3s;">
        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #38a169, #48bb78); color: #fff; border-radius: 15px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3);">
            <span class="dashicons dashicons-money-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
        </div>
        <div style="font-size: 0.85em; color: #64748b; margin-bottom: 8px; font-weight: 700;">إجمالي الإيرادات</div>
        <div style="font-size: 1.8em; font-weight: 900; color: #38a169;"><?php echo number_format($stats['total_revenue'] ?? 0, 2); ?> <span style="font-size: 0.4em;">ج.م</span></div>
    </div>
</div>


<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px;">
    <!-- Financial Collection Trends -->
    <div style="background: #fff; padding: 25px; border: 1px solid var(--sm-border-color); border-radius: 12px; box-shadow: var(--sm-shadow);">
        <h3 style="margin-top:0; font-size: 1.1em; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">تحصيل الإيرادات (آخر 30 يوم)</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="financialTrendsChart"></canvas>
        </div>
    </div>

    <!-- Specialization Distribution -->
    <div style="background: #fff; padding: 25px; border: 1px solid var(--sm-border-color); border-radius: 12px; box-shadow: var(--sm-shadow);">
        <h3 style="margin-top:0; font-size: 1.1em; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">توزيع التخصصات المهنية</h3>
        <div style="height: 300px; position: relative;">
            <canvas id="specializationDistChart"></canvas>
        </div>
    </div>
</div>

<?php endif; ?>





<script>
function smDownloadChart(chartId, fileName) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = fileName + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

(function() {
    <?php if (!$is_officer): ?>
    return;
    <?php endif; ?>
    window.smCharts = window.smCharts || {};

    const initSummaryCharts = function() {
        if (typeof Chart === 'undefined') {
            setTimeout(initSummaryCharts, 200);
            return;
        }

        const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

        // Data for Financial Trends
        const financialData = <?php echo json_encode($stats['financial_trends']); ?>;
        const trendLabels = financialData.map(d => d.date);
        const trendValues = financialData.map(d => d.total);

        new Chart(document.getElementById('financialTrendsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'إجمالي التحصيل اليومي',
                    data: trendValues,
                    borderColor: '#38a169',
                    backgroundColor: 'rgba(56, 161, 105, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Data for Specializations
        const specData = <?php
            $specs_labels = SM_Settings::get_specializations();
            $mapped_specs = [];
            foreach($stats['specializations'] as $s) {
                $mapped_specs[] = [
                    'label' => $specs_labels[$s->specialization] ?? $s->specialization,
                    'count' => $s->count
                ];
            }
            echo json_encode($mapped_specs);
        ?>;

        new Chart(document.getElementById('specializationDistChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: specData.map(d => d.label),
                datasets: [{
                    data: specData.map(d => d.count),
                    backgroundColor: ['#3182ce', '#e53e3e', '#d69e2e', '#38a169', '#805ad5', '#d53f8c']
                }]
            },
            options: chartOptions
        });

        const createOrUpdateChart = (id, config) => {
            if (window.smCharts[id]) {
                window.smCharts[id].destroy();
            }
            const el = document.getElementById(id);
            if (el) {
                window.smCharts[id] = new Chart(el.getContext('2d'), config);
            }
        };


    };

    if (document.readyState === 'complete') initSummaryCharts();
    else window.addEventListener('load', initSummaryCharts);
})();
</script>
