<?php
/** Решения покупателя по сделке: одобрить (дальше подтверждает администратор) или отклонить. */
class DealController
{
    public function __construct(private PDO $pdo) {}

    public function decide(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['company_id'])) {
            $_SESSION['flash_error'] = 'Войдите в аккаунт, чтобы принять решение по сделке.';
            header('Location: /login');
            exit;
        }

        if (isStaffRole($_SESSION['role'] ?? '')) {
            header('Location: ' . staffHome($_SESSION['role'] ?? ''));
            exit;
        }

        if (!canCompany('decide_deals')) {
            $this->finish('flash_error', 'Недостаточно прав для решений по сделкам. Обратитесь к директору компании.');
        }

        if (!csrfValid()) {
            $this->finish('flash_error', 'Сессия устарела. Обновите страницу и повторите действие.');
        }

        $dealId = (int)($_POST['deal_id'] ?? 0);
        $decision = is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';

        if ($dealId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            $this->finish('flash_error', 'Некорректный запрос.');
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT deal_number, status, payment_status, buyer_approved_at, buyer_company_id
                 FROM deals WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$dealId]);
            $deal = $stmt->fetch();

            // решение принимает только компания-покупатель
            if (!$deal || (int)$deal['buyer_company_id'] !== (int)$_SESSION['company_id']) {
                $this->finish('flash_error', 'Сделка не найдена или недоступна.');
            }
            if ($deal['status'] !== 'in_progress') {
                $this->finish('flash_error', "Сделка {$deal['deal_number']} уже закрыта — решение изменить нельзя.");
            }
            if ($deal['buyer_approved_at'] !== null) {
                $this->finish('flash_error', "Вы уже одобрили сделку {$deal['deal_number']}. Её подтвердит администратор.");
            }

            if ($decision === 'approve') {
                // условия в WHERE защищают от повторного нажатия и гонки
                $stmt = $this->pdo->prepare(
                    "UPDATE deals SET buyer_approved_at = NOW()
                     WHERE id = ? AND buyer_company_id = ? AND status = 'in_progress' AND buyer_approved_at IS NULL"
                );
                $stmt->execute([$dealId, $_SESSION['company_id']]);

                if ($stmt->rowCount() === 0) {
                    $this->finish('flash_error', "Сделка {$deal['deal_number']} уже обработана.");
                }
                $this->finish(
                    'flash_success',
                    "Вы одобрили сделку {$deal['deal_number']}. Администратор подтвердит её после проверки оплаты."
                );
            }

            $stmt = $this->pdo->prepare(
                "UPDATE deals SET status = 'rejected', closed_at = NOW()
                 WHERE id = ? AND buyer_company_id = ? AND status = 'in_progress' AND buyer_approved_at IS NULL"
            );
            $stmt->execute([$dealId, $_SESSION['company_id']]);

            if ($stmt->rowCount() === 0) {
                $this->finish('flash_error', "Сделка {$deal['deal_number']} уже обработана.");
            }

            // сделка отклонена — заказанную проверку, которую ещё не завершили, отменяем (доплату вернёт администратор)
            try {
                $stmt = $this->pdo->prepare("SELECT id FROM inspections WHERE deal_id = ? AND status IN ('requested', 'assigned') LIMIT 1");
                $stmt->execute([$dealId]);
                $inspId = $stmt->fetchColumn();
                if ($inspId !== false) {
                    $stmt = $this->pdo->prepare("UPDATE inspections SET status = 'cancelled', admin_comment = ? WHERE id = ?");
                    $stmt->execute(['Сделка отклонена покупателем', $inspId]);
                    logInspectionEvent($this->pdo, (int)$inspId, (int)$_SESSION['user_id'], 'cancelled', 'Сделка отклонена покупателем');
                }
            } catch (PDOException $e) {
                error_log('Inspection cancel on reject failed: ' . $e->getMessage());
            }

            $this->finish(
                'flash_success',
                $deal['payment_status'] === 'paid'
                    ? "Сделка {$deal['deal_number']} отклонена. Администратор вернёт оплату."
                    : "Сделка {$deal['deal_number']} отклонена."
            );
        } catch (PDOException $e) {
            error_log('Deal decision failed: ' . $e->getMessage());
            $this->finish('flash_error', 'Не удалось сохранить решение. Попробуйте позже.');
        }
    }

    private function finish(string $flashKey, string $message): never
    {
        $_SESSION[$flashKey] = $message;
        header('Location: /account#deals');
        exit;
    }
}
