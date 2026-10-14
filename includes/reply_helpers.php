<?php
/*
  Shared helpers: logged-in user ගේ email / id හොයාගන්නවා, unread admin replies ගන්නවා.
  Match කරන්නේ user_id හෝ email එකෙන් (user_id column එක තාම නැත්නම් email එකෙන් විතරක්).
*/
if (!function_exists('cm_user_email')) {
    function cm_user_email() {
        global $pdo;
        static $cache = null;
        if ($cache !== null) return $cache;

        $email = '';
        if (!empty($_SESSION['user']['email']))   $email = $_SESSION['user']['email'];
        elseif (!empty($_SESSION['user_email']))  $email = $_SESSION['user_email'];
        elseif (!empty($_SESSION['email']))       $email = $_SESSION['email'];

        if ($email === '' && !empty($_SESSION['user_id']) && isset($pdo)) {
            try {
                $st = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                $st->execute([(int)$_SESSION['user_id']]);
                $email = (string)$st->fetchColumn();
            } catch (Exception $e) {
                $email = '';
            }
        }
        return $cache = trim($email);
    }
}

if (!function_exists('cm_user_id')) {
    function cm_user_id() {
        return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }
}

if (!function_exists('cm_fetch_unread')) {
    function cm_fetch_unread($limit = 5) {
        global $pdo;
        $email = cm_user_email();
        $uid   = cm_user_id();
        if (!isset($pdo) || ($email === '' && $uid === 0)) return [];
        $limit = (int)$limit;
        $sql = "SELECT id, subject, reply, replied_at FROM contact_messages
                WHERE reply IS NOT NULL AND reply <> '' AND reply_seen = 0 AND (%s)
                ORDER BY replied_at DESC LIMIT $limit";
        try {
            $st = $pdo->prepare(sprintf($sql, 'user_id = ? OR LOWER(email) = LOWER(?)'));
            $st->execute([$uid, $email]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* user_id column නැත්නම් email එකෙන් විතරක් */ }
        try {
            $st = $pdo->prepare(sprintf($sql, 'LOWER(email) = LOWER(?)'));
            $st->execute([$email]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('cm_unread_reply_count')) {
    function cm_unread_reply_count() {
        return count(cm_fetch_unread(100));
    }
}

if (!function_exists('cm_mark_replies_seen')) {
    function cm_mark_replies_seen() {
        global $pdo;
        $email = cm_user_email();
        $uid   = cm_user_id();
        if (!isset($pdo) || ($email === '' && $uid === 0)) return false;
        try {
            $pdo->prepare("UPDATE contact_messages SET reply_seen = 1
                           WHERE reply IS NOT NULL AND (user_id = ? OR LOWER(email) = LOWER(?))")
                ->execute([$uid, $email]);
            return true;
        } catch (Exception $e) { }
        try {
            $pdo->prepare("UPDATE contact_messages SET reply_seen = 1
                           WHERE reply IS NOT NULL AND LOWER(email) = LOWER(?)")
                ->execute([$email]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}