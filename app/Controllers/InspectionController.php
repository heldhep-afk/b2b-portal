<?php
/** Заказ проверки качества компанией-покупателем: по уже оформленной сделке или отмена ещё не начатой проверки. */
class InspectionController
{
    public function __construct(private PDO $pdo) {}

    private function back(string $key, string $message): never
    {
        $_SESSION[$key] = $message;
        header('Location: /account#deals');
        exit;
    }

    /** Только вошедший сотрудник компании с правом оформлять закупки; CSRF обязателен. */
    private function guard(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['flash_error'] = 'Войдите в аккаунт компании.';
            header('Location: /login');
            exit;
        }
        if (isStaffRole($_SESSION['role'] ?? '')) {
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }
        if (!canCompany('create_deals')) {
            $this->back('flash_error', 'Недостаточно прав для заказа проверки. Обратитесь к директору компании.');
        }
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.');
        }
    }

    /** Заказать проверку по сделке, которая уже оформлена и ещё «В работе». */
    public function order(): void
    {
        $this->guard();

        $dealId = (int)($_POST['deal_id'] ?? 0);
        $note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
        if ($dealId <= 0) {
            $this->back('flash_error', 'Некорректный запрос.');
        }
        if (mb_strlen($note) > 500) {
            $this->back('flash_error', 'Описание проверки не должно быть длиннее 500 символов.');
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT id, deal_number, status, total_amount, buyer_company_id FROM deals WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$dealId]);
            $deal = $stmt->fetch();

            if (!$deal || (int)$deal['buyer_company_id'] !== (int)$_SESSION['company_id']) {
                throw new RuntimeException('Сделка не найдена или недоступна.');
            }
            if ($deal['status'] !== 'in_progress') {
                throw new RuntimeException("Сделка {$deal['deal_number']} уже закрыта — заказать проверку нельзя.");
            }

            $stmt = $this->pdo->prepare('SELECT 1 FROM inspections WHERE deal_id = ? LIMIT 1');
            $stmt->execute([$dealId]);
            if ($stmt->fetchColumn()) {
                throw new RuntimeException("По сделке {$deal['deal_number']} проверка уже заказана.");
            }

            $fee = inspectionFeeFor((float)$deal['total_amount']);
            $stmt = $this->pdo->prepare(
                'INSERT INTO inspections (deal_id, requested_by, buyer_note, fee, reward) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$dealId, $_SESSION['user_id'], nullIfEmpty($note), $fee, inspectionRewardFor($fee)]);
            logInspectionEvent($this->pdo, (int)$this->pdo->lastInsertId(), (int)$_SESSION['user_id'], 'requested', null);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Inspection order failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось заказать проверку. Попробуйте позже.');
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->back('flash_error', $e->getMessage());
        }

        $this->back('flash_success', "Проверка по сделке {$deal['deal_number']} заказана. Доплата — " . formatPrice($fee)
            . '; реквизиты откроются кнопкой «Оплатить проверку».');
    }

    /** Отменить проверку, пока её не взял проверяющий. Если доплата уже получена, администратор вернёт её. */
    public function cancel(): void
    {
        $this->guard();

        $id = (int)($_POST['inspection_id'] ?? 0);
        if ($id <= 0) {
            $this->back('flash_error', 'Некорректный запрос.');
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT i.id, i.status, i.fee_status, d.deal_number, d.buyer_company_id
                 FROM inspections i JOIN deals d ON d.id = i.deal_id
                 WHERE i.id = ? FOR UPDATE'
            );
            $stmt->execute([$id]);
            $insp = $stmt->fetch();

            if (!$insp || (int)$insp['buyer_company_id'] !== (int)$_SESSION['company_id']) {
                throw new RuntimeException('Проверка не найдена или недоступна.');
            }
            if ($insp['status'] !== 'requested') {
                throw new RuntimeException('Проверку уже взял проверяющий — отменить её может только администратор.');
            }

            $stmt = $this->pdo->prepare(
                "UPDATE inspections SET status = 'cancelled', admin_comment = 'Отменена покупателем'
                 WHERE id = ? AND status = 'requested'"
            );
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Проверка уже обработана.');
            }
            logInspectionEvent($this->pdo, $id, (int)$_SESSION['user_id'], 'cancelled', 'Отменена покупателем');

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Inspection cancel failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось отменить проверку. Попробуйте позже.');
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->back('flash_error', $e->getMessage());
        }

        $this->back('flash_success', "Проверка по сделке {$insp['deal_number']} отменена."
            . ($insp['fee_status'] === 'paid' ? ' Администратор вернёт доплату.' : ''));
    }

    /**
     * Продавец согласует визит проверяющего: подтверждает предложенное время или предлагает своё,
     * и называет сотрудника, который будет сопровождать проверяющего (ФИО, номер сотрудника, телефон).
     */
    public function visit(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['flash_error'] = 'Войдите в аккаунт компании.';
            header('Location: /login');
            exit;
        }
        if (isStaffRole($_SESSION['role'] ?? '')) {
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }
        if (!canCompany('decide_deals')) {
            $this->back('flash_error', 'Согласовывать проверку может директор или сотрудник с правом решать по сделкам.');
        }
        if (!csrfValid()) {
            $this->back('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.');
        }

        $str = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $id = (int)($_POST['inspection_id'] ?? 0);
        $mode = $str('mode');
        $escortName = $str('escort_name');
        $escortNumber = $str('escort_number');
        $escortPhone = $str('escort_phone');
        $note = $str('visit_note');

        if ($id <= 0 || !in_array($mode, ['confirm', 'propose'], true)) {
            $this->back('flash_error', 'Некорректный запрос.');
        }
        if (mb_strlen($escortName) < 5 || mb_strlen($escortName) > 150) {
            $this->back('flash_error', 'Укажите ФИО сопровождающего сотрудника (5–150 символов).');
        }
        if ($escortNumber === '' || mb_strlen($escortNumber) > 50) {
            $this->back('flash_error', 'Укажите номер сопровождающего сотрудника (до 50 символов).');
        }
        if (mb_strlen($escortPhone) > 30 || mb_strlen($note) > 300) {
            $this->back('flash_error', 'Телефон — до 30 символов, комментарий — до 300.');
        }

        $newTime = null;
        if ($mode === 'propose') {
            $newTime = parseVisitDateTime($str('visit_at'));
            if (!$newTime) {
                $this->back('flash_error', 'Укажите дату и время проверки.');
            }
            if (($err = visitDateError($newTime)) !== null) {
                $this->back('flash_error', $err);
            }
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT i.id, i.status, i.fee_status, i.visit_status, i.visit_proposed_by, i.visit_at, d.deal_number, d.seller_company_id
                 FROM inspections i JOIN deals d ON d.id = i.deal_id WHERE i.id = ? FOR UPDATE'
            );
            $stmt->execute([$id]);
            $insp = $stmt->fetch();

            if (!$insp || (int)$insp['seller_company_id'] !== (int)$_SESSION['company_id']) {
                throw new RuntimeException('Проверка не найдена или недоступна.');
            }
            if ($insp['status'] !== 'assigned') {
                throw new RuntimeException('Согласовать время можно, когда проверяющий назначен и ещё не отправил отчёт.');
            }

            if ($mode === 'confirm') {
                if ($insp['visit_status'] !== 'proposed' || $insp['visit_proposed_by'] !== 'inspector' || !$insp['visit_at']) {
                    throw new RuntimeException('Сейчас нет времени проверки, предложенного проверяющим, — предложите своё.');
                }
                $stmt = $this->pdo->prepare(
                    "UPDATE inspections SET visit_status = 'confirmed', visit_confirmed_at = NOW(), visit_note = ?,
                            escort_name = ?, escort_number = ?, escort_phone = ?
                     WHERE id = ? AND status = 'assigned' AND visit_status = 'proposed' AND visit_proposed_by = 'inspector'"
                );
                $stmt->execute([nullIfEmpty($note), $escortName, $escortNumber, nullIfEmpty($escortPhone), $id]);
                $event = 'visit_confirmed';
                $eventComment = 'Время ' . formatDateTime($insp['visit_at']) . '; сопровождает ' . $escortName . ' (№ ' . $escortNumber . ')';
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE inspections SET visit_status = 'proposed', visit_proposed_by = 'seller', visit_at = ?, visit_confirmed_at = NULL,
                            visit_note = ?, escort_name = ?, escort_number = ?, escort_phone = ?
                     WHERE id = ? AND status = 'assigned'"
                );
                $stmt->execute([$newTime->format('Y-m-d H:i:00'), nullIfEmpty($note), $escortName, $escortNumber, nullIfEmpty($escortPhone), $id]);
                $event = 'visit_proposed';
                $eventComment = 'Продавец предлагает ' . formatDateTime($newTime->format('Y-m-d H:i:00')) . '; сопровождает ' . $escortName . ' (№ ' . $escortNumber . ')';
            }
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Состояние проверки уже изменилось — обновите страницу.');
            }
            logInspectionEvent($this->pdo, $id, (int)$_SESSION['user_id'], $event, $eventComment);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Inspection visit (seller) failed: ' . $e->getMessage());
            $this->back('flash_error', 'Не удалось сохранить. Проверьте, что выполнен SQL-скрипт sql/migration_inspection_visits.sql.');
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->back('flash_error', $e->getMessage());
        }

        $this->back('flash_success', $mode === 'confirm'
            ? "Время проверки по сделке {$insp['deal_number']} подтверждено, сопровождающий указан."
            : "Другое время по сделке {$insp['deal_number']} отправлено проверяющему на согласование.");
    }
}
