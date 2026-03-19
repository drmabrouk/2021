<?php if (!defined('ABSPATH')) exit; global $wpdb; ?>
<div class="sm-surveys-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h3 style="margin:0;">إدارة اختبارات الممارسة المهنية</h3>
        <button class="sm-btn" onclick="smOpenNewSurveyModal()" style="width: auto;">+ إنشاء اختبار جديد</button>
    </div>

    <div class="sm-table-container">
        <table class="sm-table">
            <thead>
                <tr>
                    <th>بيانات الاختبار</th>
                    <th>الإعدادات والوقت</th>
                    <th>الفئة / التخصص</th>
                    <th>تاريخ البدء</th>
                    <th>الحالة</th>
                    <th>المشاركات</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $surveys = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sm_surveys ORDER BY created_at DESC");
                $user = wp_get_current_user();
                $is_syndicate_admin = in_array('sm_syndicate_admin', (array)$user->roles);
                $my_gov = get_user_meta($user->ID, 'sm_governorate', true);

                $specs_labels = SM_Settings::get_specializations();
                foreach ($surveys as $s):
                    $resp_where = $wpdb->prepare("survey_id = %d", $s->id);
                    if ($is_syndicate_admin && $my_gov) {
                        $resp_where .= $wpdb->prepare(" AND (
                            EXISTS (SELECT 1 FROM {$wpdb->prefix}usermeta um WHERE um.user_id = user_id AND um.meta_key = 'sm_governorate' AND um.meta_value = %s)
                            OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sm_members m WHERE m.wp_user_id = user_id AND m.governorate = %s)
                        )", $my_gov, $my_gov);
                    }
                    $responses_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sm_survey_responses WHERE $resp_where");
                    $questions_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sm_test_questions WHERE test_id = %d", $s->id));
                    $test_type_map = ['practice' => 'مزاولة مهنة', 'promotion' => 'ترقية درجة', 'training' => 'دورة تدريبية'];
                ?>
                <tr>
                    <td>
                        <div style="font-weight: 800; color:var(--sm-dark-color);"><?php echo esc_html($s->title); ?></div>
                        <div style="font-size: 11px; color:#64748b; margin-top:4px;">
                            <span class="dashicons dashicons-editor-help" style="font-size:14px; width:14px; height:14px;"></span> <?php echo $questions_count; ?> سؤال مدرج
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 12px;">⏰ <?php echo $s->time_limit; ?> دقيقة</div>
                        <div style="font-size: 12px; color:#38a169; font-weight:700;">🎯 نجاح: <?php echo $s->pass_score; ?>%</div>
                        <div style="font-size: 10px; color:#94a3b8;">المحاولات: <?php echo $s->max_attempts; ?></div>
                    </td>
                    <td>
                        <div style="font-size: 12px; font-weight:700; color:var(--sm-primary-color);"><?php echo $test_type_map[$s->test_type] ?? $s->test_type; ?></div>
                        <div style="font-size: 11px; color:#64748b;"><?php echo !empty($s->specialty) ? ($specs_labels[$s->specialty] ?? $s->specialty) : 'تخصص عام'; ?></div>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($s->created_at)); ?></td>
                    <td>
                        <span class="sm-badge" style="background: <?php echo $s->status === 'active' ? '#38a169' : '#e53e3e'; ?>;">
                            <?php echo $s->status === 'active' ? 'نشط' : 'ملغى'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="sm-btn sm-btn-outline" onclick="smViewSurveyResults(<?php echo $s->id; ?>, '<?php echo esc_js($s->title); ?>')" style="padding: 2px 10px; font-size: 11px;">
                            <?php echo $responses_count; ?> نتيجة
                        </button>
                    </td>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <button class="sm-btn" style="padding:4px 8px; font-size:11px; background:var(--sm-dark-color);" onclick='smOpenQuestionBank(<?php echo json_encode($s); ?>)'>الأسئلة</button>
                            <button class="sm-btn sm-btn-outline" onclick="smOpenEditSurveyModal(<?php echo json_encode($s); ?>)" style="padding: 4px 8px; font-size: 11px;"><span class="dashicons dashicons-edit"></span></button>
                            <?php if ($s->status === 'active'): ?>
                                <button class="sm-btn sm-btn-outline" onclick="smOpenAssignModal(<?php echo $s->id; ?>, '<?php echo esc_js($s->title); ?>')" style="padding: 4px 8px; font-size: 11px;">تعيين</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- NEW/EDIT SURVEY MODAL -->
