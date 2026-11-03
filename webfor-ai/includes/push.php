<?php
/**
 * Push-уведомления менеджеру (Web Push): ключи VAPID создаются один раз, подписки — из приложения-админки.
 * Если отправить не удалось (старая подписка, нет сети) — подписка удаляется, а письмо на e-mail всё равно уходит.
 */
defined('ABSPATH') || exit;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

function wfai_vapid() {
    $keys = get_option('wfai_vapid');
    if (!is_array($keys) || empty($keys['publicKey'])) {
        try {
            $keys = VAPID::createVapidKeys();
            update_option('wfai_vapid', $keys, false);
        } catch (\Throwable $e) {
            error_log('[webfor-ai] не удалось создать ключи push: ' . $e->getMessage());
            return null;
        }
    }
    return $keys;
}

/** Адрес приложения-админки (плагин WebFor Site), а если его нет — стандартная админка. */
function wfai_admin_url($hash = '') {
    $base = function_exists('webfor_admin_app_url') ? webfor_admin_app_url() : admin_url('admin.php?page=wfai');
    return $base . ($hash ? '#' . $hash : '');
}

/** Отправляет уведомление на все подписанные устройства. Возвращает число успешных отправок. */
function wfai_push_all($title, $body, $url) {
    $subs = (array) get_option('wfai_push_subs', []);
    $keys = wfai_vapid();
    if (!$subs || !$keys) {
        return 0;
    }
    try {
        $push = new WebPush(['VAPID' => ['subject' => 'mailto:' . (get_option('admin_email') ?: 'admin@example.com'), 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]], ['TTL' => 3600, 'urgency' => 'high'], new \GuzzleHttp\Client(['timeout' => 10]));
        $payload = wp_json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
        foreach ($subs as $s) {
            $push->queueNotification(Subscription::create($s), $payload);
        }
        $ok = 0;
        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $ok++;
            } elseif ($report->isSubscriptionExpired()) {
                unset($subs[md5($report->getEndpoint())]);
            }
        }
        update_option('wfai_push_subs', $subs, false);
        return $ok;
    } catch (\Throwable $e) {
        error_log('[webfor-ai] push не отправлен: ' . $e->getMessage());
        return 0;
    }
}
