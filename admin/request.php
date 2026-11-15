<?php
declare(strict_types=1);

/** One service request: payload, status, internal note, optional status mail. */
require dirname(__DIR__) . '/app/bootstrap.php';
session_boot('admin');
$admin = auth_require('admin');

$id = admin_id();
$req = request_get($id);
if ($req === null) {
    abort(404);
}
$self = '/admin/request.php?id=' . $id;
$service = service_get((string) $req['service_slug']);

if (is_post()) {
    csrf_verify();
    $status = post_str('status', 20);
    $note = post_str('note', 3000);
    if (!in_array($status, admin_request_statuses(), true)) {
        redirect($self, ['error' => __('Please choose a valid status.')]);
    }
    $changed = $status !== $req['status'];
    request_update($id, $status, $note);
    audit('request.update', 'request', $id, ['service' => (string) $req['service_slug'], 'from' => (string) $req['status'], 'to' => $status]);
    $msg = __('The request was updated.');
    if (post_bool('send_mail')) {
        if (!v_email((string) $req['email'])) {
            $msg .= ' ' . __('No mail was sent: the requester has no valid e-mail address.');
        } elseif (!rate_limit_hit('admin_request_mail:' . $id, [5, 3600])) {
            $msg .= ' ' . __('No mail was sent: too many mails for this request.');
        } else {
            $sent = send_mail((string) $req['email'], __('Update on your request'), sprintf(
                "Hello%s,\n\nthere is an update on your request \"%s\" (reference #%d).\n\nNew status: %s\n\nIf you have questions, just reply to this message or write to %s.",
                $req['name'] !== '' ? ' ' . $req['name'] : '',
                $service ? (string) $service['name'] : (string) $req['service_slug'],
                $id,
                status_label($status),
                (string) config('mail.contact')
            ), (string) config('mail.contact'));
            audit('request.mail', 'request', $id, ['sent' => $sent, 'status_changed' => $changed]);
            $msg .= ' ' . ($sent ? __('The requester was informed by e-mail.') : __('The e-mail could not be sent.'));
        }
    }
    redirect($self, ['ok' => $msg]);
}

render_view('admin/request', [
    'title' => __('Request') . ' #' . $id,
    'req' => $req,
    'service' => $service,
    'payload' => admin_payload_rows($req['payload']),
    'requester' => $req['user_id'] !== null ? user_get((int) $req['user_id']) : null,
]);