<div id="new-survey-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 750px;">
        <div class="sm-modal-header">
            <h3 id="survey-modal-title">إعداد اختبار ممارسة مهنية جديد</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 25px;">
            <input type="hidden" id="survey_id">
            <div class="sm-form-group">
                <label class="sm-label">عنوان الاختبار / المسابقة:</label>
                <input type="text" id="survey_title" class="sm-input" placeholder="مثال: اختبار الحصول على درجة أخصائي" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0;">
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:12px;">مدة الاختبار (دقيقة):</label>
                    <input type="number" id="survey_time_limit" class="sm-input" value="30">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:12px;">أقصى عدد محاولات:</label>
                    <input type="number" id="survey_max_attempts" class="sm-input" value="1">
                </div>
                <div class="sm-form-group" style="margin-bottom:0;">
                    <label class="sm-label" style="font-size:12px;">درجة النجاح (%):</label>
                    <input type="number" id="survey_pass_score" class="sm-input" value="50">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="sm-form-group">
                    <label class="sm-label">التخصص المرتبط:</label>
                    <select id="survey_specialty" class="sm-select">
                        <option value="">-- كافة التخصصات (عام) --</option>
                        <?php foreach (SM_Settings::get_specializations() as $k => $v) echo "<option value='$k'>$v</option>"; ?>
                    </select>
                </div>
                <div class="sm-form-group">
                    <label class="sm-label">نوع الاختبار:</label>
                    <select id="survey_test_type" class="sm-select">
                        <option value="practice">اختبار مزاولة مهنة</option>
                        <option value="promotion">اختبار ترقية درجة</option>
                        <option value="training">دورة تدريبية</option>
                    </select>
                </div>
            </div>

            <div class="sm-form-group">
                <label class="sm-label">الفئة المستهدفة بالظهور التلقائي:</label>
                <select id="survey_recipients" class="sm-select">
                    <option value="all">الجميع</option>
                    <option value="sm_member">الأعضاء فقط</option>
                    <option value="sm_syndicate_member">أعضاء النقابة فقط</option>
                    <option value="sm_syndicate_admin">مسؤولو النقابة فقط</option>
                </select>
            </div>

            <div style="margin-top:30px; display:flex; gap:10px;">
                <button class="sm-btn" id="survey_submit_btn" onclick="smSaveSurvey()" style="flex:2; height:50px; font-weight:800;">حفظ ونشر الاختبار</button>
                <button class="sm-btn sm-btn-outline" onclick="this.closest('.sm-modal-overlay').style.display='none'" style="flex:1;">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<!-- QUESTION BANK MODAL -->
