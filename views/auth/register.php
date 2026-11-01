<?php
require BASE_PATH . '/views/layout/header.php';

$v   = fn(string $k): string => e($old[$k] ?? '');
$inv = fn(string $k): string => isset($errors[$k]) ? ' is-invalid' : '';
$fe  = fn(string $k): string => '<div class="field-error" data-error-for="' . e($k) . '"' . (isset($errors[$k]) ? '' : ' hidden')
    . '><i class="bi bi-exclamation-circle"></i><span>' . e($errors[$k] ?? '') . '</span></div>';
$stepTitles = ['Компания', 'Контактное лицо', 'Доступ'];
$stepClass = fn(int $i): string => $startStep === $i ? ' active' : '';
?>

<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="auth-shell row g-0">

            <aside class="col-lg-4 auth-aside d-none d-lg-flex flex-column">
                <div class="mb-auto">
                    <div class="auth-badge"><i class="bi bi-buildings"></i></div>
                    <h3 class="mt-4 mb-2">Регистрация компании</h3>
                    <p class="opacity-75 mb-0">Выходите на рынок оптовых поставок за три простых шага.</p>

                    <div class="perk"><i class="bi bi-grid"></i><div><strong>Каталог поставщиков</strong><br><span class="opacity-75 small">Ищите товары и услуги по категориям</span></div></div>
                    <div class="perk"><i class="bi bi-box-seam"></i><div><strong>Свои товары</strong><br><span class="opacity-75 small">Размещайте предложения для других компаний</span></div></div>
                    <div class="perk"><i class="bi bi-file-earmark-text"></i><div><strong>Сделки в кабинете</strong><br><span class="opacity-75 small">Статусы и история в одном месте</span></div></div>
                </div>
                <div class="pt-4">
                    <span class="opacity-75 small d-block mb-2">Уже зарегистрированы?</span>
                    <a href="/login" class="btn btn-outline-light btn-sm px-3">Войти</a>
                </div>
            </aside>

            <div class="col-lg-8 auth-main">
                <div class="nav nav-pills mb-4">
                    <a class="nav-link active" href="/register">Регистрация компании</a>
                    <a class="nav-link" href="/join">Я сотрудник — по коду</a>
                </div>

                <h3 class="d-lg-none mb-3">Регистрация компании</h3>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i><?= e($errorMsg) ?></div>
                <?php elseif ($errors): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Проверьте форму: ошибки на шагах —
                        <?= e(implode(', ', array_map(fn($i) => ($i + 1) . '. ' . $stepTitles[$i], $errorSteps))) ?>.
                    </div>
                <?php endif; ?>

                <div id="wizard">
                    <script>document.getElementById('wizard').classList.add('wizard-js');</script>

                    <div class="stepper" id="stepper">
                        <?php foreach ($stepTitles as $i => $title): ?>
                            <?php if ($i > 0): ?><div class="stepper-line"></div><?php endif; ?>
                            <button type="button" class="stepper-item" data-i="<?= $i ?>">
                                <span class="dot"><?= $i + 1 ?></span><span class="label"><?= e($title) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" action="/register" id="regForm" novalidate autocomplete="on">
                        <?= csrfField() ?>

                        <!-- Шаг 1 -->
                        <section class="wizard-step<?= $stepClass(0) ?>" data-step="0">
                            <h4 class="mb-1">Данные компании</h4>
                            <p class="text-muted">Эти сведения увидят партнёры по сделкам.</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="company_name">Название компании <span class="text-danger">*</span></label>
                                    <input type="text" id="company_name" name="company_name" class="form-control<?= $inv('company_name') ?>"
                                           maxlength="150" placeholder='ООО "Ромашка"' value="<?= $v('company_name') ?>" autocomplete="organization">
                                    <?= $fe('company_name') ?>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="inn">ИНН <span class="text-danger">*</span></label>
                                    <input type="text" id="inn" name="inn" class="form-control<?= $inv('inn') ?>"
                                           inputmode="numeric" maxlength="12" placeholder="7700000000" value="<?= $v('inn') ?>">
                                    <div class="form-text" id="innHelp">10 цифр (организация) или 12 (ИП)</div>
                                    <?= $fe('inn') ?>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label" for="company_phone">Телефон компании <span class="text-danger">*</span></label>
                                    <input type="tel" id="company_phone" name="company_phone" class="form-control<?= $inv('company_phone') ?>"
                                           maxlength="30" placeholder="+7 900 000-00-00" value="<?= $v('company_phone') ?>" autocomplete="tel">
                                    <?= $fe('company_phone') ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="legal_address">Юридический адрес <span class="text-danger">*</span></label>
                                    <input type="text" id="legal_address" name="legal_address" class="form-control<?= $inv('legal_address') ?>"
                                           maxlength="255" placeholder="г. Москва, ул. Примерная, д. 1" value="<?= $v('legal_address') ?>" autocomplete="street-address">
                                    <?= $fe('legal_address') ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="description">О компании <span class="text-muted small">(необязательно)</span></label>
                                    <textarea id="description" name="description" class="form-control<?= $inv('description') ?>" rows="3"
                                              maxlength="2000" placeholder="Чем занимается компания, что поставляет"><?= $v('description') ?></textarea>
                                    <?= $fe('description') ?>
                                </div>
                            </div>
                        </section>

                        <!-- Шаг 2 -->
                        <section class="wizard-step<?= $stepClass(1) ?>" data-step="1">
                            <h4 class="mb-1">Контактное лицо</h4>
                            <p class="text-muted">Человек, который будет работать в личном кабинете.</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="contact_person">ФИО <span class="text-danger">*</span></label>
                                    <input type="text" id="contact_person" name="contact_person" class="form-control<?= $inv('contact_person') ?>"
                                           maxlength="150" placeholder="Иванов Иван Иванович" value="<?= $v('contact_person') ?>" autocomplete="name">
                                    <?= $fe('contact_person') ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="position">Должность <span class="text-danger">*</span></label>
                                    <input type="text" id="position" name="position" class="form-control<?= $inv('position') ?>"
                                           maxlength="100" placeholder="Менеджер по закупкам" value="<?= $v('position') ?>" autocomplete="organization-title">
                                    <?= $fe('position') ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="phone">Личный телефон <span class="text-danger">*</span></label>
                                    <input type="tel" id="phone" name="phone" class="form-control<?= $inv('phone') ?>"
                                           maxlength="30" placeholder="+7 900 000-00-00" value="<?= $v('phone') ?>">
                                    <?= $fe('phone') ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control<?= $inv('email') ?>"
                                           maxlength="100" placeholder="name@company.ru" value="<?= $v('email') ?>" autocomplete="email">
                                    <div class="form-text">Будет использоваться для входа в систему</div>
                                    <?= $fe('email') ?>
                                </div>
                            </div>
                        </section>

                        <!-- Шаг 3 -->
                        <section class="wizard-step<?= $stepClass(2) ?>" data-step="2">
                            <h4 class="mb-1">Доступ к аккаунту</h4>
                            <p class="text-muted">Придумайте логин и надёжный пароль.</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="username">Логин <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control<?= $inv('username') ?>"
                                           maxlength="50" placeholder="ivanov_company" value="<?= $v('username') ?>" autocomplete="username">
                                    <div class="form-text">Латинские буквы, цифры, символы _ . -</div>
                                    <?= $fe('username') ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Пароль <span class="text-danger">*</span></label>
                                    <div class="pass-wrap">
                                        <input type="password" id="password" name="password" class="form-control<?= $inv('password') ?>"
                                               autocomplete="new-password">
                                        <button type="button" class="pass-toggle" data-target="password" aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="strength mt-2"><span id="strengthBar"></span></div>
                                    <div class="form-text" id="strengthText">Минимум 8 символов, буквы и цифры</div>
                                    <?= $fe('password') ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirm">Повторите пароль <span class="text-danger">*</span></label>
                                    <div class="pass-wrap">
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-control<?= $inv('password_confirm') ?>"
                                               autocomplete="new-password">
                                        <button type="button" class="pass-toggle" data-target="password_confirm" aria-label="Показать пароль"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <?= $fe('password_confirm') ?>
                                </div>
                                <div class="col-12">
                                    <div class="summary-box" id="summaryBox" hidden>
                                        <div class="small text-muted mb-1">Проверьте данные</div>
                                        <div class="fw-semibold" id="sumCompany"></div>
                                        <div class="small" id="sumInn"></div>
                                        <div class="small" id="sumPerson"></div>
                                        <div class="small" id="sumEmail"></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="wizard-nav mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-outline-secondary btn-lg" id="btnPrev" hidden>
                                <i class="bi bi-arrow-left me-1"></i>Назад
                            </button>
                            <div class="wizard-nav-main">
                                <button type="button" class="btn btn-brand btn-lg" id="btnNext" hidden>
                                    Далее<i class="bi bi-arrow-right ms-1"></i>
                                </button>
                                <button type="submit" class="btn btn-brand btn-lg" id="btnSubmit">
                                    <i class="bi bi-check2-circle me-1"></i>Зарегистрироваться
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="mt-4 text-center d-lg-none">
                    <a href="/login">Уже есть аккаунт? Войти</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('regForm');
    var steps = [].slice.call(form.querySelectorAll('.wizard-step'));
    var items = [].slice.call(document.querySelectorAll('.stepper-item'));
    var lines = [].slice.call(document.querySelectorAll('.stepper-line'));
    var btnPrev = document.getElementById('btnPrev');
    var btnNext = document.getElementById('btnNext');
    var btnSubmit = document.getElementById('btnSubmit');
    var current = <?= (int)$startStep ?>;
    var stepErrors = {};
    (<?= json_encode($errorSteps) ?>).forEach(function (i) { stepErrors[i] = true; });

    function digits(v) { return v.replace(/\D/g, ''); }
    function phoneRule(v) {
        v = v.trim();
        if (!v) return 'Укажите номер телефона.';
        var d = digits(v).length;
        if (d < 10 || d > 15 || v.length > 30 || /[^\d\s()+\-]/.test(v)) return 'Введите корректный номер, например +7 900 000-00-00.';
        return '';
    }
    var rules = {
        company_name: function (v) {
            v = v.trim();
            if (!v) return 'Укажите название компании.';
            if (v.length < 2) return 'Слишком короткое название.';
            if (v.length > 150) return 'Название не должно быть длиннее 150 символов.';
            return '';
        },
        inn: function (v) {
            v = v.replace(/\s/g, '');
            if (!v) return 'Укажите ИНН.';
            if (/\D/.test(v)) return 'ИНН состоит только из цифр.';
            if (v.length !== 10 && v.length !== 12) return 'ИНН — 10 цифр (организация) или 12 (ИП), сейчас: ' + v.length + '.';
            return '';
        },
        legal_address: function (v) {
            v = v.trim();
            if (!v) return 'Укажите юридический адрес.';
            if (v.length < 5) return 'Адрес слишком короткий.';
            if (v.length > 255) return 'Адрес не должен быть длиннее 255 символов.';
            return '';
        },
        company_phone: phoneRule,
        description: function (v) { return v.length > 2000 ? 'Описание не должно быть длиннее 2000 символов.' : ''; },
        contact_person: function (v) {
            v = v.trim();
            if (!v) return 'Укажите ФИО контактного лица.';
            if (v.split(/\s+/).length < 2) return 'Укажите фамилию и имя.';
            if (v.length > 150) return 'ФИО не должно быть длиннее 150 символов.';
            return '';
        },
        position: function (v) {
            v = v.trim();
            if (!v) return 'Укажите должность.';
            if (v.length > 100) return 'Должность не должна быть длиннее 100 символов.';
            return '';
        },
        phone: phoneRule,
        email: function (v) {
            v = v.trim();
            if (!v) return 'Укажите email.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Некорректный email, например name@company.ru.';
            if (v.length > 100) return 'Email не должен быть длиннее 100 символов.';
            return '';
        },
        username: function (v) {
            v = v.trim();
            if (!v) return 'Придумайте логин.';
            if (v.length < 3) return 'Логин — минимум 3 символа.';
            if (v.length > 50) return 'Логин не должен быть длиннее 50 символов.';
            if (!/^[A-Za-z0-9_.\-]+$/.test(v)) return 'Только латинские буквы, цифры и символы _ . -';
            return '';
        },
        password: function (v) {
            if (!v) return 'Введите пароль.';
            if (v.length < 8) return 'Пароль — минимум 8 символов.';
            if (v.length > 72) return 'Пароль не должен быть длиннее 72 символов.';
            if (!/[A-Za-z]/.test(v) || !/\d/.test(v)) return 'Нужна хотя бы одна буква и одна цифра.';
            return '';
        },
        password_confirm: function (v) {
            if (!v) return 'Повторите пароль.';
            if (v !== form.elements['password'].value) return 'Пароли не совпадают.';
            return '';
        }
    };

    function showError(input, msg) {
        var box = form.querySelector('[data-error-for="' + input.name + '"]');
        if (msg) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            if (box) { box.querySelector('span').textContent = msg; box.hidden = false; }
        } else {
            input.classList.remove('is-invalid');
            if (box) { box.hidden = true; }
            if (input.value.trim() !== '') { input.classList.add('is-valid'); } else { input.classList.remove('is-valid'); }
        }
    }
    function check(input) {
        var rule = rules[input.name];
        var msg = rule ? rule(input.value) : '';
        showError(input, msg);
        return msg;
    }
    function stepInputs(i) {
        return [].slice.call(steps[i].querySelectorAll('input[name], textarea[name]'));
    }
    function validateStep(i) {
        var first = null;
        stepInputs(i).forEach(function (inp) {
            if (check(inp) && !first) first = inp;
        });
        return first;
    }

    function renderStepper() {
        items.forEach(function (it, j) {
            var dot = it.querySelector('.dot');
            it.classList.remove('active', 'done', 'error');
            if (j === current) {
                it.classList.add('active');
                dot.textContent = j + 1;
            } else if (stepErrors[j]) {
                it.classList.add('error');
                dot.innerHTML = '<i class="bi bi-exclamation-lg"></i>';
            } else if (j < current) {
                it.classList.add('done');
                dot.innerHTML = '<i class="bi bi-check-lg"></i>';
            } else {
                dot.textContent = j + 1;
            }
        });
        lines.forEach(function (ln, j) { ln.classList.toggle('filled', j < current); });
    }
    function fillSummary() {
        var f = form.elements;
        document.getElementById('sumCompany').textContent = f['company_name'].value.trim();
        document.getElementById('sumInn').textContent = 'ИНН ' + f['inn'].value.replace(/\s/g, '');
        document.getElementById('sumPerson').textContent = f['contact_person'].value.trim() + ' · ' + f['position'].value.trim();
        document.getElementById('sumEmail').textContent = f['email'].value.trim();
        document.getElementById('summaryBox').hidden = false;
    }
    function goTo(i, focus) {
        current = i;
        steps.forEach(function (s, j) { s.classList.toggle('active', j === i); });
        btnPrev.hidden = i === 0;
        btnNext.hidden = i === steps.length - 1;
        btnSubmit.hidden = i !== steps.length - 1;
        if (i === steps.length - 1) fillSummary();
        renderStepper();
        if (focus) {
            var inp = stepInputs(i)[0];
            if (inp) inp.focus({ preventScroll: true });
        }
    }

    btnNext.addEventListener('click', function () {
        var bad = validateStep(current);
        if (bad) {
            stepErrors[current] = true;
            renderStepper();
            bad.focus();
            return;
        }
        stepErrors[current] = false;
        goTo(current + 1, true);
    });
    btnPrev.addEventListener('click', function () { goTo(current - 1, true); });
    items.forEach(function (it, j) {
        it.addEventListener('click', function () { if (j < current) goTo(j, true); });
    });

    form.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && ev.target.tagName !== 'TEXTAREA' && current < steps.length - 1) {
            ev.preventDefault();
            btnNext.click();
        }
    });
    form.addEventListener('submit', function (ev) {
        var firstStep = -1, firstInput = null;
        steps.forEach(function (s, i) {
            var bad = validateStep(i);
            stepErrors[i] = !!bad;
            if (bad && firstStep === -1) { firstStep = i; firstInput = bad; }
        });
        if (firstStep !== -1) {
            ev.preventDefault();
            goTo(firstStep, false);
            firstInput.focus();
        }
    });

    form.addEventListener('focusout', function (ev) {
        var t = ev.target;
        if (rules[t.name] && (t.value !== '' || t.classList.contains('is-invalid'))) check(t);
    });
    form.addEventListener('input', function (ev) {
        var t = ev.target;
        if (t.classList.contains('is-invalid')) check(t);
        if (t.name === 'password') {
            updateStrength(t.value);
            var c = form.elements['password_confirm'];
            if (c.value !== '') check(c);
        }
        if (t.name === 'inn') {
            var n = t.value.replace(/\s/g, '').length;
            document.getElementById('innHelp').textContent = n ? 'Введено цифр: ' + n + ' (нужно 10 или 12)' : '10 цифр (организация) или 12 (ИП)';
        }
    });

    var strengthColors = ['#dc3545', '#dc3545', '#f0ad4e', '#7cb342', '#2e9e5b'];
    var strengthLabels = ['Очень слабый пароль', 'Слабый пароль', 'Средний пароль', 'Хороший пароль', 'Надёжный пароль'];
    function updateStrength(p) {
        var bar = document.getElementById('strengthBar');
        var txt = document.getElementById('strengthText');
        if (!p) {
            bar.style.width = '0';
            txt.textContent = 'Минимум 8 символов, буквы и цифры';
            return;
        }
        var s = 0;
        if (p.length >= 8) s++;
        if (p.length >= 12) s++;
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
        if (/\d/.test(p)) s++;
        if (/[^A-Za-z0-9]/.test(p)) s++;
        s = Math.min(s, 4);
        bar.style.width = (Math.max(s, 1) * 25) + '%';
        bar.style.background = strengthColors[s];
        txt.textContent = strengthLabels[s];
    }

    [].forEach.call(document.querySelectorAll('.pass-toggle'), function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(btn.dataset.target);
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
        });
    });

    btnPrev.hidden = current === 0;
    btnNext.hidden = current === steps.length - 1;
    btnSubmit.hidden = current !== steps.length - 1;
    if (current === steps.length - 1 && form.elements['company_name'].value) fillSummary();
    renderStepper();
})();
</script>

<?php require BASE_PATH . '/views/layout/footer.php'; ?>
