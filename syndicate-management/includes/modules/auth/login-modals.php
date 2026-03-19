<?php
if (!defined('ABSPATH')) exit;
$syndicate = SM_Settings::get_syndicate_info();
?>
<!-- Recovery Modal -->
<div id="sm-recovery-modal" class="sm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center; padding:20px;">
    <div class="sm-modal-content" style="background:white; width:100%; max-width:400px; padding:35px; border-radius:20px; position:relative;">
        <button onclick="smToggleRecovery()" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
        <h3 style="margin-top:0; margin-bottom:25px; text-align:center; font-weight:800;">استعادة كلمة المرور</h3>
        <div id="recovery-step-1">
            <p style="font-size:14px; color:#64748b; margin-bottom:20px; line-height:1.6;">أدخل الرقم القومي الخاص بك للتحقق وإرسال رمز الاستعادة.</p>
            <div class="sm-form-group" style="margin-bottom:20px;"><label class="sm-label">الرقم القومي:</label><input type="text" id="rec_national_id" class="sm-input" placeholder="14 رقم" maxlength="14" style="width:100%;"></div>
            <button onclick="smRequestOTP()" class="sm-btn" style="width:100%;">إرسال رمز التحقق</button>
        </div>
        <div id="recovery-step-2" style="display:none;">
            <p style="font-size:13px; color:#38a169; margin-bottom:15px;">تم إرسال الرمز بنجاح. يرجى التحقق من بريدك.</p>
            <input type="text" id="rec_otp" class="sm-input" placeholder="رمز التحقق (6 أرقام)" style="margin-bottom:10px; width:100%;">
            <div class="sm-form-group" style="margin-bottom:20px; position:relative;">
                <input type="password" id="rec_new_pass" class="sm-input" placeholder="كلمة المرور الجديدة" style="width:100%;">
                <span class="dashicons dashicons-visibility sm-password-toggle" onclick="smTogglePass('rec_new_pass', this)"></span>
            </div>
            <button onclick="smResetPassword()" class="sm-btn" style="width:100%;">تغيير كلمة المرور</button>
        </div>
    </div>
</div>