<div id="question-bank-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 900px; width: 95%;">
        <div class="sm-modal-header">
            <h3>بنك أسئلة الاختبار: <span id="bank-test-title"></span></h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding: 0;">
            <div style="display: grid; grid-template-columns: 350px 1fr; height: 650px;">
                <!-- Add Question Form -->
                <div style="background: #f8fafc; border-left: 1px solid #e2e8f0; padding: 25px; overflow-y: auto;">
                    <h4 style="margin-top:0;">إضافة سؤال جديد</h4>
                    <form id="add-question-form">
                        <input type="hidden" id="q_test_id">
                        <div class="sm-form-group">
                            <label class="sm-label">نص السؤال:</label>
                            <textarea id="q_text" class="sm-textarea" rows="3" required></textarea>
                        </div>
                        <div class="sm-form-group">
                            <label class="sm-label">نوع السؤال:</label>
                            <select id="q_type" class="sm-select" onchange="smToggleQuestionOptions(this.value)">
                                <option value="mcq">اختيار من متعدد (MCQ)</option>
                                <option value="true_false">صح أو خطأ</option>
                                <option value="short_answer">إجابة قصيرة</option>
                            </select>
                        </div>

                        <div id="mcq-options-container">
                            <label class="sm-label">الخيارات المتاحة:</label>
                            <div style="display:grid; gap:8px; margin-bottom:15px;">
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="0" checked><input type="text" class="sm-input q-opt" placeholder="الخيار الأول"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="1"><input type="text" class="sm-input q-opt" placeholder="الخيار الثاني"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="2"><input type="text" class="sm-input q-opt" placeholder="الخيار الثالث"></div>
                                <div style="display:flex; gap:5px;"><input type="radio" name="correct_mcq" value="3"><input type="text" class="sm-input q-opt" placeholder="الخيار الرابع"></div>
                            </div>
                        </div>

                        <div id="tf-options-container" style="display:none;">
                            <label class="sm-label">الإجابة الصحيحة:</label>
                            <select id="q_correct_tf" class="sm-select">
                                <option value="true">صح</option>
                                <option value="false">خطأ</option>
                            </select>
                        </div>

                        <div id="short-options-container" style="display:none;">
                            <label class="sm-label">الإجابة النموذجية:</label>
                            <input type="text" id="q_correct_short" class="sm-input">
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:15px;">
                            <div class="sm-form-group"><label class="sm-label">النقاط:</label><input type="number" id="q_points" class="sm-input" value="1"></div>
                            <div class="sm-form-group"><label class="sm-label">الصعوبة:</label><select id="q_difficulty" class="sm-select"><option value="easy">سهل</option><option value="medium" selected>متوسط</option><option value="hard">صعب</option></select></div>
                        </div>
                        <div class="sm-form-group"><label class="sm-label">الموضوع / التصنيف:</label><input type="text" id="q_topic" class="sm-input" placeholder="مثال: قوانين النقابة"></div>

                        <button type="submit" class="sm-btn" style="width:100%; margin-top:10px;">إضافة السؤال للبنك</button>
                    </form>
                </div>
                <!-- Questions List -->
                <div style="padding: 25px; overflow-y: auto;">
                    <div id="bank-questions-list">
                        <!-- Questions load here via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ASSIGN TEST MODAL -->
<div id="assign-test-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 500px;">
        <div class="sm-modal-header">
            <h3 id="assign-modal-title">تعيين الاختبار لمستخدمين</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="sm-modal-body">
            <input type="hidden" id="assign_survey_id">
            <div class="sm-form-group">
                <label class="sm-label">اختر المستخدمين (يمكنك اختيار أكثر من واحد):</label>
                <select id="assign_user_ids" class="sm-select" multiple style="height: 200px;">
                    <?php
                    $all_users = get_users(['role__in' => ['sm_member', 'sm_syndicate_member']]);
                    foreach($all_users as $u) {
                        echo "<option value='{$u->ID}'>{$u->display_name} ({$u->user_login})</option>";
                    }
                    ?>
                </select>
            </div>
            <button class="sm-btn" onclick="smSubmitAssignment()" style="width: 100%; margin-top: 20px;">تأكيد التعيين</button>
        </div>
    </div>
</div>

<!-- RESULTS MODAL -->
<div id="survey-results-modal" class="sm-modal-overlay">
    <div class="sm-modal-content" style="max-width: 800px;">
        <div class="sm-modal-header">
            <h3 id="res-modal-title">نتائج الاستطلاع</h3>
            <button class="sm-modal-close" onclick="this.closest('.sm-modal-overlay').style.display='none'">&times;</button>
        </div>
        <div id="survey-results-body" style="max-height: 500px; overflow-y: auto; padding: 20px;">
            <!-- Results will be loaded here -->
        </div>
    </div>
</div>

<script>
function smOpenNewSurveyModal() {
    document.getElementById('survey_id').value = '';
    document.getElementById('survey-modal-title').innerText = 'إعداد اختبار ممارسة مهنية جديد';
    document.getElementById('survey_submit_btn').innerText = 'حفظ ونشر الاختبار';
    document.getElementById('survey_title').value = '';
    document.getElementById('survey_time_limit').value = '30';
    document.getElementById('survey_max_attempts').value = '1';
    document.getElementById('survey_pass_score').value = '50';
    document.getElementById('new-survey-modal').style.display = 'flex';
}

