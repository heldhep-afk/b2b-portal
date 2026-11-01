<?php
/** Экранирование вывода. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Забирает из массива (обычно $_POST) поля компании. */
function collectCompanyData(array $src): array
{
    $get = fn(string $k): string => trim((string)($src[$k] ?? ''));

    return [
        'company_name'   => $get('company_name'),
        'inn'            => preg_replace('/\s+/', '', $get('inn')),
        'legal_address'  => $get('legal_address'),
        'contact_person' => $get('contact_person'),
        'website'        => $get('website'),
        'description'    => $get('description'),
    ];
}

/**
 * Проверяет данные компании. Возвращает текст ошибки или '' если всё хорошо.
 * Поле website нормализуется (добавляется https://).
 */
function validateCompanyData(array &$d, bool $checkInn = true): string
{
    if ($d['company_name'] === '') {
        return 'Укажите название компании.';
    }
    if ($checkInn && !preg_match('/^(\d{10}|\d{12})$/', $d['inn'])) {
        return 'ИНН должен состоять из 10 цифр (организация) или 12 цифр (ИП).';
    }
    if ($d['legal_address'] === '') {
        return 'Укажите юридический адрес.';
    }
    if ($d['contact_person'] === '') {
        return 'Укажите контактное лицо.';
    }
    if ($d['website'] !== '') {
        if (!preg_match('~^https?://~i', $d['website'])) {
            $d['website'] = 'https://' . $d['website'];
        }
        if (!filter_var($d['website'], FILTER_VALIDATE_URL)) {
            return 'Некорректный адрес сайта.';
        }
    }
    return '';
}

/** Пустые необязательные поля сохраняем в БД как NULL. */
function nullIfEmpty(string $value): ?string
{
    return $value === '' ? null : $value;
}

/** Цена в формате 12 345,67 ₽ */
function formatPrice(mixed $price): string
{
    return number_format((float)$price, 2, ',', ' ') . ' ₽';
}

/** Метаданные статуса сделки: подпись, цвет бейджа Bootstrap и группа для фильтра. */
function dealStatusMeta(string $status): array
{
    return match ($status) {
        'in_progress' => ['label' => 'В работе',  'badge' => 'warning', 'group' => 'progress'],
        'completed'   => ['label' => 'Завершён',  'badge' => 'success', 'group' => 'done'],
        'rejected'    => ['label' => 'Отклонён',  'badge' => 'danger',  'group' => 'rejected'],
        'cancelled'   => ['label' => 'Отменён',   'badge' => 'secondary', 'group' => 'cancelled'],
        default       => ['label' => $status,     'badge' => 'light',   'group' => 'progress'],
    };
}

/** Дата в формате 31.12.2026 (или тире). */
function formatDate(?string $dt): string
{
    return $dt ? date('d.m.Y', strtotime($dt)) : '—';
}

/** Инициалы для аватара: «Иванов Иван» -> «ИИ». */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function roleLabel(string $role): string
{
    return match ($role) {
        'admin'   => 'Администратор',
        'inspector' => 'Проверяющий',
        'manager' => 'Менеджер',
        'client'  => 'Представитель компании',
        default   => $role,
    };
}

/**
 * Забирает из сессии адрес, на который нужно вернуть пользователя после входа
 * (например, /checkout?product_id=5). Принимаются только внутренние пути.
 */
function popAfterLogin(): string
{
    $next = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);

    return (is_string($next) && preg_match('~^/(?![/\\\\])[^\r\n]*$~', $next)) ? $next : '';
}