<!-- Registration Modal -->
<div id="sm-registration-modal" class="sm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(17,31,53,0.85); z-index:10000; justify-content:center; align-items:center; padding:20px; backdrop-filter: blur(4px);">
    <div class="sm-modal-content" style="background:white; width:100%; max-width:700px; padding:40px; border-radius:24px; position:relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow-y:auto; max-height:90vh;">
        <button onclick="smToggleRegistration()" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8; transition: 0.2s;">&times;</button>
        <?php if (!empty($syndicate['syndicate_logo'])): ?><div style="text-align:center; margin-bottom:15px;"><img src="<?php echo esc_url($syndicate['syndicate_logo']); ?>" style="max-height: 50px;"></div><?php endif; ?>
        <div style="text-align:center; margin-bottom:30px;"><h3 style="margin:0; font-weight:900; font-size:1.5em; color:var(--sm-dark-color);">طلب عضوية جديدة</h3><p id="reg-step-title" style="color:#64748b; font-size:13px; margin-top:5px;">المرحلة الأولى: البيانات الشخصية</p></div>
        <form id="sm-membership-request-form" enctype="multipart/form-data">
            <div class="sm-steps-indicator" style="display:flex; justify-content:center; gap:10px; margin-bottom:30px; flex-wrap:wrap;">
                <?php for($i=1; $i<=5; $i++): $bg = ($i === 1) ? 'var(--sm-primary-color)' : '#edf2f7'; $color = ($i === 1) ? 'white' : '#718096'; ?>
                    <span id="reg-dot-<?php echo $i; ?>" style="width:30px; height:30px; background:<?php echo $bg; ?>; color:<?php echo $color; ?>; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:13px; transition:0.3s;"><?php echo $i; ?></span>
                <?php endfor; ?>
            </div>
            <!-- Step 1: Personal Data -->
            <div id="reg-step-1" class="reg-step">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">الاسم الرباعي الكامل:</label><input name="name" type="text" class="sm-input" required></div>
                    <div class="sm-form-group"><label class="sm-label">الرقم القومي (14 رقم):</label><input name="national_id" type="text" class="sm-input" required maxlength="14"></div>
                    <div class="sm-form-group"><label class="sm-label">رقم الهاتف الجوال:</label><input name="phone" type="text" class="sm-input" required placeholder="01xxxxxxxxx"></div>
                    <div class="sm-form-group"><label class="sm-label">البريد الإلكتروني:</label><input name="email" type="email" class="sm-input" required placeholder="example@domain.com"></div>
                    <div class="sm-form-group"><label class="sm-label">محافظة الإقامة:</label><select name="residence_governorate" class="sm-select" required><option value="">-- اختر --</option><?php foreach(SM_Settings::get_governorates() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">مدينة الإقامة:</label><input name="residence_city" type="text" class="sm-input" required></div>
                    <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">الشارع / القرية:</label><input name="residence_street" type="text" class="sm-input" required></div>
                </div>
                <button type="button" onclick="smRegNext(2)" class="sm-btn" style="width:100%; margin-top:10px;">التالي: البيانات الأكاديمية</button>
            </div>
            <!-- Step 2: Academic Data -->
            <div id="reg-step-2" class="reg-step" style="display:none;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="sm-form-group"><label class="sm-label">الجامعة:</label><select id="reg_university" name="university" class="sm-select academic-cascading" required><option value="">-- اختر الجامعة --</option><?php foreach(SM_Settings::get_universities() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">الكلية:</label><select id="reg_faculty" name="faculty" class="sm-select academic-cascading" required disabled><option value="">-- اختر الكلية --</option><?php foreach(SM_Settings::get_faculties() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">القسم:</label><select id="reg_department" name="department" class="sm-select academic-cascading" required disabled><option value="">-- اختر القسم --</option><?php foreach(SM_Settings::get_departments() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">التخصص:</label><select id="reg_specialization" name="specialization" class="sm-select academic-cascading" required disabled><option value="">-- اختر التخصص --</option><?php foreach(SM_Settings::get_specializations() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group"><label class="sm-label">تاريخ التخرج:</label><input name="graduation_date" type="date" class="sm-input" required></div>
                    <div class="sm-form-group"><label class="sm-label">الدرجة العلمية:</label><select name="academic_degree" class="sm-select" required><?php foreach(SM_Settings::get_academic_degrees() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px; margin-top:10px;">
                    <button type="button" onclick="smRegNext(1)" class="sm-btn sm-btn-outline">السابق</button>
                    <button type="button" onclick="smRegNext(3)" class="sm-btn">التالي: البيانات المهنية</button>
                </div>
            </div>
            <!-- Step 3: Professional Data -->
            <div id="reg-step-3" class="reg-step" style="display:none;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="sm-form-group"><label class="sm-label">الدرجة الوظيفية المستهدفة:</label><select name="professional_grade" class="sm-select" required><?php foreach(SM_Settings::get_professional_grades() as $k=>$v) echo "<option value='$k'>$v</option>"; ?></select></div>
                    <div class="sm-form-group">
                        <label class="sm-label">لجنة النقابة التابع لها:</label>
                        <select name="governorate" class="sm-select" required>
                            <option value="">-- اختر الفرع --</option>
                            <?php
                            $dynamic_branches = SM_DB::get_branches_data();
                            if (!empty($dynamic_branches)):
                                foreach($dynamic_branches as $db): echo "<option value='".esc_attr($db->slug)."'>".esc_html($db->name)."</option>"; endforeach;
                            else:
                                foreach(SM_Settings::get_governorates() as $k=>$v) echo "<option value='$k'>$v</option>";
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="sm-form-group" style="grid-column: span 2;"><label class="sm-label">النقابة الفرعية / اللجنة النوعية:</label><input name="sub_syndicate" type="text" class="sm-input" placeholder="مثال: نقابة المعلمين بوسط القاهرة"></div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px; margin-top:10px;">
                    <button type="button" onclick="smRegNext(2)" class="sm-btn sm-btn-outline">السابق</button>
                    <button type="button" onclick="smRegNext(4)" class="sm-btn">التالي: مرحلة السداد</button>
                </div>
            </div>
            <!-- Step 4: Payment Stage -->
            <div id="reg-step-4" class="reg-step" style="display:none;">
                <div style="text-align:center; padding:40px; border:2px dashed #e2e8f0; border-radius:15px; color:#94a3b8; margin-bottom:20px;"><span class="dashicons dashicons-cart" style="font-size:40px; width:40px; height:40px; margin-bottom:10px;"></span><p>مرحلة السداد الإلكتروني قيد التطوير البرمجي حالياً.</p></div>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                    <button type="button" onclick="smRegNext(3)" class="sm-btn sm-btn-outline">السابق</button>
                    <button type="button" onclick="smRegNext(5)" class="sm-btn">التالي: شحن الوثائق</button>
                </div>
            </div>
            <!-- Step 5: Document Submission -->
            <div id="reg-step-5" class="reg-step" style="display:none;">
                <div style="background: #fff5f5; padding: 25px; border-radius: 15px; border: 1px solid #feb2b2; margin-bottom: 25px;"><h4 style="margin: 0 0 10px 0; color: #c53030; font-weight: 800;">تعليمات إرسال الأصول الورقية</h4><p style="font-size: 13px; color: #7b2c2c; line-height: 1.6; margin: 0;">لإتمام عملية القيد، يتوجب عليك شحن أصول المستندات (المؤهل، فيش جنائي، صور شخصية، إلخ) عبر خدمة شحن معتمدة إلى المقر الرئيسي للنقابة.</p></div>
                <div style="background: #f8fafc; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                    <div style="margin-bottom: 10px;"><strong>العنوان:</strong> <?php echo esc_html($syndicate['address']); ?></div>
                    <div style="margin-bottom: 10px;"><strong>رقم الهاتف:</strong> <?php echo esc_html($syndicate['phone']); ?></div>
                    <?php if (!empty($syndicate['postal_code'])): ?><div style="margin-bottom: 10px;"><strong>الرمز البريدي:</strong> <?php echo esc_html($syndicate['postal_code']); ?></div><?php endif; ?>
                </div>
                <div style="background: #fffaf0; padding: 15px; border-radius: 10px; border: 1px solid #feebc8; font-size: 12px; color: #9c4221; line-height: 1.6; margin-bottom: 25px;">بمجرد إرسال طلبك، سيتم تزويدك بكود تتبع إلكتروني لمتابعة حالة طلبك.</div>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                    <button type="button" onclick="smRegNext(4)" class="sm-btn sm-btn-outline">السابق</button>
                    <button type="submit" class="sm-btn" style="background:var(--sm-dark-color);">إرسال الطلب النهائي</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activation Modal -->
<div id="sm-activation-modal" class="sm-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center; padding:20px;">
    <div class="sm-modal-content" style="background:white; width:100%; max-width:450px; padding:40px; border-radius:24px; position:relative;">
        <button onclick="smToggleActivation()" style="position:absolute; top:20px; left:20px; border:none; background:none; font-size:24px; cursor:pointer; color:#94a3b8;">&times;</button>
        <div style="text-align:center; margin-bottom:30px;"><h3 style="margin:0; font-weight:900;">تفعيل الحساب الرقمي</h3><p style="color:#64748b; font-size:13px; margin-top:5px;">خطوات بسيطة للوصول لخدماتك الإلكترونية</p></div>

        <div class="sm-steps-indicator" style="display:flex; justify-content:center; gap:10px; margin-bottom:25px;">
            <?php for($i=1; $i<=4; $i++): ?>
                <span id="act-dot-<?php echo $i; ?>" style="width:30px; height:30px; background:#edf2f7; color:#718096; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:13px; transition:0.3s;"><?php echo $i; ?></span>
            <?php endfor; ?>
        </div>

        <div id="activation-step-1">
            <p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الأولى: اختر فرع النقابة التابع له</p>
            <div class="sm-form-group" style="margin-bottom:20px;">
                <select id="act_branch" class="sm-select" style="width:100%;">
                    <option value="">-- اختر الفرع --</option>
                    <?php
                    $active_branches = SM_DB::get_branches_data();
                    foreach($active_branches as $b) echo "<option value='".esc_attr($b->slug)."'>".esc_html($b->name)."</option>";
                    ?>
                </select>
            </div>
            <button onclick="smActivateGoTo(2)" class="sm-btn" style="width:100%;">التالي: التحقق من الهوية</button>
        </div>

        <div id="activation-step-2" style="display:none;">
            <p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الثانية: التحقق من الهوية بالسجلات</p>
            <div class="sm-form-group" style="margin-bottom:15px;"><input type="text" id="act_national_id" class="sm-input" placeholder="الرقم القومي (14 رقم)" style="width:100%;"></div>
            <div class="sm-form-group" style="margin-bottom:15px;"><input type="text" id="act_mem_no" class="sm-input" placeholder="رقم القيد النقابي" style="width:100%;"></div>
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                <button onclick="smActivateGoTo(1)" class="sm-btn sm-btn-outline">السابق</button>
                <button onclick="smActivateStep2Check()" class="sm-btn">تحقق والتالي</button>
            </div>
        </div>

        <div id="activation-step-3" style="display:none;">
            <p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الثالثة: تأكيد بيانات التواصل</p>
            <div class="sm-form-group" style="margin-bottom:15px;"><input type="email" id="act_email" class="sm-input" placeholder="البريد الإلكتروني المعتمد" style="width:100%;"></div>
            <div class="sm-form-group" style="margin-bottom:15px;"><input type="text" id="act_phone" class="sm-input" placeholder="رقم الهاتف الحالي" style="width:100%;"></div>
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                <button onclick="smActivateGoTo(2)" class="sm-btn sm-btn-outline">السابق</button>
                <button onclick="smActivateGoTo(4)" class="sm-btn">تأكيد البيانات</button>
            </div>
        </div>

        <div id="activation-step-4" style="display:none;">
            <p style="font-size:14px; color:#4a5568; margin-bottom:20px; text-align:center;">المرحلة الرابعة: تعيين كلمة المرور</p>
            <div class="sm-form-group" style="margin-bottom:20px; position:relative;"><input type="password" id="act_pass" class="sm-input" placeholder="كلمة المرور (10 خانات على الأقل)" style="width:100%;"><span class="dashicons dashicons-visibility sm-password-toggle" onclick="smTogglePass('act_pass', this)"></span></div>
            <div style="display:grid; grid-template-columns: 1fr 2fr; gap:10px;">
                <button onclick="smActivateGoTo(3)" class="sm-btn sm-btn-outline">السابق</button>
                <button onclick="smActivateFinal()" class="sm-btn">إكمال التنشيط والدخول</button>
            </div>
        </div>
    </div>
</div>

<script>
function smTogglePass(id, btn) {
    const input = document.getElementById(id);
    if (input.type === "password") { input.type = "text"; btn.classList.replace("dashicons-visibility", "dashicons-hidden"); }
    else { input.type = "password"; btn.classList.replace("dashicons-hidden", "dashicons-visibility"); }
}
function smToggleRecovery() { const m = document.getElementById("sm-recovery-modal"); m.style.display = m.style.display === "none" ? "flex" : "none"; }
function smToggleActivation() { const m = document.getElementById("sm-activation-modal"); m.style.display = m.style.display === "none" ? "flex" : "none"; if(m.style.display==="flex") smActivateGoTo(1); }
function smActivateGoTo(step) {
    if (step === 2) { if (!document.getElementById("act_branch").value) return alert("يرجى اختيار الفرع أولاً."); }
    if (step === 4) {
        const email = document.getElementById("act_email").value;
        const phone = document.getElementById("act_phone").value;
        if(!/^\S+@\S+\.\S+$/.test(email)) return alert("يرجى إدخال بريد إلكتروني صحيح");
        if(phone.length < 10) return alert("يرجى إدخال رقم هاتف صحيح");
    }
    document.querySelectorAll("[id^='activation-step-']").forEach(s => s.style.display = "none");
    document.getElementById("activation-step-" + step).style.display = "block";
    for (let i = 1; i <= 4; i++) {
        const dot = document.getElementById("act-dot-" + i);
        if (i < step) { dot.style.background = "#38a169"; dot.style.color = "white"; dot.innerText = "✓"; }
        else if (i === step) { dot.style.background = "var(--sm-primary-color)"; dot.style.color = "white"; dot.innerText = i; }
        else { dot.style.background = "#edf2f7"; dot.style.color = "#718096"; dot.innerText = i; }
    }
}
function smActivateStep2Check() {
    const nid = document.getElementById("act_national_id").value;
    const mem = document.getElementById("act_mem_no").value;
    const branch = document.getElementById("act_branch").value;
    if(!/^[0-9]{14}$/.test(nid)) return alert("يرجى إدخال رقم قومي صحيح (14 رقم)");
    const fd = new FormData();
    fd.append("action", "sm_activate_account_step1");
    fd.append("national_id", nid);
    fd.append("membership_number", mem);
    fd.append("branch", branch);
    fetch(ajaxurl, {method:"POST", body:fd}).then(r=>r.json()).then(res=>{
        if(res.success) smActivateGoTo(3);
        else alert(res.data);
    });
}
function smToggleRegistration() { const m = document.getElementById("sm-registration-modal"); const isClosing = m.style.display !== "none"; m.style.display = isClosing ? "none" : "flex"; if (!isClosing) { smRegNext(1); document.getElementById("sm-membership-request-form").reset(); } }
document.querySelectorAll(".academic-cascading").forEach((el, idx, arr) => { el.addEventListener("change", function() { if (this.value && idx < arr.length - 1) { arr[idx + 1].disabled = false; } else if (!this.value) { for (let i = idx + 1; i < arr.length; i++) { arr[i].value = ""; arr[i].disabled = true; } } }); });
function smRegNext(step) {
    if (step > 1) { const prevStep = step - 1; const prevDiv = document.getElementById("reg-step-" + prevStep); const inputs = prevDiv.querySelectorAll("input[required], select[required]"); for (const input of inputs) { if (!input.value) return alert("يرجى ملء كافة الحقول المطلوبة للمتابعة."); } if (prevStep === 1) { const nid = prevDiv.querySelector("[name=\"national_id\"]").value; if (nid.length !== 14) return alert("الرقم القومي يجب أن يتكون من 14 رقم."); } }
    const titles = ["البيانات الشخصية", "البيانات الأكاديمية", "البيانات المهنية", "مرحلة السداد", "شحن الوثائق"];
    document.getElementById("reg-step-title").innerText = "المرحلة " + (["الأولى", "الثانية", "الثالثة", "الرابعة", "الخامسة"][step-1]) + ": " + titles[step-1];
    document.querySelectorAll(".reg-step").forEach(s => s.style.display = "none");
    document.getElementById("reg-step-" + step).style.display = "block";
    for (let i = 1; i <= 5; i++) { const dot = document.getElementById("reg-dot-" + i); if (!dot) continue; if (i < step) { dot.style.background = "#38a169"; dot.style.color = "white"; dot.innerText = "✓"; } else if (i === step) { dot.style.background = "var(--sm-primary-color)"; dot.style.color = "white"; dot.innerText = i; } else { dot.style.background = "#edf2f7"; dot.style.color = "#718096"; dot.innerText = i; } }
}
function smRequestOTP() { const nid = document.getElementById("rec_national_id").value; const fd = new FormData(); fd.append("action", "sm_forgot_password_otp"); fd.append("national_id", nid); fetch(ajaxurl, {method:"POST", body:fd}).then(r=>r.json()).then(res=>{ if(res.success) { document.getElementById("recovery-step-1").style.display="none"; document.getElementById("recovery-step-2").style.display="block"; } else alert(res.data); }); }
function smActivateStep2() { const email = document.getElementById("act_email").value; const phone = document.getElementById("act_phone").value; if(!/^\S+@\S+\.\S+$/.test(email)) return alert("يرجى إدخال بريد إلكتروني صحيح"); if(phone.length < 10) return alert("يرجى إدخال رقم هاتف صحيح"); document.getElementById("activation-step-2").style.display="none"; document.getElementById("activation-step-3").style.display="block"; }
function smResetPassword() { const nid = document.getElementById("rec_national_id").value; const otp = document.getElementById("rec_otp").value; const pass = document.getElementById("rec_new_pass").value; const fd = new FormData(); fd.append("action", "sm_reset_password_otp"); fd.append("national_id", nid); fd.append("otp", otp); fd.append("new_password", pass); fetch(ajaxurl, {method:"POST", body:fd}).then(r=>r.json()).then(res=>{ if(res.success) { alert(res.data); location.reload(); } else alert(res.data); }); }
function smActivateStep1() { const nid = document.getElementById("act_national_id").value; const mem = document.getElementById("act_mem_no").value; if(!/^[0-9]{14}$/.test(nid)) return alert("يرجى إدخال رقم قومي صحيح (14 رقم)"); const fd = new FormData(); fd.append("action", "sm_activate_account_step1"); fd.append("national_id", nid); fd.append("membership_number", mem); fetch(ajaxurl, {method:"POST", body:fd}).then(r=>r.json()).then(res=>{ if(res.success) { document.getElementById("activation-step-1").style.display="none"; document.getElementById("activation-step-2").style.display="block"; } else alert(res.data); }); }
function smActivateFinal() { const nid = document.getElementById("act_national_id").value; const mem = document.getElementById("act_mem_no").value; const email = document.getElementById("act_email").value; const phone = document.getElementById("act_phone").value; const pass = document.getElementById("act_pass").value; if(!/^\S+@\S+\.\S+$/.test(email)) return alert("يرجى إدخال بريد إلكتروني صحيح"); if(pass.length < 10) return alert("كلمة المرور يجب أن تكون 10 أحرف على الأقل"); const fd = new FormData(); fd.append("action", "sm_activate_account_final"); fd.append("national_id", nid); fd.append("membership_number", mem); fd.append("email", email); fd.append("phone", phone); fd.append("password", pass); fetch(ajaxurl, {method:"POST", body:fd}).then(r=>r.json()).then(res=>{ if(res.success) { alert(res.data); location.reload(); } else alert(res.data); }); }
document.getElementById("sm-membership-request-form")?.addEventListener("submit", function(e) { e.preventDefault(); const fd = new FormData(this); fd.append("action", "sm_submit_membership_request"); fd.append("nonce", "<?php echo wp_create_nonce('sm_registration_nonce'); ?>"); const nid = fd.get("national_id"); if(!/^[0-9]{14}$/.test(nid)) return alert("الرقم القومي يجب أن يتكون من 14 رقم."); const btn = e.submitter || this.querySelector("button[type=\"submit\"]"); const originalText = btn.innerText; btn.disabled = true; btn.innerText = "جاري الحفظ..."; fetch(ajaxurl, {method:"POST", body:fd}).then(r=>r.json()).then(res=>{ btn.disabled = false; btn.innerText = originalText; if(res.success) { const trackingCode = res.data; document.getElementById("sm-registration-modal").querySelector(".sm-modal-content").innerHTML = `<div style="text-align:center; padding:40px;"><div style="font-size:60px; margin-bottom:20px;">✅</div><h3 style="font-weight:900; font-size:1.8em; margin:0 0 10px 0;">تم تسجيل طلبك بنجاح!</h3><p style="color:#64748b; line-height:1.6; margin-bottom:25px;">يرجى تصوير كود التتبع التالي لمتابعة طلبك:</p><div style="background:#f8fafc; border:2px dashed var(--sm-primary-color); padding:20px; font-size:28px; font-weight:900; color:var(--sm-primary-color); border-radius:15px; margin-bottom:30px; letter-spacing:2px;">${trackingCode}</div><button onclick="location.reload()" class="sm-btn" style="width:100%; height:50px; font-weight:800;">إغلاق والعودة</button></div>`; } else alert(res.data); }); });
</script>