function smOpenEditSurveyModal(s) {
    document.getElementById('survey_id').value = s.id;
    document.getElementById('survey-modal-title').innerText = 'تعديل إعدادات الاختبار: ' + s.title;
    document.getElementById('survey_submit_btn').innerText = 'تحديث إعدادات الاختبار';
    document.getElementById('survey_title').value = s.title;
    document.getElementById('survey_time_limit').value = s.time_limit;
    document.getElementById('survey_max_attempts').value = s.max_attempts;
    document.getElementById('survey_pass_score').value = s.pass_score;
    document.getElementById('survey_specialty').value = s.specialty;
    document.getElementById('survey_test_type').value = s.test_type;
    document.getElementById('survey_recipients').value = s.recipients;
    document.getElementById('new-survey-modal').style.display = 'flex';
}

function smSaveSurvey() {
    const id = document.getElementById('survey_id').value;
    const title = document.getElementById('survey_title').value;
    if (!title) return alert('يرجى إدخال عنوان الاختبار');

    const fd = new FormData();
    fd.append('action', id ? 'sm_update_survey' : 'sm_add_survey');
    if (id) fd.append('id', id);
    fd.append('title', title);
    fd.append('time_limit', document.getElementById('survey_time_limit').value);
    fd.append('max_attempts', document.getElementById('survey_max_attempts').value);
    fd.append('pass_score', document.getElementById('survey_pass_score').value);
    fd.append('specialty', document.getElementById('survey_specialty').value);
    fd.append('test_type', document.getElementById('survey_test_type').value);
    fd.append('recipients', document.getElementById('survey_recipients').value);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم حفظ بيانات الاختبار');
            location.reload();
        } else alert(res.data);
    });
}

function smToggleQuestionOptions(type) {
    document.getElementById('mcq-options-container').style.display = (type === 'mcq') ? 'block' : 'none';
    document.getElementById('tf-options-container').style.display = (type === 'true_false') ? 'block' : 'none';
    document.getElementById('short-options-container').style.display = (type === 'short_answer') ? 'block' : 'none';
}

window.smOpenQuestionBank = function(s) {
    document.getElementById('q_test_id').value = s.id;
    document.getElementById('bank-test-title').innerText = s.title;
    smLoadBankQuestions(s.id);
    document.getElementById('question-bank-modal').style.display = 'flex';
};

function smLoadBankQuestions(testId) {
    const list = document.getElementById('bank-questions-list');
    list.innerHTML = '<p>جاري تحميل الأسئلة...</p>';

    fetch(ajaxurl + '?action=sm_get_test_questions&test_id=' + testId + '&nonce=<?php echo wp_create_nonce("sm_admin_action"); ?>')
    .then(r=>r.json()).then(res => {
        if (!res.success || !res.data) {
            list.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;"><span class="dashicons dashicons-warning" style="font-size:40px; width:40px; height:40px;"></span><p>لا توجد أسئلة مضافة لهذا الاختبار بعد.</p></div>';
            return;
        }
        let html = '<div style="display:grid; gap:15px;">';
        res.data.forEach((q, idx) => {
            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; position:relative; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="position:absolute; left:20px; top:20px; display:flex; gap:10px;">
                        <span class="sm-badge sm-badge-low" style="font-size:10px;">${q.difficulty}</span>
                        <button onclick="smDeleteQuestion(${q.id}, ${testId})" style="border:none; background:none; color:#e53e3e; cursor:pointer;"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                    <div style="font-weight:800; color:var(--sm-dark-color); margin-bottom:10px;">س${idx+1}: ${q.question_text}</div>
                    <div style="font-size:12px; color:#64748b;">النوع: ${q.question_type} | النقاط: ${q.points}</div>
                    <div style="margin-top:10px; padding:10px; background:#f0fff4; border-radius:8px; border:1px solid #c6f6d5; font-size:12px; color:#22543d;">
                        <strong>الإجابة الصحيحة:</strong> ${q.correct_answer}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        list.innerHTML = html;
    });
}

document.getElementById('add-question-form').onsubmit = function(e) {
    e.preventDefault();
    const testId = document.getElementById('q_test_id').value;
    const type = document.getElementById('q_type').value;
    const fd = new FormData();
    fd.append('action', 'sm_add_test_question');
    fd.append('test_id', testId);
    fd.append('question_text', document.getElementById('q_text').value);
    fd.append('question_type', type);
    fd.append('points', document.getElementById('q_points').value);
    fd.append('difficulty', document.getElementById('q_difficulty').value);
    fd.append('topic', document.getElementById('q_topic').value);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    if (type === 'mcq') {
        const opts = Array.from(document.querySelectorAll('.q-opt')).map(i => i.value);
        const correctIdx = document.querySelector('input[name="correct_mcq"]:checked').value;
        fd.append('options', JSON.stringify(opts));
        fd.append('correct_answer', opts[correctIdx]);
    } else if (type === 'true_false') {
        fd.append('correct_answer', document.getElementById('q_correct_tf').value);
    } else {
        fd.append('correct_answer', document.getElementById('q_correct_short').value);
    }

    fetch(ajaxurl, { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.success) {
            smShowNotification('تم إضافة السؤال');
            this.reset();
            smLoadBankQuestions(testId);
        } else alert(res.data);
    });
};

function smDeleteQuestion(id, testId) {
    if (!confirm('حذف هذا السؤال نهائياً؟')) return;
    const fd = new FormData();
    fd.append('action', 'sm_delete_test_question');
    fd.append('id', id);
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.success) smLoadBankQuestions(testId);
    });
}