/** CSRF-токен сессии (для форм, меняющих данные). */
function csrfToken(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

function csrfValid(): bool
{
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && isset($_SESSION['csrf']) && is_string($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/** Дата и время: 31.12.2026 14:05 */
function formatDateTime(?string $dt): string
{
    return $dt ? date('d.m.Y H:i', strtotime($dt)) : '—';
}

/** Статус оплаты сделки (платежи проходят через администраторов платформы). */
function paymentStatusMeta(string $status): array
{
    return match ($status) {
        'unpaid'   => ['label' => 'Ждёт оплаты',          'badge' => 'secondary'],
        'paid'     => ['label' => 'Оплачено (у платформы)', 'badge' => 'info'],
        'paid_out' => ['label' => 'Выплачено продавцу',   'badge' => 'success'],
        'refunded' => ['label' => 'Возвращено покупателю', 'badge' => 'dark'],
        default    => ['label' => $status,                'badge' => 'light'],
    };
}

function adminActionLabel(string $action): string
{
    return match ($action) {
        'confirm_payment' => 'Оплата подтверждена',
        'confirm_deal'    => 'Сделка подтверждена',
        'cancel'          => 'Сделка отменена',
        'payout'          => 'Выплата продавцу',
        'refund'          => 'Возврат покупателю',
        default           => $action,
    };
}

/** Какие действия администратор может выполнить по сделке в её текущем состоянии. */
function adminDealActions(string $status, string $payment, bool $buyerApproved, ?string $inspectionStatus = null): array
{
    $actions = [];
    if ($status === 'in_progress') {
        if ($payment === 'unpaid') {
            $actions[] = 'confirm_payment';
        }
        // если заказана проверка, сделку можно подтвердить только после того, как проверка подтверждена или отменена
        if ($payment === 'paid' && $buyerApproved && !inspectionBlocksDeal($inspectionStatus)) {
            $actions[] = 'confirm_deal';
        }
        $actions[] = 'cancel';
    }
    if ($status === 'completed' && $payment === 'paid') {
        $actions[] = 'payout';
    }
    if (in_array($status, ['rejected', 'cancelled'], true) && $payment === 'paid') {
        $actions[] = 'refund';
    }
    return $actions;
}

/** Реквизиты платформы для оплаты сделок. ЗАМЕНИТЕ значения на реальные. */
function platformRequisites(): array
{
    return [
        'Получатель'     => 'ООО «B2B Портал»',
        'ИНН'            => '0000000000',
        'Расчётный счёт' => '00000000000000000000',
        'Банк'           => 'Название банка',
        'БИК'            => '000000000',
    ];
}

/** Компактная сумма для календаря и графиков: 1,2 млн / 245 тыс / 980. */
function formatCompact(float $v): string
{
    if ($v >= 1000000) {
        return rtrim(rtrim(number_format($v / 1000000, 1, ',', ''), '0'), ',') . ' млн';
    }
    if ($v >= 1000) {
        return number_format($v / 1000, $v >= 100000 ? 0 : 1, ',', '') . ' тыс';
    }
    return number_format($v, 0, ',', ' ');
}

/** Название месяца по-русски: 1 => «январь» (или «января», если $genitive). */
function ruMonthName(int $month, bool $genitive = false): string
{
    $nom = ['', 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
    $gen = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    return ($genitive ? $gen : $nom)[$month] ?? '';
}

/** Первая буква заглавная (корректно для кириллицы). */
function ruUcfirst(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

/** Название роли внутри компании. */
function companyRoleLabel(string $role): string
{
    return match ($role) {
        'owner'    => 'Директор',
        'manager'  => 'Менеджер',
        'employee' => 'Сотрудник',
        default    => $role,
    };
}

/** Права, которыми директор наделяет сотрудников: ключ => [название, пояснение]. */
function companyPermissionCatalog(): array
{
    return [
        'create_deals'      => ['Оформлять закупки',           'заказывать товары и услуги у других компаний'],
        'decide_deals'      => ['Одобрять и отклонять сделки', 'принимать решение покупателя по сделкам компании'],
        'manage_products'   => ['Товары компании',             'добавлять и удалять товары'],
        'manage_categories' => ['Категории компании',          'выбирать категории товаров и услуг'],
        'edit_company'      => ['Данные компании',             'менять название, адрес, телефон и описание'],
        'review_requests'   => ['Заявки на вступление',        'принимать и отклонять заявки новых сотрудников'],
    ];
}

/** Права роли по умолчанию (пока директор не настроил индивидуальные). */
function companyRoleDefaults(string $role): array
{
    return match ($role) {
        'owner'   => array_keys(companyPermissionCatalog()),
        'manager' => ['create_deals', 'decide_deals', 'manage_products', 'manage_categories', 'review_requests'],
        default   => ['create_deals'],
    };
}

/** Действующие права: индивидуальные (строка через запятую; NULL — нет настройки) или права роли. */
function companyEffectivePermissions(string $role, ?string $custom): array
{
    if ($role === 'owner') {
        return companyRoleDefaults('owner');
    }
    if ($custom === null) {
        return companyRoleDefaults($role);
    }
    $allowed = array_filter(explode(',', $custom), fn($p) => $p !== '');
    return array_values(array_intersect(array_keys(companyPermissionCatalog()), $allowed));
}

/**
 * Права текущего пользователя внутри его компании (роль и права актуализируются при каждом запросе в index.php).
 * Директор может всё; управление командой и правами не делегируется.
 */
function canCompany(string $permission): bool
{
    // сотрудник с неодобренной заявкой не имеет никаких прав
    if (($_SESSION['join_status'] ?? 'approved') !== 'approved') {
        return false;
    }
    $role = $_SESSION['company_role'] ?? 'employee';
    if ($permission === 'manage_team') {
        return $role === 'owner';
    }
    $custom = $_SESSION['permissions'] ?? null;
    return in_array($permission, companyEffectivePermissions($role, is_string($custom) ? $custom : null), true);
}


/** Алфавит кода компании: без похожих символов (I, L, O, 0, 1). */
function joinCodeAlphabet(): string
{
    return 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
}

/** Новый случайный код компании из 8 символов. */
function generateJoinCode(): string
{
    $alphabet = joinCodeAlphabet();
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

/** Приводит введённый код к виду XXXXXXXX: без пробелов и дефисов, заглавными. */
function normalizeJoinCode(string $raw): string
{
    return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $raw));
}

/** Код для показа: ABCD-EF23. */
function formatJoinCode(?string $code): string
{
    $code = (string)$code;
    return strlen($code) === 8 ? substr($code, 0, 4) . '-' . substr($code, 4) : $code;
}


/* ===================== Персонал и проверки качества ===================== */

/** Сотрудник платформы (не компании): администратор или проверяющий. */
function isStaffRole(?string $role): bool
{
    return in_array($role, ['admin', 'inspector'], true);
}

/** Главная страница сотрудника платформы. */
function staffHome(?string $role): string
{
    return $role === 'inspector' ? '/inspector' : '/admin';
}

/**
 * Проверка прав для страниц персонала. Роль каждый раз перепроверяется в БД.
 * Гостя отправляет на вход, остальных — на их главную страницу. Возвращает строку users.
 *
 * @param string[] $roles допустимые роли
 */
function requireStaff(PDO $pdo, array $roles, string $loginMessage): array
{
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['after_login'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            ? ($_SERVER['REQUEST_URI'] ?? '/')
            : '/';
        $_SESSION['flash_error'] = $loginMessage;
        header('Location: /login');
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, company_id, username, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_active'] !== 1 || !in_array($user['role'], $roles, true)) {
        $_SESSION['flash_error'] = 'Недостаточно прав для доступа к этой странице.';
        header('Location: ' . ($user ? (isStaffRole($user['role']) ? staffHome($user['role']) : '/account') : '/'));
        exit;
    }
    $_SESSION['role'] = $user['role'];
    return $user;
}

/** Параметры доплаты за проверку: доля от суммы сделки, минимум, максимум, шаг округления. ИЗМЕНИТЕ под свои тарифы. */
function inspectionPricing(): array
{
    return ['rate' => 0.02, 'min' => 2000.0, 'max' => 30000.0, 'step' => 100.0, 'reward_share' => 0.7];
}

/** Доплата компании за проверку сделки на указанную сумму. */
function inspectionFeeFor(float $dealTotal): float
{
    $p = inspectionPricing();
    $fee = max($p['min'], min($p['max'], $dealTotal * $p['rate']));
    return round($fee / $p['step']) * $p['step'];
}

/** Вознаграждение проверяющего по умолчанию — доля от доплаты (администратор может изменить при подтверждении). */
function inspectionRewardFor(float $fee): float
{
    return round($fee * inspectionPricing()['reward_share'] / 50) * 50.0;
}

function inspectionStatusMeta(string $status): array
{
    return match ($status) {
        'requested' => ['label' => 'Ждёт проверяющего',       'badge' => 'secondary'],
        'assigned'  => ['label' => 'Проверяется',             'badge' => 'warning'],
        'submitted' => ['label' => 'Отчёт у администратора',  'badge' => 'info'],
        'approved'  => ['label' => 'Проверка подтверждена',   'badge' => 'success'],
        'cancelled' => ['label' => 'Проверка отменена',       'badge' => 'dark'],
        default     => ['label' => $status,                   'badge' => 'light'],
    };
}

function inspectionVerdictMeta(?string $verdict): array
{
    return match ($verdict) {
        'ok'      => ['label' => 'Соответствует',          'badge' => 'success', 'icon' => 'bi-check-circle'],
        'remarks' => ['label' => 'Есть замечания',         'badge' => 'warning', 'icon' => 'bi-exclamation-circle'],
        'fail'    => ['label' => 'Не соответствует',       'badge' => 'danger',  'icon' => 'bi-x-circle'],
        default   => ['label' => '—',                      'badge' => 'light',   'icon' => 'bi-dash-circle'],
    };
}

function inspectionFeeMeta(string $status): array
{
    return match ($status) {
        'unpaid'   => ['label' => 'Доплата ждёт оплаты', 'badge' => 'secondary'],
        'paid'     => ['label' => 'Доплата получена',    'badge' => 'info'],
        'refunded' => ['label' => 'Доплата возвращена',  'badge' => 'dark'],
        default    => ['label' => $status,               'badge' => 'light'],
    };
}

function inspectionEventLabel(string $action): string
{
    return match ($action) {
        'requested'     => 'Проверка заказана',
        'fee_confirmed' => 'Доплата подтверждена',
        'assigned'      => 'Проверяющий назначен',
        'taken'         => 'Проверяющий взял задание',
        'submitted'     => 'Отчёт отправлен',
        'approved'      => 'Отчёт подтверждён',
        'returned'      => 'Отчёт возвращён на доработку',
        'cancelled'     => 'Проверка отменена',
        'fee_refunded'  => 'Доплата возвращена',
        'reward_paid'   => 'Вознаграждение выплачено',
        'released'      => 'Задание возвращено в свободные',
        'visit_proposed'  => 'Предложено время проверки',
        'visit_confirmed' => 'Время проверки согласовано',
        default         => $action,
    };
}

/** Записывает действие в журнал проверки. */
function logInspectionEvent(PDO $pdo, int $inspectionId, ?int $userId, string $action, ?string $comment = null): void
{
    $stmt = $pdo->prepare('INSERT INTO inspection_events (inspection_id, user_id, action, comment) VALUES (?, ?, ?, ?)');
    $stmt->execute([$inspectionId, $userId, $action, ($comment === null || $comment === '') ? null : $comment]);
}

/** Пока проверка не завершена, администратор не может подтвердить сделку. */
function inspectionBlocksDeal(?string $inspectionStatus): bool
{
    return in_array($inspectionStatus, ['requested', 'assigned', 'submitted'], true);
}


/** Какие действия администратор может выполнить по проверке в её текущем состоянии. */
function inspectionAdminActions(array $i): array
{
    $actions = [];
    $active = in_array($i['status'], ['requested', 'assigned', 'submitted'], true);

    if ($active && $i['fee_status'] === 'unpaid') {
        $actions[] = 'confirm_fee';
    }
    if (in_array($i['status'], ['requested', 'assigned'], true) && $i['fee_status'] === 'paid') {
        $actions[] = 'assign';
    }
    if ($i['status'] === 'submitted') {
        $actions[] = 'approve';
        $actions[] = 'return';
    }
    if ($active) {
        $actions[] = 'cancel';
    }
    if ($i['status'] === 'cancelled' && $i['fee_status'] === 'paid') {
        $actions[] = 'refund_fee';
    }
    if ($i['status'] === 'approved' && $i['reward_paid_at'] === null) {
        $actions[] = 'pay_reward';
    }
    return $actions;
}


/** Состояние согласования визита проверяющего. */
function visitStatusMeta(?string $status, ?string $proposedBy): array
{
    if ($status === 'confirmed') {
        return ['label' => 'Время согласовано', 'badge' => 'success'];
    }
    if ($status === 'proposed') {
        return $proposedBy === 'seller'
            ? ['label' => 'Продавец предложил время — ждёт ответа проверяющего', 'badge' => 'warning']
            : ['label' => 'Проверяющий предложил время — ждёт ответа продавца', 'badge' => 'warning'];
    }
    return ['label' => 'Время не согласовано', 'badge' => 'secondary'];
}

/** Дата и время из поля datetime-local (Y-m-d\TH:i) или null, если формат неверен. */
function parseVisitDateTime(string $value): ?DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($value));
    return ($dt && $dt->format('Y-m-d\TH:i') === trim($value)) ? $dt : null;
}

/** Проверка срока визита: не в прошлом и не дальше чем через 60 дней. Возвращает текст ошибки или null. */
function visitDateError(DateTimeImmutable $dt): ?string
{
    $now = new DateTimeImmutable('now');
    if ($dt < $now) {
        return 'Время проверки не может быть в прошлом.';
    }
    if ($dt > $now->modify('+60 days')) {
        return 'Время проверки — не дальше чем через 60 дней.';
    }
    return null;
}


/** Этапы проверки для индикатора шагов. */
function inspectionStepLabels(): array
{
    return ['Заказана', 'Оплачена', 'Проверяющий назначен', 'Время согласовано', 'Отчёт отправлен', 'Подтверждена'];
}

/** Какие этапы пройдены (массив из 6 bool). По полям: status, fee_status, visit_status. */
function inspectionStepFlags(string $status, string $feeStatus, ?string $visitStatus): array
{
    $assigned = in_array($status, ['assigned', 'submitted', 'approved'], true);
    $reported = in_array($status, ['submitted', 'approved'], true);
    return [
        true,
        $feeStatus === 'paid' || $assigned,
        $assigned,
        $visitStatus === 'confirmed' || $reported,
        $reported,
        $status === 'approved',
    ];
}

/**
 * Подсказка «что делать дальше» для участника: viewer = buyer | seller | inspector.
 * Возвращает ['text' => ..., 'action' => true, если ход за этим участником].
 */
function inspectionNextStep(string $viewer, string $status, string $feeStatus, ?string $visitStatus, ?string $visitBy, bool $rewardPaid = false): array
{
    $t = fn(string $text, bool $action = false): array => ['text' => $text, 'action' => $action];

    if ($status === 'cancelled') {
        return $t($viewer === 'buyer' && $feeStatus === 'paid' ? 'Проверка отменена, доплату вернёт администратор' : 'Проверка отменена');
    }
    if ($status === 'approved') {
        return match ($viewer) {
            'buyer', 'seller' => $t('Проверка подтверждена — результат в окне «Проверка»'),
            default => $t($rewardPaid ? 'Вознаграждение выплачено' : 'Подтверждено, ждёт выплаты вознаграждения'),
        };
    }
    if ($status === 'submitted') {
        return $t('Отчёт на проверке у администратора');
    }
    if ($status === 'assigned') {
        $confirmed = $visitStatus === 'confirmed';
        $byInspector = $visitStatus === 'proposed' && $visitBy === 'inspector';
        $bySeller = $visitStatus === 'proposed' && $visitBy === 'seller';
        return match ($viewer) {
            'seller' => $byInspector ? $t('Подтвердите время и назовите сопровождающего сотрудника', true)
                : ($bySeller ? $t('Ждём ответа проверяющего на ваше время')
                : ($confirmed ? $t('Время согласовано — подготовьте сопровождающего')
                : $t('Можно предложить время проверки и назвать сопровождающего', true))),
            'inspector' => $bySeller ? $t('Продавец предложил время — согласитесь или предложите другое', true)
                : ($byInspector ? $t('Ждём подтверждения времени продавцом')
                : ($confirmed ? $t('Проведите проверку и отправьте отчёт', true)
                : $t('Предложите продавцу время проверки', true))),
            default => $t($confirmed ? 'Проверка пройдёт в согласованное время' : 'Проверяющий согласует время с продавцом'),
        };
    }
    // requested
    if ($feeStatus !== 'paid') {
        return $viewer === 'buyer' ? $t('Оплатите проверку по реквизитам платформы', true) : $t('Ждём оплаты проверки заказчиком');
    }
    return $viewer === 'inspector' ? $t('Свободное задание — можно взять', true) : $t('Ждём проверяющего');
}