function smOpenAssignModal(id, title) {
    document.getElementById('assign_survey_id').value = id;
    document.getElementById('assign-modal-title').innerText = 'تعيين الاختبار: ' + title;
    document.getElementById('assign-test-modal').style.display = 'flex';
}

function smSubmitAssignment() {
    const survey_id = document.getElementById('assign_survey_id').value;
    const select = document.getElementById('assign_user_ids');
    const user_ids = Array.from(select.selectedOptions).map(option => option.value);

    if (user_ids.length === 0) {
        alert('يرجى اختيار مستخدم واحد على الأقل');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'sm_assign_test');
    fd.append('survey_id', survey_id);
    user_ids.forEach(id => fd.append('user_ids[]', id));
    fd.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch(ajaxurl, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم تعيين الاختبار بنجاح');
            document.getElementById('assign-test-modal').style.display = 'none';
        } else {
            alert('خطأ: ' + res.data);
        }
    });
}

function smCancelSurvey(id) {
    if (!confirm('هل أنت متأكد من إلغاء هذا الاختبار؟ لن يتمكن أحد من التقديم عليه بعد الآن.')) return;

    const formData = new FormData();
    formData.append('action', 'sm_cancel_survey');
    formData.append('id', id);
    formData.append('nonce', '<?php echo wp_create_nonce("sm_admin_action"); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            smShowNotification('تم إلغاء الاستطلاع');
            location.reload();
        }
    });
}

function smViewSurveyResults(id, title) {
    document.getElementById('res-modal-title').innerText = 'نتائج: ' + title;
    const body = document.getElementById('survey-results-body');
    body.innerHTML = '<p style="text-align:center;">جاري تحميل النتائج...</p>';
    document.getElementById('survey-results-modal').style.display = 'flex';

    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=sm_get_survey_results&id=' + id)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const d = res.data;
            let html = `
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:15px; margin-bottom:30px;">
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">إجمالي المشاركات</div>
                        <div style="font-size:24px; font-weight:900;">${d.stats.total_responses}</div>
                    </div>
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">متوسط الدرجات</div>
                        <div style="font-size:24px; font-weight:900; color:var(--sm-primary-color);">${Math.round(d.stats.avg_score)}%</div>
                    </div>
                    <div style="background:#fff; padding:15px; border-radius:10px; border:1px solid #e2e8f0; text-align:center;">
                        <div style="font-size:11px; color:#64748b;">عدد الناجحين</div>
                        <div style="font-size:24px; font-weight:900; color:#38a169;">${d.stats.pass_count}</div>
                    </div>
                </div>
            `;

            d.questions.forEach(item => {
                html += `<div style="margin-bottom: 25px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 800; margin-bottom: 15px; color: var(--sm-dark-color);">${item.question}</div>
                    <div style="display: grid; gap: 10px;">`;

                for (const [ans, count] of Object.entries(item.answers)) {
                    html += `<div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 8px 15px; border-radius: 5px; border: 1px solid #edf2f7;">
                        <span>${ans}</span>
                        <span style="font-weight: 700; color: var(--sm-primary-color);">${count}</span>
                    </div>`;
                }
                html += `</div></div>`;
            });
            body.innerHTML = html;
        } else {
            body.innerHTML = '<p style="color:red;">فشل تحميل النتائج</p>';
        }
    });
}
</script>
